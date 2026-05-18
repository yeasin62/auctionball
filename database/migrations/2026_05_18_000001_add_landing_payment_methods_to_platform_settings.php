<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->json('landing_payment_methods')->nullable()->after('manual_review_hours');
        });

        // Only the methods we can actually accept today are on by default.
        // bKash is wired up (manual submission flow); the rest are marketing
        // placeholders the admin can re-enable once integrated.
        DB::table('platform_settings')->update([
            'landing_payment_methods' => json_encode(['bkash']),
        ]);
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn('landing_payment_methods');
        });
    }
};
