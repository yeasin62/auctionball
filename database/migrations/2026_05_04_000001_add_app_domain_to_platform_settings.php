<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->string('app_domain', 100)->default('auctionball.com')->after('id');
        });

        DB::table('platform_settings')->update(['app_domain' => 'auctionball.com']);
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn('app_domain');
        });
    }
};
