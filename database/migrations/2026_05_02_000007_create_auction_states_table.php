<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('auction_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('current_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->unsignedBigInteger('highest_bid')->default(0);
            $table->foreignId('highest_bidder_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('status')->default('idle');                // idle | running | paused | sold | unsold
            $table->timestamp('timer_end')->nullable();
            $table->timestamp('last_bid_at')->nullable();
            $table->unsignedSmallInteger('timer_duration_seconds')->default(60);
            $table->timestamps();

            $table->unique(['organization_id', 'season_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_states');
    }
};
