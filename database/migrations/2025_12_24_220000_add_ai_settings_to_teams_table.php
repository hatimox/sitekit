<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->boolean('ai_enabled')->default(true)->after('personal_team');
            $table->string('ai_provider')->nullable()->after('ai_enabled'); // openai, anthropic, gemini
            $table->text('ai_openai_key')->nullable()->after('ai_provider');
            $table->text('ai_anthropic_key')->nullable()->after('ai_openai_key');
            $table->text('ai_gemini_key')->nullable()->after('ai_anthropic_key');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'ai_enabled',
                'ai_provider',
                'ai_openai_key',
                'ai_anthropic_key',
                'ai_gemini_key',
            ]);
        });
    }
};
