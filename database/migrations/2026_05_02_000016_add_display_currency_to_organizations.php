<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Internal money columns (player base_price, team budgets, bid amounts) are stored in BDT.
            // display_currency flips how those amounts are presented in the UI / PDFs / emails.
            $table->string('display_currency', 8)->default('BDT')->after('plan');

            // Conversion rate org admin sets manually. Default 110 ≈ 2026 sandbox rate.
            // We deliberately do NOT auto-fetch live rates — keeps the system deterministic,
            // and a federation running USD can lock in a quarterly rate themselves.
            $table->unsignedSmallInteger('bdt_per_usd')->default(110)->after('display_currency');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['display_currency', 'bdt_per_usd']);
        });
    }
};
