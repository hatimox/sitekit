<?php

namespace App\Services;

use App\Models\DatabaseBackup;
use App\Models\Team;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BackupStorageService
{
    public function uploadToCloud(DatabaseBackup $backup): bool
    {
        $team = $backup->team;

        if (!$team->hasCloudBackupStorage()) {
            return false;
        }

        $disk = $team->getBackupDisk();

        if (!$disk) {
            return false;
        }

        try {
            // Get the backup file from the server via agent
            $fileContent = $this->downloadBackupFromServer($backup);

            if (!$fileContent) {
                Log::warning("Failed to download backup from server", [
                    'backup_id' => $backup->id,
                ]);
                return false;
            }

            // Upload to cloud storage
            $cloudPath = $this->getCloudPath($backup);
            $disk->put($cloudPath, $fileContent);

            // Update backup record
            $backup->update([
                'cloud_path' => $cloudPath,
                'cloud_storage_driver' => $team->backup_storage_driver,
            ]);

            Log::info("Backup uploaded to cloud storage", [
                'backup_id' => $backup->id,
                'cloud_path' => $cloudPath,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to upload backup to cloud storage", [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function downloadFromCloud(DatabaseBackup $backup): ?string
    {
        $team = $backup->team;

        if (!$backup->cloud_path || !$team->hasCloudBackupStorage()) {
            return null;
        }

        $disk = $team->getBackupDisk();

        if (!$disk || !$disk->exists($backup->cloud_path)) {
            return null;
        }

        return $disk->get($backup->cloud_path);
    }

    public function deleteFromCloud(DatabaseBackup $backup): bool
    {
        $team = $backup->team;

        if (!$backup->cloud_path || !$team->hasCloudBackupStorage()) {
            return false;
        }

        $disk = $team->getBackupDisk();

        if (!$disk) {
            return false;
        }

        try {
            $disk->delete($backup->cloud_path);

            $backup->update([
                'cloud_path' => null,
                'cloud_storage_driver' => null,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error("Failed to delete backup from cloud storage", [
                'backup_id' => $backup->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    protected function getCloudPath(DatabaseBackup $backup): string
    {
        $database = $backup->database;
        $server = $backup->server;

        return sprintf(
            'backups/%s/%s/%s',
            $server->name,
            $database->name,
            $backup->filename
        );
    }

    protected function downloadBackupFromServer(DatabaseBackup $backup): ?string
    {
        $server = $backup->server;

        if (!$backup->path) {
            return null;
        }

        // Create a file read agent job to download the backup
        $job = \App\Models\AgentJob::create([
            'server_id' => $server->id,
            'team_id' => $backup->team_id,
            'type' => 'file_read',
            'payload' => [
                'path' => $backup->path,
                'base64' => true, // Request base64 encoded content for binary files
            ],
            'status' => \App\Models\AgentJob::STATUS_PENDING,
        ]);

        // Wait for job completion (with timeout)
        $timeout = 120; // 2 minutes
        $start = time();

        while (time() - $start < $timeout) {
            $job->refresh();

            if ($job->isCompleted()) {
                $result = $job->result ?? [];
                if (isset($result['content'])) {
                    return base64_decode($result['content']);
                }
                return null;
            }

            if ($job->isFailed()) {
                return null;
            }

            sleep(2);
        }

        return null;
    }

    public function getCloudUrl(DatabaseBackup $backup): ?string
    {
        $team = $backup->team;

        if (!$backup->cloud_path || !$team->hasCloudBackupStorage()) {
            return null;
        }

        $disk = $team->getBackupDisk();

        if (!$disk) {
            return null;
        }

        try {
            // Generate a temporary signed URL valid for 1 hour
            return $disk->temporaryUrl($backup->cloud_path, now()->addHour());
        } catch (\Exception $e) {
            // Some drivers don't support temporary URLs
            return null;
        }
    }
}
