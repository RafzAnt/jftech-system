<?php

namespace Tests\Feature;

use App\Support\ReportFormat;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BranchTestData;
use Tests\TestCase;

/**
 * Every printed report has the same standard format: letterhead with the TIN, the title and the dates it covers,
 * the branch, numbered rows with a TOTAL, the Prepared by / Checked by / Approved by signatures and the page footer.
 *
 * Seed: users 1 super admin, 2 Balilihan admin (see BranchTestData).
 */
class StandardReportFormatTest extends TestCase
{
    use BranchTestData;

    /** Every report of the system, with the title it prints. */
    private const REPORTS = [
        '/print-payments' => 'Payment Transactions Report',
        '/print-bills' => 'Billing Report',
        '/print-clients' => 'Client Masterlist Report',
        '/print-employees' => 'Employee Masterlist Report',
        '/print-inventory' => 'Inventory Stock Report',
        '/print-attendance' => 'Attendance Report',
        '/print-monthly-reports' => 'Business Summary Report',
        '/print-payroll?id=1' => 'Payslip',
        '/print-reports' => 'JFTECH Activity Report',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->createBranchSchema();
        $this->seedBranchData();
        DB::connection()->getPdo()->sqliteCreateFunction('GREATEST', fn (...$values) => max($values), -1);
    }

    public function test_every_report_has_the_standard_letterhead_title_and_signatures(): void
    {
        $this->signIn(1);

        foreach (self::REPORTS as $url => $title) {
            $html = $this->get($url)->assertOk()->getContent();
            if ($title !== 'Payslip') {
                $this->assertStringContainsString('Checked by:', $html, $url);
            }

            foreach ([
                'JFTECH IT SOLUTIONS', 'Del Carmen Sur, Balilihan, Bohol', 'TIN: 476-882-950-000',
                '<h1>' . $title . '</h1>', 'Branch:', 'Date generated:', 'Prepared by:', 'Approved by:',
            ] as $needle) {
                $this->assertStringContainsString($needle, $html, "{$url} is missing {$needle}");
            }
            $this->assertStringContainsString('Signature over printed name', $html, $url);
            $this->assertStringContainsString('*** END OF ' . ($title === 'Payslip' ? 'PAYSLIP' : 'REPORT') . ' ***', $html, $url);
            $this->assertStringContainsString('Page " counter(page) " of " counter(pages)', $html, $url);
            $this->assertStringContainsString('css/print_report.css', $html, $url);
        }
    }

    public function test_the_payslip_is_acknowledged_by_the_employee_and_the_other_reports_are_checked(): void
    {
        $this->signIn(1);

        $payslip = $this->get('/print-payroll?id=1')->assertOk()->getContent();
        $this->assertStringContainsString('Received by:', $payslip);
        $this->assertStringContainsString('ELI SANTOS', strtoupper($payslip));
        $this->assertStringNotContainsString('Checked by:', $payslip);

        $this->assertStringContainsString('Jieso B. Danao', $this->get('/print-bills')->getContent());
    }

