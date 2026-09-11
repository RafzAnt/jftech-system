<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'company_address'],
            ['setting_value' => 'Del Carmen Sur, Balilihan, Bohol', 'setting_label' => 'Company Address']
        );

        DB::table('system_settings')->updateOrInsert(
            ['setting_key' => 'company_email'],
            ['setting_value' => 'jftechitsolutions@gmail.com', 'setting_label' => 'Email Address']
        );
    }

    public function down(): void
    {
        DB::table('system_settings')->where('setting_key', 'company_address')->update(['setting_value' => 'Balilihan, Bohol, Philippines']);
        DB::table('system_settings')->where('setting_key', 'company_email')->update(['setting_value' => 'info@jftech.com']);
    }
};
