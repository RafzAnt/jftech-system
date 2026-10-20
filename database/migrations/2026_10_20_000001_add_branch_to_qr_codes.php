<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The overtime QR code belongs to a branch: each branch admin makes the one of their branch, on the days that branch approves overtime,
 * and only that branch's employees can use it. The morning and afternoon codes stay shared (no branch).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            if (! Schema::hasColumn('qr_codes', 'branch_name')) {
                $table->string('branch_name', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('qr_codes', function (Blueprint $table) {
            if (Schema::hasColumn('qr_codes', 'branch_name')) {
                $table->dropColumn('branch_name');
            }
        });
    }
};
