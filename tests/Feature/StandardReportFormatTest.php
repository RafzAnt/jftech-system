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
        '/print-payments' => 'Payment Collection Report',
        '/print-bills' => 'Billing Report',
        '/print-clients' => 'Client Report',
        '/print-employees' => 'Employee Report',
        '/print-inventory' => 'Inventory Report',
        '/print-attendance' => 'Attendance Report',
        '/print-monthly-reports' => 'Profit and Loss Report',
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
            $this->assertStringContainsString('function fitOnePage()', $html, "{$url} is not scaled to one page when it is long");
        }
        $this->assertStringContainsString('zoom: var(--fit', file_get_contents(public_path('css/print_report.css')));
    }

    public function test_the_totals_are_at_the_bottom_of_a_report_not_in_a_box_on_top(): void
    {
        $this->signIn(1);

        foreach (self::REPORTS as $url => $title) {
            if ($title === 'Payslip') {
                continue;
            }
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('<div class="summary">', $html, "{$url} still has the box of figures on top");
            $totals = strpos($html, '<div class="section-heading">Totals</div>');
            $this->assertNotFalse($totals, "{$url} has no Totals");
            $this->assertLessThan(strpos($html, '<section class="signatories">'), $totals, "{$url}: the totals come before the signatures");
            $this->assertGreaterThan(strpos($html, '<div class="report-info">'), $totals, "{$url}: the totals are below the report, not on top");
        }

        // The four figures that were in the box on top of the business report are now the totals at its bottom.
        $business = $this->get('/print-monthly-reports')->getContent();
        $totals = strpos($business, '<div class="section-heading">Totals</div>');
        foreach (['Total Billed', 'Payments Received', 'Outstanding Balance', 'Net Payroll'] as $figure) {
            $this->assertNotFalse(strpos($business, $figure, $totals), "{$figure} is listed in the totals at the bottom");
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

    public function test_the_summary_tables_add_up_to_a_total_row_and_list_no_records(): void
    {
        $this->signIn(1);

        $clients = $this->get('/print-clients')->assertOk()->getContent();
        $this->assertStringContainsString('By Plan', $clients);
        $this->assertMatchesRegularExpression('~<td>TOTAL</td>\s*<td class="num">3</td>~', $clients);
        $this->assertStringNotContainsString('<th class="no">No.</th>', $clients, 'no record-by-record list');
        $this->assertStringNotContainsString('Ana Reyes', $clients, 'a summary names no client');
        $this->assertStringNotContainsString('JFT-1', $clients);

        $payments = $this->get('/print-payments')->assertOk()->getContent();
        $this->assertStringContainsString('By Payment Method', $payments);
        $this->assertMatchesRegularExpression('~<td>TOTAL COLLECTED</td>\s*<td class="num">2</td>\s*<td class="num">₱3,000.00</td>\s*<td class="num">100.0%</td>~', $payments);
        $this->assertStringContainsString('₱1,500.00', $payments, 'the average payment');
        $this->assertStringNotContainsString('Ben Cruz', $payments);
        $this->assertStringNotContainsString('<th class="no">No.</th>', $payments);
    }

    public function test_the_long_breakdowns_are_not_on_the_printed_reports(): void
    {
        $this->signIn(1);

        // A printed report is one short page: no table per date, month, branch or barangay.
        foreach (self::REPORTS as $url => $title) {
            $html = $this->get($url)->assertOk()->getContent();
            foreach (['By Date', 'By Month', 'By Branch', 'By Barangay', 'By Billing Month', 'Daily Breakdown', 'Monthly Breakdown'] as $long) {
                $this->assertStringNotContainsString($long, $html, "{$url} still prints {$long}");
            }
        }
    }

    public function test_the_employee_report_groups_by_position_when_no_employee_has_a_department(): void
    {
        $this->signIn(1);

        $html = $this->get('/print-employees')->assertOk()->getContent();
        $this->assertStringContainsString('By Position', $html);
        $this->assertStringNotContainsString('By Department', $html);
        $this->assertStringNotContainsString('Not specified', $html);
    }

    public function test_every_list_report_is_a_summary_without_a_record_list(): void
    {
        $this->signIn(1);

        foreach (['/print-payments', '/print-bills', '/print-clients', '/print-employees', '/print-inventory', '/print-attendance', '/print-reports'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('<th class="no">No.</th>', $html, "{$url} still lists records");
            $this->assertStringContainsString('<div class="section-heading">', $html, $url);
        }
        $this->assertStringContainsString('By Record Type', $this->get('/print-reports')->getContent());
    }

    public function test_a_plan_name_with_an_ampersand_is_not_escaped_twice(): void
    {
        DB::table('clients')->where('id', 1)->update(['wifi_plan' => 'Home & Office']);
        $this->signIn(1);

        $html = $this->get('/print-clients')->assertOk()->getContent();

        $this->assertStringContainsString('Home &amp; Office', $html);
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
