<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('platform_settings', 'ai_provider')) {
                $table->string('ai_provider', 32)->default('auto')->after('openai_model');
            }
            if (! Schema::hasColumn('platform_settings', 'anthropic_api_key')) {
                $table->text('anthropic_api_key')->nullable()->after('ai_provider');
            }
            if (! Schema::hasColumn('platform_settings', 'anthropic_model')) {
                $table->string('anthropic_model', 100)->nullable()->after('anthropic_api_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('platform_settings', function (Blueprint $table) {
            $table->dropColumn(['ai_provider', 'anthropic_api_key', 'anthropic_model']);
        });
    }
};
