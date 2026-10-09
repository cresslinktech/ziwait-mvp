<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->string('cover_path')->nullable()->after('logo_path');
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('settings');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->dropColumn('photo_path');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('cover_path');
        });
    }
};