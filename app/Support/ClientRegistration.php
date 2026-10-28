<?php

namespace App\Support;

use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Adding a client: the Register New Client form of the Clients page and the confirmation of an installation report from the
 * employee app both end here, so a client is checked and saved in one way. A client may be connected to a NAP port in the same
 * step; the port is taken (or the whole thing refused) in one transaction.
 */
final class ClientRegistration
{
    /** The modes of payment (MOP) of a client. They match the payment methods the cashier records. */
    public const PAYMENT_MODES = ['cash' => 'Cash', 'gcash' => 'GCash'];

    /**
     * The details of a new client, checked and cleaned. Throws a DomainException with a message to show the person.
     *
     * $input: full_name, contact_number, email, barangay_name, exact_address, installation_date, internet_plan_id, payment_mode,
     * ip_address, facebook_account, coordinates (as pasted from Google Maps), nap_port_id.
     *
     * @return array<string, mixed>
     */
    public static function clean(array $input, string $branch, bool $emailRequired, bool $napRequired): array
    {
        $text = static fn (string $key): string => trim((string) ($input[$key] ?? ''));

        $fullName = preg_replace('/\s+/u', ' ', $text('full_name'));
        $contact = $text('contact_number');
        $email = $text('email');
        $barangay = $text('barangay_name');
        $address = $text('exact_address');
        $date = $text('installation_date');
        $mode = strtolower($text('payment_mode'));
        $ip = $text('ip_address');
        $facebook = $text('facebook_account');

        if ($fullName === '' || $contact === '' || $barangay === '' || $address === '' || $date === '') {
            throw new DomainException('Please complete all required client registration fields.');
        }
        if (mb_strlen($fullName) < 2 || mb_strlen($fullName) > 100) {
            throw new DomainException('Enter the name of the client (up to 100 letters).');
        }
        if (! preg_match('/^\d{11}$/', $contact)) {
            throw new DomainException('Mobile number must contain exactly 11 digits.');
        }
        if ($email === '' ? $emailRequired : ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException($email === '' ? 'Please complete all required client registration fields.' : 'Please enter a valid email address.');
        }
        if (mb_strlen($address) > 255) {
            throw new DomainException('The address is too long (up to 255 characters).');
        }
        $parsedDate = \DateTime::createFromFormat('Y-m-d', $date);
        if (! $parsedDate || $parsedDate->format('Y-m-d') !== $date) {
            throw new DomainException('Please enter a valid installation date.');
        }
        if (! isset(self::PAYMENT_MODES[$mode])) {
            throw new DomainException('Choose the mode of payment (' . implode(' or ', self::PAYMENT_MODES) . ').');
        }
        if ($ip !== '' && ! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new DomainException('The IP address is not valid, for example 192.168.1.20.');
        }
        if (mb_strlen($facebook) > 150) {
            throw new DomainException('The Facebook account is too long (up to 150 characters).');
        }

        // The barangay is spelled as the branch has it when the branch has a list.
        foreach (NapBoxes::barangaysOf($branch) as $option) {
            if (strcasecmp($option, $barangay) === 0) {
                $barangay = $option;
                break;
            }
        }

        $plan = DB::table('internet_plans')->where('id', (int) ($input['internet_plan_id'] ?? 0))->where('status', 'active')->where('archived', 0)->first();
        if (! $plan) {
            throw new DomainException('Please select an active internet plan.');
        }

        [$latitude, $longitude] = Coordinates::parse($text('coordinates'), 'client');

        $portId = (int) ($input['nap_port_id'] ?? 0) ?: null;
        if ($portId === null && $napRequired) {
            throw new DomainException('Choose the NAP box and the port the client is connected to.');
        }

        return [
            'full_name' => $fullName, 'contact_number' => $contact, 'email' => $email === '' ? null : $email,
            'branch_name' => $branch, 'barangay_name' => $barangay, 'exact_address' => $address, 'installation_date' => $date,
            'plan' => $plan, 'payment_mode' => $mode, 'ip_address' => $ip === '' ? null : $ip,
            'facebook_account' => $facebook === '' ? null : $facebook,
            'latitude' => $latitude, 'longitude' => $longitude, 'nap_port_id' => $portId,
        ];
    }

