<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Pro is now 10-team capped (was unlimited) at the same 4999 BDT price.
        DB::table('plan_pricing')->where('slug', 'pro')->update([
            'teams_limit' => 10,
            'updated_at'  => now(),
        ]);

        // Enterprise is now 5999 BDT (was 9999) for unlimited everything.
        DB::table('plan_pricing')->where('slug', 'enterprise')->update([
            'price_bdt'  => 5999,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('plan_pricing')->where('slug', 'pro')->update([
            'teams_limit' => 999_999_999,
            'updated_at'  => now(),
        ]);
        DB::table('plan_pricing')->where('slug', 'enterprise')->update([
            'price_bdt'  => 9999,
            'updated_at' => now(),
        ]);
    }
};
