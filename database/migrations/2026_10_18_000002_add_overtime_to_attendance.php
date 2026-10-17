<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Overtime is a third attendance session: the employee scans an overtime QR code (or the admin enters it by hand) when the
 * extra work starts and again when it ends, and the minutes in between go to the payroll. The overtime QR has its own
 * time window (17:00 to 22:00 unless the settings say otherwise).
 */
return new class extends Migration
{
    private const SETTINGS = [
        ['qr_overtime_start', '17:00:00', 'Overtime QR Start Time'],
        ['qr_overtime_end', '22:00:00', 'Overtime QR End Time'],
    ];

    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance', 'overtime_time_in')) {
                $table->time('overtime_time_in')->nullable();
            }
            if (! Schema::hasColumn('attendance', 'overtime_time_out')) {
                $table->time('overtime_time_out')->nullable();
            }
            if (! Schema::hasColumn('attendance', 'overtime_minutes')) {
                $table->unsignedInteger('overtime_minutes')->default(0);
            }
        });

        // MySQL keeps the QR type as an ENUM of the two sessions; the other databases use a plain string.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE qr_codes MODIFY qr_type ENUM('morning', 'afternoon', 'overtime') NOT NULL");
        }

        foreach (self::SETTINGS as [$key, $value, $label]) {
            if (! DB::table('system_settings')->where('setting_key', $key)->exists()) {
                DB::table('system_settings')->insert(['setting_key' => $key, 'setting_value' => $value, 'setting_label' => $label]);
            }
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('setting_key', array_column(self::SETTINGS, 0))->delete();

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::table('qr_codes')->where('qr_type', 'overtime')->delete();
            DB::statement("ALTER TABLE qr_codes MODIFY qr_type ENUM('morning', 'afternoon') NOT NULL");
        }

        Schema::table('attendance', function (Blueprint $table) {
            foreach (['overtime_time_in', 'overtime_time_out', 'overtime_minutes'] as $column) {
                if (Schema::hasColumn('attendance', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
