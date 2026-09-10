<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('internet_plans', function (Blueprint $table) {
            if (! Schema::hasColumn('internet_plans', 'scheduled_monthly_price')) {
                $table->decimal('scheduled_monthly_price', 10, 2)->nullable()->after('monthly_price');
            }
            if (! Schema::hasColumn('internet_plans', 'scheduled_installation_fee')) {
                $table->decimal('scheduled_installation_fee', 10, 2)->nullable()->after('installation_fee');
            }
            if (! Schema::hasColumn('internet_plans', 'price_effective_on')) {
                $table->date('price_effective_on')->nullable()->after('scheduled_installation_fee');
            }
        });

        if (! Schema::hasTable('internet_plan_price_histories')) Schema::create('internet_plan_price_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('internet_plan_id')->constrained()->cascadeOnDelete();
            $table->decimal('previous_monthly_price', 10, 2)->nullable();
            $table->decimal('new_monthly_price', 10, 2)->nullable();
            $table->decimal('previous_installation_fee', 10, 2)->nullable();
            $table->decimal('new_installation_fee', 10, 2)->nullable();
            $table->string('change_type', 20);
            $table->date('effective_on')->nullable();
            $table->text('reason')->nullable();
            // The legacy users table uses an unsigned INT primary key, while
            // Laravel's foreignId() creates an unsigned BIGINT.
            $table->integer('changed_by')->nullable();
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });
        else {
            // A failed MySQL migration can leave the table behind after the
            // table definition was created but before its last FK was added.
            Schema::table('internet_plan_price_histories', function (Blueprint $table) {
                $table->integer('changed_by')->nullable()->change();
                $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('internet_plan_price_histories');
        Schema::table('internet_plans', function (Blueprint $table) {
            $table->dropColumn(['scheduled_monthly_price', 'scheduled_installation_fee', 'price_effective_on']);
        });
    }
};
