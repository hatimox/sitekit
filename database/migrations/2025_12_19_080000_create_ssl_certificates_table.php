<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ssl_certificates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('web_app_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('letsencrypt'); // letsencrypt, custom
            $table->string('domain');
            $table->string('status')->default('pending');
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('certificate')->nullable();
            $table->text('private_key')->nullable();
            $table->text('chain')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('last_renewal_attempt')->nullable();
            $table->unsignedInteger('renewal_count')->default(0);
            $table->timestamps();

            $table->unique(['web_app_id', 'domain']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ssl_certificates');
    }
};
