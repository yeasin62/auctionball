<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('visitor_events', function (Blueprint $t) {
            // Captured per event so we can attribute the SESSION's traffic
            // source to the very first event recorded for it. UTM tags drive
            // campaign attribution; raw referrer feeds search / social /
            // referral classification.
            $t->string('referrer', 512)->nullable()->after('path');
            $t->string('utm_source', 100)->nullable()->after('referrer')->index();
            $t->string('utm_medium', 100)->nullable()->after('utm_source');
            $t->string('utm_campaign', 100)->nullable()->after('utm_medium');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_events', function (Blueprint $t) {
            $t->dropColumn(['referrer', 'utm_source', 'utm_medium', 'utm_campaign']);
        });
    }
};
