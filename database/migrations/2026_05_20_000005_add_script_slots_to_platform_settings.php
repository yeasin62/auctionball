<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->longText('head_scripts')->nullable()->after('landing_payment_methods');
            $table->longText('body_start_scripts')->nullable()->after('head_scripts');
            $table->longText('body_end_scripts')->nullable()->after('body_start_scripts');
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['head_scripts', 'body_start_scripts', 'body_end_scripts']);
        });
    }
};