    /**
     * Save a client that clean() returned, with the first bill, and connect it to its NAP port. Returns the client id.
     * $exceptInstallation is the installation report being confirmed: the port it reserved is not "taken" for itself.
     */
    public static function create(array $data, ?int $exceptInstallation = null): int
    {
        return DB::transaction(function () use ($data, $exceptInstallation): int {
            $port = null;
            if ($data['nap_port_id']) {
                $port = self::lockPort((int) $data['nap_port_id'], (string) $data['branch_name'], $exceptInstallation);
            }

            // Only Balilihan has its barangays in the barangays table; the other branches keep the typed name.
            $barangayId = strcasecmp((string) $data['branch_name'], 'Balilihan') === 0
                ? DB::table('barangays')->where('barangay_name', $data['barangay_name'])->value('id')
                : null;
            $plan = $data['plan'];

            $clientId = (int) DB::table('clients')->insertGetId([
                'client_code' => 'JFT-' . date('Y') . '-' . strtoupper(uniqid()),
                'full_name' => $data['full_name'], 'contact_number' => $data['contact_number'], 'email' => $data['email'],
                'branch_name' => $data['branch_name'], 'registered_barangay_name' => $data['barangay_name'], 'barangay_id' => $barangayId,
                'exact_address' => $data['exact_address'], 'wifi_plan' => $plan->plan_name, 'internet_plan_id' => $plan->id,
                'monthly_rate' => $plan->monthly_price, 'installation_fee' => $plan->installation_fee, 'installation_date' => $data['installation_date'],
                'payment_mode' => $data['payment_mode'], 'ip_address' => $data['ip_address'], 'facebook_account' => $data['facebook_account'],
                'latitude' => $data['latitude'], 'longitude' => $data['longitude'],
                'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
            ]);

            $billingStart = date('Y-m-d');
            $dueDate = date('Y-m-d', strtotime($billingStart . ' +30 days'));
            DB::table('bills')->insert([
                'client_id' => $clientId, 'bill_number' => 'BILL-' . date('Ymd') . '-' . strtoupper(uniqid()),
                'billing_period_start' => $billingStart, 'billing_period_end' => $dueDate, 'amount' => $plan->monthly_price,
                'due_date' => $dueDate, 'status' => 'pending',
            ]);

            if ($port) {
                DB::table('nap_ports')->where('id', $port->id)->update(['status' => 'occupied', 'client_id' => $clientId, 'occupied_at' => now(), 'updated_at' => now()]);
            }

            return $clientId;
        });
    }

    /**
     * A port of a box of this branch that is free to give to a client (locked for the rest of the transaction). Throws a
     * DomainException when it is not: in use, faulty, reserved by another waiting report, or in another branch.
     */
    public static function lockPort(int $portId, string $branch, ?int $exceptInstallation = null): object
    {
        $port = DB::table('nap_ports')->where('id', $portId)->lockForUpdate()->first();
        $box = $port ? DB::table('nap_boxes')->where('id', $port->nap_box_id)->first() : null;
        if (! $port || ! $box || strcasecmp((string) $box->branch_name, $branch) !== 0 || $box->archived || $box->status !== 'active') {
            throw new DomainException('Choose a NAP box and port of your branch.');
        }
        $label = "{$box->name} port {$port->port_number}";
        if ($port->status !== 'available') {
            throw new DomainException("{$label} is " . ($port->status === 'occupied' ? 'already connected to a client' : 'faulty') . '. Choose another port.');
        }
        if (in_array((int) $port->id, NapBoxes::reservedPortIds(null, $exceptInstallation), true)) {
            throw new DomainException("{$label} is already used in an installation report that is waiting for the admin. Choose another port.");
        }
        $port->box_name = $box->name;
        $port->box_id = (int) $box->id;

        return $port;
    }
}
