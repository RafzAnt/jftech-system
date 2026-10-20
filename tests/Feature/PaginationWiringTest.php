<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Long lists show 20 rows at a time with page buttons (the script at the end of public/js/jftech-main.js). The behaviour itself runs in
 * the browser; these tests keep it wired to every page, and keep the login history whole.
 */
class PaginationWiringTest extends TestCase
{
    public function test_every_page_with_a_data_table_loads_the_script_that_pages_it(): void
    {
        $missing = [];
        foreach (glob(resource_path('views/pages/*.blade.php')) as $view) {
            $source = file_get_contents($view);
            if (str_contains($source, 'class="data-table') && ! str_contains($source, 'jftech-main.js')) {
                $missing[] = basename($view);
            }
        }

        $this->assertSame([], $missing, 'these pages have a data table but never load jftech-main.js, so their lists would not be paged');
    }

    public function test_the_login_history_is_never_paged(): void
    {
        $source = file_get_contents(resource_path('views/pages/login_history.blade.php'));

        $this->assertMatchesRegularExpression('/<table class="data-table"[^>]*\bdata-no-pagination\b/', $source);
    }

    public function test_the_script_and_the_styles_of_the_pager_exist(): void
    {
        $script = file_get_contents(public_path('js/jftech-main.js'));
        $styles = file_get_contents(public_path('css/jftech-theme.css'));

        foreach (['data-no-pagination', 'data-paginate-list', "table.data-table", 'beforeprint'] as $needle) {
            $this->assertStringContainsString($needle, $script, $needle);
        }
        foreach (['.pager {', '.pager-btn.active', '.pager-hidden'] as $needle) {
            $this->assertStringContainsString($needle, $styles, $needle);
        }
    }

    public function test_the_payroll_records_are_paged_in_fives(): void
    {
        $source = file_get_contents(resource_path('views/pages/payroll.blade.php'));

        $this->assertStringContainsString('<div class="payslip-list" data-paginate-list data-page-size="5">', $source);
    }
}
