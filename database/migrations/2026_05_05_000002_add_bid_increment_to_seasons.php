<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            // Minimum bid increment in BDT. Each new bid must be at least
            // (current_highest_bid + bid_increment). 1000 is a sensible default
            // for tournament-scale auctions in Bangladesh; orgs can raise/lower
            // per season from the season form.
            $table->unsignedInteger('bid_increment')->default(1000)->after('budget_per_team');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('bid_increment');
        });
    }
};
