<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('backup_storage_driver')->nullable()->after('notification_settings'); // local, s3, r2
            $table->string('backup_s3_endpoint')->nullable()->after('backup_storage_driver');
            $table->string('backup_s3_region')->nullable()->after('backup_s3_endpoint');
            $table->string('backup_s3_bucket')->nullable()->after('backup_s3_region');
            $table->text('backup_s3_key')->nullable()->after('backup_s3_bucket'); // encrypted
            $table->text('backup_s3_secret')->nullable()->after('backup_s3_key'); // encrypted
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn([
                'backup_storage_driver',
                'backup_s3_endpoint',
                'backup_s3_region',
                'backup_s3_bucket',
                'backup_s3_key',
                'backup_s3_secret',
            ]);
        });
    }
};
