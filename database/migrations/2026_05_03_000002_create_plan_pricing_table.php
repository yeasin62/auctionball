<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plan_pricing', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 32)->unique();
            $table->unsignedInteger('price_bdt')->default(0);
            $table->unsignedBigInteger('seasons_limit')->default(1);
            $table->unsignedBigInteger('players_limit')->default(20);
            $table->unsignedBigInteger('teams_limit')->default(4);
            $table->boolean('watermark')->default(true);
            $table->boolean('export_csv')->default(false);
            $table->boolean('export_pdf')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // 999_999_999 = "unlimited" sentinel (still a real bigint, no nullable mess).
        $unlimited = 999_999_999;
        $now = now();

        DB::table('plan_pricing')->insert([
            ['slug' => 'free',       'price_bdt' => 0,    'seasons_limit' => 1,          'players_limit' => 20,         'teams_limit' => 4,          'watermark' => true,  'export_csv' => false, 'export_pdf' => false, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'starter',    'price_bdt' => 1999, 'seasons_limit' => 3,          'players_limit' => 100,        'teams_limit' => 6,          'watermark' => false, 'export_csv' => true,  'export_pdf' => false, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'pro',        'price_bdt' => 4999, 'seasons_limit' => $unlimited, 'players_limit' => $unlimited, 'teams_limit' => $unlimited, 'watermark' => false, 'export_csv' => true,  'export_pdf' => true,  'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'enterprise', 'price_bdt' => 9999, 'seasons_limit' => $unlimited, 'players_limit' => $unlimited, 'teams_limit' => $unlimited, 'watermark' => false, 'export_csv' => true,  'export_pdf' => true,  'sort_order' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_pricing');
    }
};
