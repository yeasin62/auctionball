<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->foreignId('player_id')->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamp('placed_at')->useCurrent();
            $table->timestamps();

            $table->index(['organization_id', 'season_id', 'player_id']);
            $table->index(['team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
    }
};
