<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            // Per-season list of {name, base_price}. Each row in the array is
            // one player category with its default base price (used by public
            // registration when no explicit base_price is supplied).
            $table->json('player_categories')->nullable()->after('registration_form_schema');
        });

        $defaults = json_encode([
            ['name' => 'Elite',   'base_price' => 50000],
            ['name' => 'Regular', 'base_price' => 25000],
            ['name' => 'New',     'base_price' => 10000],
        ]);

        DB::table('seasons')->whereNull('player_categories')->update([
            'player_categories' => $defaults,
        ]);
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('player_categories');
        });
    }
};
