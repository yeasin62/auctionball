<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            // Vanity host — e.g. "bpl-cup.example.com". Empty for the default *.auctionball.com.
            // Unique: two orgs can't both claim the same host.
            $table->string('custom_domain', 255)->nullable()->unique()->after('slug');

            // Random token the org admin must publish as a TXT record on their DNS to prove ownership.
            $table->string('custom_domain_verification_token', 64)->nullable()->after('custom_domain');

            // Stamped when DNS verification last passed. Null means unverified — middleware
            // refuses to bind that host to the org until verified.
            $table->timestamp('custom_domain_verified_at')->nullable()->after('custom_domain_verification_token');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn(['custom_domain', 'custom_domain_verification_token', 'custom_domain_verified_at']);
        });
    }
};
