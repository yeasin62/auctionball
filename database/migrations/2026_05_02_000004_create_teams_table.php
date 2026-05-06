<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('season_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('short_code', 10)->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('logo_url')->nullable();
            $table->unsignedBigInteger('initial_budget')->default(0);
            $table->unsignedBigInteger('remaining_budget')->default(0);
            $table->string('device_token', 64)->nullable()->unique();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'season_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};
