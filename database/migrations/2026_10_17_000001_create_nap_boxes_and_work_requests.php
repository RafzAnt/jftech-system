<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Field work from the employee app: NAP boxes and their ports, installation/repair requests with the
 * materials reported at completion, the sign-in tokens of the app, and the extra details an inventory
 * request carries (item out or return).
 *
 * Existing JFTECH tables use signed INT primary keys, so the columns that point at them are plain integers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('nap_boxes')) {
            Schema::create('nap_boxes', function (Blueprint $table) {
                $table->id();
                $table->string('name', 50);
                $table->string('branch_name', 100);
                $table->string('barangay_name', 100);
                $table->string('location', 255)->nullable();
                $table->unsignedSmallInteger('total_ports');
                $table->string('status', 20)->default('active');
                $table->boolean('archived')->default(false);
                $table->timestamps();
                $table->unique(['branch_name', 'name'], 'nap_boxes_branch_name_unique');
                $table->index(['branch_name', 'barangay_name'], 'nap_boxes_branch_barangay_index');
            });
        }

        if (! Schema::hasTable('nap_ports')) {
            Schema::create('nap_ports', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('nap_box_id');
                $table->unsignedSmallInteger('port_number');
                $table->string('status', 20)->default('available');
                $table->integer('client_id')->nullable();
                $table->timestamp('occupied_at')->nullable();
                $table->timestamps();
                $table->unique(['nap_box_id', 'port_number'], 'nap_ports_box_number_unique');
                $table->foreign('nap_box_id')->references('id')->on('nap_boxes')->cascadeOnDelete();
                $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('work_requests')) {
            Schema::create('work_requests', function (Blueprint $table) {
                $table->id();
                $table->string('request_code', 30)->unique();
                $table->string('branch_name', 100);
                $table->integer('employee_id');
                $table->string('work_type', 20);
                $table->string('status', 30)->default('pending_approval');
                $table->integer('client_id')->nullable();
                $table->string('new_client_name', 100)->nullable();
                $table->string('new_client_phone', 20)->nullable();
                $table->text('new_client_address')->nullable();
                $table->string('barangay_name', 100)->nullable();
                $table->text('location')->nullable();
                $table->text('problem_details')->nullable();
                $table->unsignedBigInteger('nap_box_id')->nullable();
                $table->unsignedBigInteger('nap_port_id')->nullable();
                $table->unsignedBigInteger('actual_nap_box_id')->nullable();
                $table->unsignedBigInteger('actual_nap_port_id')->nullable();
                $table->text('completion_notes')->nullable();
                $table->text('admin_remarks')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->integer('reviewed_by')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->integer('confirmed_by')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();
                $table->index(['branch_name', 'status'], 'work_requests_branch_status_index');
                $table->index('employee_id', 'work_requests_employee_index');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
                $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
                $table->foreign('nap_box_id')->references('id')->on('nap_boxes')->nullOnDelete();
                $table->foreign('nap_port_id')->references('id')->on('nap_ports')->nullOnDelete();
                $table->foreign('actual_nap_box_id')->references('id')->on('nap_boxes')->nullOnDelete();
                $table->foreign('actual_nap_port_id')->references('id')->on('nap_ports')->nullOnDelete();
                $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('confirmed_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('work_request_materials')) {
            Schema::create('work_request_materials', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('work_request_id');
                $table->integer('inventory_id')->nullable();
                $table->string('item_name', 100);
                $table->unsignedInteger('used_quantity')->default(0);
                $table->unsignedInteger('returned_quantity')->default(0);
                $table->unsignedInteger('damaged_quantity')->default(0);
                $table->timestamps();
                $table->foreign('work_request_id')->references('id')->on('work_requests')->cascadeOnDelete();
                $table->foreign('inventory_id')->references('id')->on('inventory')->nullOnDelete();
            });
        }

        // The app signs in once and then sends this token; only a hash of it is stored.
        if (! Schema::hasTable('employee_api_tokens')) {
            Schema::create('employee_api_tokens', function (Blueprint $table) {
                $table->id();
                $table->integer('employee_id');
                $table->integer('login_history_id')->nullable();
                $table->char('token_hash', 64)->unique();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->index('employee_id', 'employee_api_tokens_employee_index');
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            });
        }

        Schema::table('inventory_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('inventory_requests', 'request_type')) {
                $table->string('request_type', 10)->default('out');
            }
            if (! Schema::hasColumn('inventory_requests', 'item_condition')) {
                $table->string('item_condition', 30)->nullable();
            }
            if (! Schema::hasColumn('inventory_requests', 'area_used')) {
                $table->string('area_used', 150)->nullable();
            }
            if (! Schema::hasColumn('inventory_requests', 'person_in_charge')) {
                $table->string('person_in_charge', 100)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventory_requests', function (Blueprint $table) {
            foreach (['request_type', 'item_condition', 'area_used', 'person_in_charge'] as $column) {
                if (Schema::hasColumn('inventory_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('employee_api_tokens');
        Schema::dropIfExists('work_request_materials');
        Schema::dropIfExists('work_requests');
        Schema::dropIfExists('nap_ports');
        Schema::dropIfExists('nap_boxes');
    }
};
