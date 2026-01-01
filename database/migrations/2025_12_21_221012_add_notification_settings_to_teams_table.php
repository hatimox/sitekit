<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('slack_webhook_url')->nullable()->after('personal_team');
            $table->string('discord_webhook_url')->nullable()->after('slack_webhook_url');
            $table->json('notification_settings')->nullable()->after('discord_webhook_url');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['slack_webhook_url', 'discord_webhook_url', 'notification_settings']);
        });
    }
};
