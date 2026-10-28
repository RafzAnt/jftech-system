<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * The wording of a printed report in the usual Philippine business style: dates written out as
 * "October 27, 2026", the 12-hour clock in Manila time, "For the period ..." under the title, the branch
 * the figures cover and the person who prepared it. The layout is layouts/print_report.blade.php.
 */
final class ReportFormat
{
    /** "October 27, 2026"; whatever was typed when it is not a date. */
    public static function date(mixed $date): string
    {
        $parsed = self::parse($date);

        return $parsed ? $parsed->format('F j, Y') : trim((string) $date);
    }

    /** "October 27, 2026, 10:15 AM" */
    public static function dateTime(mixed $moment): string
    {
        $parsed = self::parse($moment);

        return $parsed ? $parsed->format('F j, Y, g:i A') : trim((string) $moment);
    }

    /** The line under the report title: which dates the figures cover. */
    public static function coverage(?string $from, ?string $to): string
    {
        $from = trim((string) $from);
        $to = trim((string) $to);

        return match (true) {
            $from !== '' && $to !== '' && self::date($from) === self::date($to) => 'For ' . self::date($from),
            $from !== '' && $to !== '' => 'For the period ' . self::date($from) . ' to ' . self::date($to),
            $from !== '' => 'For the period starting ' . self::date($from),
            $to !== '' => 'For the period up to ' . self::date($to),
            default => 'For all dates',
        };
    }

    /**
     * The branch the report covers. A branch admin's report is always their own branch; the super admin's is
     * the branch picked on the page, or every branch.
     */
    public static function branch(?string $selected = null): string
    {
        $branch = BranchScope::current() ?? trim((string) $selected);

        return $branch === '' ? 'All Branches' : $branch . ' Branch';
    }

    /** "Cash", "GCash", "Bank Transfer": a payment method as it is written on a report. */
    public static function paymentMethod(?string $method): string
    {
        return match (strtolower(trim((string) $method))) {
            'gcash' => 'GCash',
            default => ucwords(str_replace('_', ' ', trim((string) $method))),
        };
    }

    /** Who is printing the report, for the "Prepared by" signature. */
    public static function preparedBy(): array
    {
        $user = Auth::user();
        if (! $user) {
            return ['name' => '', 'title' => ''];
        }

        $title = match (true) {
            $user->isSuperAdmin() => 'Super Admin',
            $user->isBranchAdmin() => trim('Branch Admin, ' . trim((string) $user->branch_name), ' ,'),
            default => '',
        };

        return ['name' => (string) $user->full_name, 'title' => $title];
    }

    /**
     * Text for a CSS string (the page footer is a CSS box, not HTML). Everything but plain letters, digits and
     * a few marks is written as a hex escape, so a name can never close the string or the <style> block.
     */
    public static function cssString(string $text): string
    {
        $out = '';
        foreach (mb_str_split(preg_replace('/\s+/u', ' ', $text) ?? '') as $char) {
            $out .= preg_match('/^[A-Za-z0-9 .,:()\-]$/', $char) ? $char : sprintf('\\%X ', mb_ord($char));
        }

        return $out;
    }

    private static function parse(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }
}
