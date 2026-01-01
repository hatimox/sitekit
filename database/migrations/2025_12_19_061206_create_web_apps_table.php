<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('web_apps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('server_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('source_provider_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('domain');
            $table->json('aliases')->nullable();

            // System user for web apps (all apps use 'sitekit' user)
            $table->string('system_user')->nullable();

            $table->string('public_path')->default('public');

            $table->enum('web_server', ['nginx', 'nginx_apache'])->default('nginx');
            $table->enum('php_version', ['8.5', '8.4', '8.3', '8.2', '8.1'])->default('8.5');

            $table->enum('ssl_status', ['none', 'pending', 'active', 'failed'])->default('none');
            $table->timestamp('ssl_expires_at')->nullable();

            $table->enum('status', ['pending', 'creating', 'active', 'failed', 'suspended', 'deleting'])->default('pending');
            $table->text('error_message')->nullable();

            $table->json('settings')->nullable();
            $table->text('environment_variables')->nullable();

            // Git deployment (covered in Plan D)
            $table->string('repository')->nullable();
            $table->string('branch')->default('main');
            $table->text('deploy_script')->nullable();
            $table->json('shared_files')->nullable();
            $table->json('shared_directories')->nullable();
            $table->boolean('auto_deploy')->default(false);
            $table->string('webhook_secret')->nullable();
            $table->text('deploy_private_key')->nullable();
            $table->text('deploy_public_key')->nullable();

            $table->timestamps();

            $table->unique(['server_id', 'domain']);
            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_apps');
    }
};
