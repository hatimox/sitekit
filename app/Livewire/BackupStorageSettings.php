<?php

namespace App\Livewire;

use App\Models\Team;
use Livewire\Component;

class BackupStorageSettings extends Component
{
    public $team;
    public $backup_storage_driver = 'local';
    public $backup_s3_endpoint = '';
    public $backup_s3_region = 'us-east-1';
    public $backup_s3_bucket = '';
    public $backup_s3_key = '';
    public $backup_s3_secret = '';

    public function mount($team)
    {
        $this->team = $team;
        $this->backup_storage_driver = $team->backup_storage_driver ?? 'local';
        $this->backup_s3_endpoint = $team->backup_s3_endpoint ?? '';
        $this->backup_s3_region = $team->backup_s3_region ?? 'us-east-1';
        $this->backup_s3_bucket = $team->backup_s3_bucket ?? '';
        // Don't populate secrets for security - only show if they exist
        $this->backup_s3_key = '';
        $this->backup_s3_secret = '';
    }

    public function updateBackupStorageSettings()
    {
        $rules = [
            'backup_storage_driver' => ['required', 'in:local,s3,r2'],
        ];

        if ($this->backup_storage_driver !== 'local') {
            $rules = array_merge($rules, [
                'backup_s3_bucket' => ['required', 'string', 'max:255'],
                'backup_s3_region' => ['required', 'string', 'max:100'],
            ]);

            // Only require secrets if not already set
            if (!$this->team->backup_s3_key || $this->backup_s3_key) {
                $rules['backup_s3_key'] = ['required', 'string'];
            }
            if (!$this->team->backup_s3_secret || $this->backup_s3_secret) {
                $rules['backup_s3_secret'] = ['required', 'string'];
            }

            // R2 requires endpoint
            if ($this->backup_storage_driver === 'r2') {
                $rules['backup_s3_endpoint'] = ['required', 'url'];
            } else {
                $rules['backup_s3_endpoint'] = ['nullable', 'url'];
            }
        }

        $this->validate($rules);

        $data = [
            'backup_storage_driver' => $this->backup_storage_driver,
        ];

        if ($this->backup_storage_driver !== 'local') {
            $data['backup_s3_endpoint'] = $this->backup_s3_endpoint ?: null;
            $data['backup_s3_region'] = $this->backup_s3_region;
            $data['backup_s3_bucket'] = $this->backup_s3_bucket;

            // Only update secrets if provided
            if ($this->backup_s3_key) {
                $data['backup_s3_key'] = $this->backup_s3_key;
            }
            if ($this->backup_s3_secret) {
                $data['backup_s3_secret'] = $this->backup_s3_secret;
            }
        } else {
            // Clear cloud settings when switching to local
            $data['backup_s3_endpoint'] = null;
            $data['backup_s3_region'] = null;
            $data['backup_s3_bucket'] = null;
            $data['backup_s3_key'] = null;
            $data['backup_s3_secret'] = null;
        }

        $this->team->update($data);
        $this->team->refresh();

        // Clear sensitive fields after save
        $this->backup_s3_key = '';
        $this->backup_s3_secret = '';

        $this->dispatch('saved');
    }

    public function testConnection()
    {
        if ($this->backup_storage_driver === 'local') {
            $this->addError('backup_storage_driver', 'Cannot test local storage connection.');
            return;
        }

        try {
            $disk = $this->team->getBackupDisk();

            if (!$disk) {
                $this->addError('backup_storage_driver', 'Cloud storage not properly configured.');
                return;
            }

            // Try to list files in the bucket
            $disk->files('/');

            session()->flash('storage_test_success', 'Connection successful! Your cloud storage is properly configured.');
        } catch (\Exception $e) {
            $this->addError('backup_storage_driver', 'Connection failed: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.backup-storage-settings');
    }
}
