<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('internet_plans', function (Blueprint $table) {
            $table->id(); $table->string('plan_name')->unique(); $table->unsignedInteger('speed_mbps');
            $table->decimal('monthly_price', 10, 2); $table->decimal('installation_fee', 10, 2)->default(0);
            $table->text('description')->nullable(); $table->string('status', 10)->default('active'); $table->timestamps();
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('internet_plan_id')->nullable()->after('wifi_plan')->constrained('internet_plans')->nullOnDelete();
            $table->decimal('installation_fee', 10, 2)->default(0)->after('monthly_rate');
        });
    }
    public function down(): void { Schema::table('clients', function (Blueprint $table) { $table->dropConstrainedForeignId('internet_plan_id'); $table->dropColumn('installation_fee'); }); Schema::dropIfExists('internet_plans'); }
};
