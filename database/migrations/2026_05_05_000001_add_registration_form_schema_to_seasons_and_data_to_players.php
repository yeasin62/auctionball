<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            // Org-defined custom registration fields. Empty array = use only the
            // built-in fields (name, category, position, jersey, etc).
            $table->json('registration_form_schema')->nullable()->after('registration_instructions');
        });

        Schema::table('players', function (Blueprint $table) {
            // Responses to custom fields. JSON keyed by field id from the schema.
            $table->json('registration_data')->nullable()->after('registration_txn_id');
        });
    }

    public function down(): void
    {
        Schema::table('seasons', function (Blueprint $table) {
            $table->dropColumn('registration_form_schema');
        });
        Schema::table('players', function (Blueprint $table) {
            $table->dropColumn('registration_data');
        });
    }
};
