<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('business_type', 30)->nullable()->after('name');
            $table->text('description')->nullable()->after('business_type');
            $table->string('logo_path')->nullable()->after('description');
            $table->unsignedTinyInteger('onboarding_step')->default(1)->after('status'); // furthest step reached (1-5)
            $table->timestamp('onboarding_completed_at')->nullable()->after('onboarding_step');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn(['business_type', 'description', 'logo_path', 'onboarding_step', 'onboarding_completed_at']);
        });
    }
};
