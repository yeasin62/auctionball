<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('photo_url')->nullable();
            $table->string('category')->default('Regular');           // Elite | Regular | New
            $table->string('player_type')->default('New');            // Old | New
            $table->unsignedBigInteger('base_price')->default(0);
            $table->boolean('is_old_player')->default(false);
            $table->string('auction_status')->default('queue');       // queue | live | sold | unsold
            $table->unsignedBigInteger('sold_price')->nullable();
            $table->foreignId('team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('jersey_no', 10)->nullable();
            $table->string('batting_style')->nullable();
            $table->string('bowling_style')->nullable();
            $table->string('profession')->nullable();
            $table->string('registration_txn_id')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'season_id', 'auction_status']);
            $table->index(['organization_id', 'season_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
