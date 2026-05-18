<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('plan_pricing')->where('slug', 'free')->update(['players_limit' => 44]);
    }

    public function down(): void
    {
        DB::table('plan_pricing')->where('slug', 'free')->update(['players_limit' => 20]);
    }
};
