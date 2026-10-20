<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * Payments are recorded by the branch admin of the client's branch. The super admin looks at the bills and the payments, but cannot pay.
 *
 * Seed: users 1 super admin, 2 Balilihan admin, 3 Loon admin. Client 1 (Balilihan) has bill 1; client 2 (Loon) has bills 2 and 3.
 */
class PaymentPermissionTest extends TestCase
{
    use BranchTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', fn (...$values) => max($values), -1);
    }

    private function payment(array $extra = []): array
    {
        return $extra + [
            'record_payment' => 1, 'client_id' => 2, 'bill_id' => 3, 'amount' => 100, 'payment_date' => now()->toDateString(),
            'payment_method' => 'cash', 'submission_token' => '3f2b8c1e-9d4a-4b7e-8a61-0c5d2e7f9a10',
        ];
    }

    public function test_the_super_admin_sees_the_bills_but_has_no_way_to_pay(): void
    {
        $this->signIn(1);

        $page = $this->get('/client-accounts?client_id=2')->assertOk();
        $html = $page->getContent();

        $this->assertFalse($page->viewData('canRecordPayments'));
        $this->assertStringContainsString('B-3', $html, 'the bills are there to look at');
        $this->assertStringContainsString('Payment History', $html);
        $this->assertStringNotContainsString('openPaymentModal(this)', $html, 'no Pay button');
        $this->assertStringNotContainsString('id="paymentModal"', $html, 'no payment form');
        $this->assertStringNotContainsString('Record Payment', $html);
        $this->assertStringContainsString('The branch admin records the payments.', $html);
    }

    public function test_the_super_admin_cannot_record_a_payment_even_by_sending_the_form(): void
    {
        $this->signIn(1);

        $this->post('/client-accounts?client_id=2', $this->payment())->assertForbidden();

        $this->assertSame(2, DB::table('payments')->count());
        $this->assertSame('overdue', DB::table('bills')->where('id', 3)->value('status'));
    }

    public function test_the_branch_admin_still_has_the_pay_button_and_the_form_for_their_own_clients(): void
    {
        $this->signIn(3);

        $page = $this->get('/client-accounts?client_id=2')->assertOk();
        $html = $page->getContent();

        $this->assertTrue($page->viewData('canRecordPayments'));
        $this->assertStringContainsString('openPaymentModal(this)', $html);
        $this->assertStringContainsString('id="paymentModal"', $html);
        $this->assertStringContainsString('Record Payment', $html);
        $this->assertStringNotContainsString('The branch admin records the payments.', $html);
    }

    public function test_the_branch_admin_reaches_the_payment_handler_but_not_for_another_branchs_client(): void
    {
        $this->signIn(3);
        // SQLite has no row locks, so the save itself is not run here; what matters is that the form is not refused.
        $this->assertNotSame(403, $this->post('/client-accounts?client_id=2', $this->payment())->status());

        $other = $this->post('/client-accounts?client_id=1', $this->payment(['client_id' => 1, 'bill_id' => 1]));
        $this->assertSame(404, $other->status(), 'a client of another branch is not even found');
        $this->assertSame(2, DB::table('payments')->count());
    }
}
