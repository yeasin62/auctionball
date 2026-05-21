<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_settings', 'openai_api_key')) {
                $table->text('openai_api_key')->nullable()->after('body_end_scripts');
            }
            if (! Schema::hasColumn('platform_settings', 'openai_model')) {
                $table->string('openai_model', 80)->nullable()->after('openai_api_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['openai_api_key', 'openai_model']);
        });
    }
};
