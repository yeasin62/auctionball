<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            // Independent USD-side bid step. The org admin sets this directly in
            // USD units — it is NOT a conversion of `bid_increment`. When the
            // org's display_currency=USD this value is used; for BDT we keep the
            // existing `bid_increment` column. The two values are unrelated, so
            // toggling display currency never silently rescales steps.
            $table->unsignedInteger('bid_increment_usd')->default(10)->after('bid_increment');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('bid_increment_usd');
        });
    }
};
