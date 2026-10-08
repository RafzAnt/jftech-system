<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_plan_change_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('client_id');
            $table->unsignedBigInteger('previous_internet_plan_id')->nullable();
            $table->string('previous_plan_name', 100)->nullable();
            $table->decimal('previous_monthly_rate', 10, 2)->default(0);
            $table->unsignedBigInteger('new_internet_plan_id')->nullable();
            $table->string('new_plan_name', 100);
            $table->decimal('new_monthly_rate', 10, 2)->default(0);
            $table->string('reason', 1000)->nullable();
            $table->unsignedInteger('changed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_plan_change_histories');
    }
};
