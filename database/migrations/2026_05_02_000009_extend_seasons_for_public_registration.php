<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->boolean('registration_open')->default(false)->after('is_active');
            $table->string('registration_token', 32)->nullable()->unique()->after('registration_open');
            $table->unsignedInteger('registration_fee')->default(0)->after('registration_token');
            $table->text('registration_instructions')->nullable()->after('registration_fee');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn(['registration_open', 'registration_token', 'registration_fee', 'registration_instructions']);
        });
    }
};