    public function test_the_super_admin_report_covers_all_branches_and_a_branch_admins_their_own(): void
    {
        $this->signIn(1);
        $this->assertStringContainsString('<strong>All Branches</strong>', $this->get('/print-clients')->getContent());
        $this->assertStringContainsString('<strong>Loon Branch</strong>', $this->get('/print-inventory?branch=Loon')->getContent());
        $this->assertStringContainsString('<div class="name">Super Admin</div>', $this->get('/print-bills')->getContent());

        $this->signIn(2);
        foreach (['/print-clients', '/print-bills', '/print-payments', '/print-inventory?branch=Loon', '/print-monthly-reports?branch=Loon'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringContainsString('<strong>Balilihan Branch</strong>', $html, $url);
            $this->assertStringNotContainsString('Loon Branch', $html, $url);
        }
    }

    public function test_the_prepared_by_signature_is_the_person_who_prints_it(): void
    {
        DB::table('users')->where('id', 2)->update(['full_name' => 'Bea Balilihan']);
        $this->signIn(2);

        $html = $this->get('/print-employees')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~Prepared by:</div>\s*<div class="name">Bea Balilihan</div>~', $html);
        $this->assertStringContainsString('Branch Admin, Balilihan', $html);
        $this->assertStringContainsString('Printed ' . ReportFormat::dateTime(now()), $html);
    }

    public function test_the_dates_are_written_out_as_month_day_year(): void
    {
        $this->signIn(1);

        $this->assertStringContainsString('For the period October 1, 2026 to October 31, 2026', $this->get('/print-payments?from=2026-10-01&to=2026-10-31')->getContent());
        $this->assertStringContainsString('For the period October 1, 2026 to October 31, 2026', $this->get('/print-bills?start=2026-10-01&end=2026-10-31')->getContent());
        $this->assertStringContainsString('For the period October 1, 2026 to October 31, 2026', $this->get('/print-monthly-reports?from=2026-10-01&to=2026-10-31')->getContent());
        $this->assertStringContainsString('For all dates', $this->get('/print-payments')->getContent());
        $this->assertStringContainsString('As of ' . now()->format('F j, Y'), $this->get('/print-clients')->getContent());
    }

    public function test_the_rows_are_numbered_and_end_in_a_total_row(): void
    {
        $this->signIn(1);

        $clients = $this->get('/print-clients')->assertOk()->getContent();
        $this->assertStringContainsString('<th class="no">No.</th>', $clients);
        $this->assertStringContainsString('<td class="no">3</td>', $clients);
        $this->assertStringContainsString('TOTAL (3 clients)', $clients);

        $payments = $this->get('/print-payments')->assertOk()->getContent();
        $this->assertStringContainsString('TOTAL (2 payments)', $payments);
        $this->assertStringContainsString('₱3,000.00', $payments);
    }

    public function test_a_name_with_an_ampersand_is_not_escaped_twice(): void
    {
        DB::table('clients')->where('id', 1)->update(['full_name' => 'Ana & Sons']);
        $this->signIn(1);

        $html = $this->get('/print-clients')->assertOk()->getContent();

        $this->assertStringContainsString('Ana &amp; Sons', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function test_a_name_cannot_break_out_of_the_page_footer_style(): void
    {
        DB::table('users')->where('id', 1)->update(['full_name' => '"; } </style><script>alert(1)</script>']);
        $this->signIn(1);

        $html = $this->get('/print-bills')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertSame(1, substr_count($html, '</style>'));
    }

    public function test_the_helper_words_dates_and_css_text(): void
    {
        $this->assertSame('October 27, 2026', ReportFormat::date('2026-10-27'));
        $this->assertSame('not a date', ReportFormat::date('not a date'));
        $this->assertSame('', ReportFormat::date(null));
        $this->assertSame('October 27, 2026, 3:05 PM', ReportFormat::dateTime('2026-10-27 15:05:00'));

        $this->assertSame('For October 27, 2026', ReportFormat::coverage('2026-10-27', '2026-10-27'));
        $this->assertSame('For the period October 1, 2026 to October 31, 2026', ReportFormat::coverage('2026-10-01', '2026-10-31'));
        $this->assertSame('For the period starting October 1, 2026', ReportFormat::coverage('2026-10-01', ''));
        $this->assertSame('For the period up to October 31, 2026', ReportFormat::coverage(null, '2026-10-31'));
        $this->assertSame('For all dates', ReportFormat::coverage('', ''));

        $this->assertSame('GCash', ReportFormat::paymentMethod('gcash'));
        $this->assertSame('Bank Transfer', ReportFormat::paymentMethod('bank_transfer'));
        $this->assertSame('Cash', ReportFormat::paymentMethod('cash'));

        $this->assertSame('Ana \\22  \\20B1 5', ReportFormat::cssString('Ana " ₱5'));
        $this->assertStringNotContainsString('<', ReportFormat::cssString('</style>'));
    }
}
