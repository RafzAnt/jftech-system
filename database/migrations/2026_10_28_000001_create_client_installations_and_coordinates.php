<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Installing a client from the employee app:
 *   * NAP boxes and clients get Google coordinates (and a client the extra details of the installation: mode of payment, IP
 *     address, Facebook account);
 *   * client_installations is the report an employee files after installing (client details, NAP box and port, coordinates,
 *     items brought back); the branch admin edits and confirms it and only then the client exists;
 *   * client_installation_returns are the items brought back, accepted into stock when the admin confirms;
 *   * an inventory request remembers the report it was used for (installation_id).
 *
 * Existing JFTECH tables use signed INT primary keys, so the columns that point at them are plain integers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nap_boxes', function (Blueprint $table) {
            if (! Schema::hasColumn('nap_boxes', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
            }
        });

        Schema::table('clients', function (Blueprint $table) {
            if (! Schema::hasColumn('clients', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
            }
            if (! Schema::hasColumn('clients', 'payment_mode')) {
                $table->string('payment_mode', 20)->nullable();
            }
            if (! Schema::hasColumn('clients', 'ip_address')) {
                $table->string('ip_address', 45)->nullable();
            }
            if (! Schema::hasColumn('clients', 'facebook_account')) {
                $table->string('facebook_account', 150)->nullable();
            }
        });

        if (! Schema::hasTable('client_installations')) {
            Schema::create('client_installations', function (Blueprint $table) {
                $table->id();
                $table->string('branch_name', 100);
                $table->integer('employee_id');
                $table->string('status', 20)->default('submitted');
                $table->string('client_name', 100);
                $table->string('contact_number', 20);
                $table->string('email', 100)->nullable();
                $table->string('barangay_name', 100);
                $table->string('exact_address', 255);
                $table->unsignedBigInteger('internet_plan_id')->nullable();
                $table->string('payment_mode', 20);
                $table->string('ip_address', 45)->nullable();
                $table->string('facebook_account', 150)->nullable();
                $table->date('installation_date');
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->unsignedBigInteger('nap_box_id')->nullable();
                $table->unsignedBigInteger('nap_port_id')->nullable();
                $table->text('remarks')->nullable();
                $table->text('admin_remarks')->nullable();
                $table->integer('client_id')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->integer('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['branch_name', 'status'], 'client_installations_branch_status_index');
                $table->index('employee_id', 'client_installations_employee_index');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('internet_plan_id')->references('id')->on('internet_plans')->nullOnDelete();
                $table->foreign('nap_box_id')->references('id')->on('nap_boxes')->nullOnDelete();
                $table->foreign('nap_port_id')->references('id')->on('nap_ports')->nullOnDelete();
                $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
                $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('client_installation_returns')) {
            Schema::create('client_installation_returns', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('installation_id');
                $table->integer('inventory_id')->nullable();
                $table->string('item_name', 100);
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('accepted_quantity')->nullable();
                $table->string('item_condition', 30)->nullable();
                $table->timestamps();
                $table->index('installation_id', 'client_installation_returns_installation_index');
                $table->foreign('installation_id')->references('id')->on('client_installations')->cascadeOnDelete();
                $table->foreign('inventory_id')->references('id')->on('inventory')->nullOnDelete();
            });
        }

        Schema::table('inventory_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_requests', 'installation_id')) {
                $table->unsignedBigInteger('installation_id')->nullable();
                $table->index('installation_id', 'inventory_requests_installation_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_requests', function (Blueprint $table) {
            if (Schema::hasColumn('inventory_requests', 'installation_id')) {
                $table->dropIndex('inventory_requests_installation_index');
                $table->dropColumn('installation_id');
            }
        });

        Schema::dropIfExists('client_installation_returns');
        Schema::dropIfExists('client_installations');

        Schema::table('clients', function (Blueprint $table) {
            foreach (['latitude', 'longitude', 'payment_mode', 'ip_address', 'facebook_account'] as $column) {
                if (Schema::hasColumn('clients', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('nap_boxes', function (Blueprint $table) {
            foreach (['latitude', 'longitude'] as $column) {
                if (Schema::hasColumn('nap_boxes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
