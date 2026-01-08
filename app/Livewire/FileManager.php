<?php

namespace App\Livewire;

use App\Models\AgentJob;
use App\Models\WebApp;
use App\Services\FileManager\PathValidator;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class FileManager extends Component
{
    use WithFileUploads;

    public WebApp $webApp;
    public string $currentPath = '';
    public array $files = [];
    public array $breadcrumbs = [];
    public bool $isLoading = true;
    public ?string $activeJobId = null;
    public ?string $activeJobType = null;

    // Editor state
    public bool $showEditor = false;
    public ?string $editingFile = null;
    public ?string $editingFileName = null;
    public string $fileContent = '';
    public string $originalContent = '';
    public bool $isEditorLoading = false;
    public bool $isSaving = false;

    // Upload state
    public bool $showUploadModal = false;
    public $uploadFiles = [];

    // New folder state
    public bool $showNewFolderModal = false;
    public string $newFolderName = '';

    // Delete confirmation state
    public bool $showDeleteModal = false;
    public array $selectedFiles = [];
    public array $filesToDelete = [];

    // Error state
    public ?string $errorMessage = null;

    protected PathValidator $pathValidator;

    public function boot(PathValidator $pathValidator): void
    {
        $this->pathValidator = $pathValidator;
    }

    public function mount(WebApp $webApp): void
    {
        $this->webApp = $webApp;
        $this->currentPath = $webApp->root_path . '/current';
        $this->loadDirectory();
    }

    /**
     * Load directory contents via agent job
     */
    public function loadDirectory(?string $path = null): void
    {
        $path = $path ?? $this->currentPath;

        // Validate path is within allowed scope
        if (!$this->pathValidator->isAllowed($this->webApp, $path)) {
            $this->errorMessage = 'Access denied: You cannot access this directory.';
            $this->isLoading = false;
            return;
        }

        $this->isLoading = true;
        $this->errorMessage = null;
        $this->currentPath = $path;
        $this->updateBreadcrumbs();

        $job = AgentJob::create([
            'server_id' => $this->webApp->server_id,
            'team_id' => $this->webApp->team_id,
            'type' => 'file_list',
            'payload' => [
                'web_app_id' => $this->webApp->id,
                'path' => $path,
                'show_hidden' => false,
            ],
            'priority' => 2,
        ]);

        $this->activeJobId = $job->id;
        $this->activeJobType = 'file_list';
    }

    /**
     * Handle real-time job updates via Echo
     */
    #[On('echo-private:webapp.{webApp.id},job.updated')]
    public function handleJobUpdate(array $data): void
    {
        if ($data['job_id'] !== $this->activeJobId) {
            return;
        }

        if ($data['status'] === 'completed') {
            $this->handleJobCompleted($data);
        } elseif ($data['status'] === 'failed') {
            $this->handleJobFailed($data);
        }
    }

    /**
     * Handle successful job completion
     */
    protected function handleJobCompleted(array $data): void
    {
        $type = $this->activeJobType;

        match ($type) {
            'file_list' => $this->handleFileListComplete($data),
            'file_read' => $this->handleFileReadComplete($data),
            'file_write' => $this->handleFileWriteComplete($data),
            'file_delete' => $this->handleFileDeleteComplete(),
            'file_mkdir' => $this->handleMkdirComplete(),
            default => null,
        };

        $this->activeJobId = null;
        $this->activeJobType = null;
    }

    /**
     * Handle job failure
     */
    protected function handleJobFailed(array $data): void
    {
        $this->isLoading = false;
        $this->isEditorLoading = false;
        $this->isSaving = false;

        $error = $data['error'] ?? 'An error occurred';

        match ($this->activeJobType) {
            'file_list' => $this->errorMessage = "Failed to load directory: {$error}",
            'file_read' => $this->handleFileReadFailed($error),
            'file_write' => Notification::make()->title('Save Failed')->body($error)->danger()->send(),
            'file_delete' => Notification::make()->title('Delete Failed')->body($error)->danger()->send(),
            'file_mkdir' => Notification::make()->title('Create Folder Failed')->body($error)->danger()->send(),
            default => Notification::make()->title('Operation Failed')->body($error)->danger()->send(),
        };

        $this->activeJobId = null;
        $this->activeJobType = null;
    }

    /**
     * Handle file list completion
     */
    protected function handleFileListComplete(array $data): void
    {
        $output = json_decode($data['output'] ?? '{}', true);
        $this->files = $output['files'] ?? [];

        // Sort: directories first, then files, both alphabetically
        usort($this->files, function ($a, $b) {
            if ($a['type'] === 'directory' && $b['type'] !== 'directory') {
                return -1;
            }
            if ($a['type'] !== 'directory' && $b['type'] === 'directory') {
                return 1;
            }
            return strcasecmp($a['name'], $b['name']);
        });

        $this->isLoading = false;
    }

    /**
     * Handle file read completion
     */
    protected function handleFileReadComplete(array $data): void
    {
        $this->fileContent = $data['output'] ?? '';
        $this->originalContent = $this->fileContent;
        $this->isEditorLoading = false;
    }

    /**
     * Handle file read failure
     */
    protected function handleFileReadFailed(string $error): void
    {
        $this->showEditor = false;
        $this->editingFile = null;
        $this->editingFileName = null;
        Notification::make()->title('Failed to open file')->body($error)->danger()->send();
    }

    /**
     * Handle file write completion
     */
    protected function handleFileWriteComplete(array $data): void
    {
        $this->isSaving = false;
        $this->originalContent = $this->fileContent;
        Notification::make()->title('File Saved')->body('Your changes have been saved.')->success()->send();
    }

    /**
     * Handle file delete completion
     */
    protected function handleFileDeleteComplete(): void
    {
        $this->showDeleteModal = false;
        $this->filesToDelete = [];
        $this->selectedFiles = [];
        Notification::make()->title('Deleted')->body('Files have been deleted.')->success()->send();
        $this->loadDirectory();
    }

    /**
     * Handle mkdir completion
     */
    protected function handleMkdirComplete(): void
    {
        $this->showNewFolderModal = false;
        $this->newFolderName = '';
        Notification::make()->title('Folder Created')->success()->send();
        $this->loadDirectory();
    }

    /**
     * Update breadcrumb navigation
     */
    protected function updateBreadcrumbs(): void
    {
        $basePath = $this->webApp->root_path;
        $relativePath = str_replace($basePath, '', $this->currentPath);
        $parts = array_filter(explode('/', $relativePath));

        $this->breadcrumbs = [];
        $buildPath = $basePath;

        // Add root
        $this->breadcrumbs[] = [
            'name' => basename($basePath),
            'path' => $basePath,
        ];

        foreach ($parts as $part) {
            $buildPath .= '/' . $part;
            $this->breadcrumbs[] = [
                'name' => $part,
                'path' => $buildPath,
            ];
        }
    }

    /**
     * Navigate to a subdirectory
     */
    public function navigateTo(string $directory): void
    {
        $newPath = rtrim($this->currentPath, '/') . '/' . $directory;
        $this->loadDirectory($newPath);
    }

    /**
     * Navigate to a specific path (from breadcrumb)
     */
    public function navigateToPath(string $path): void
    {
        $this->loadDirectory($path);
    }

    /**
     * Navigate up one directory level
     */
    public function navigateUp(): void
    {
        $parent = dirname($this->currentPath);
        if ($this->pathValidator->isAllowed($this->webApp, $parent)) {
            $this->loadDirectory($parent);
        }
    }

    /**
     * Open a file for editing
     */
    public function openFile(string $filename, int $size = 0): void
    {
        $filePath = rtrim($this->currentPath, '/') . '/' . $filename;

        // Check if file is editable
        if (!$this->pathValidator->isEditable($filePath)) {
            Notification::make()
                ->title('Cannot Edit')
                ->body('This file type cannot be edited in the browser.')
                ->warning()
                ->send();
            return;
        }

        // Check file size
        if (!$this->pathValidator->isSizeEditable($size)) {
            $maxSize = number_format($this->pathValidator->getMaxEditableSize() / 1024 / 1024, 1);
            Notification::make()
                ->title('File Too Large')
                ->body("Files larger than {$maxSize}MB cannot be edited in the browser.")
                ->warning()
                ->send();
            return;
        }

        $this->editingFile = $filePath;
        $this->editingFileName = $filename;
        $this->fileContent = '';
        $this->originalContent = '';
        $this->isEditorLoading = true;
        $this->showEditor = true;

        $job = AgentJob::create([
            'server_id' => $this->webApp->server_id,
            'team_id' => $this->webApp->team_id,
            'type' => 'file_read',
            'payload' => [
                'web_app_id' => $this->webApp->id,
                'path' => $filePath,
            ],
            'priority' => 2,
        ]);

        $this->activeJobId = $job->id;
        $this->activeJobType = 'file_read';
    }

    /**
     * Save the currently edited file
     */
    public function saveFile(): void
    {
        if (!$this->editingFile) {
            return;
        }

        if ($this->fileContent === $this->originalContent) {
            Notification::make()->title('No Changes')->body('The file has not been modified.')->info()->send();
            return;
        }

        $this->isSaving = true;

        $job = AgentJob::create([
            'server_id' => $this->webApp->server_id,
            'team_id' => $this->webApp->team_id,
            'type' => 'file_write',
            'payload' => [
                'web_app_id' => $this->webApp->id,
                'path' => $this->editingFile,
                'content' => $this->fileContent,
                'backup' => true,
            ],
            'priority' => 2,
        ]);

        $this->activeJobId = $job->id;
        $this->activeJobType = 'file_write';
    }

    /**
     * Close the editor
     */
    public function closeEditor(): void
    {
        if ($this->fileContent !== $this->originalContent) {
            // TODO: Add unsaved changes warning
        }

        $this->showEditor = false;
        $this->editingFile = null;
        $this->editingFileName = null;
        $this->fileContent = '';
        $this->originalContent = '';
    }

    /**
     * Show delete confirmation modal
     */
    public function confirmDelete(array $files): void
    {
        $this->filesToDelete = $files;
        $this->showDeleteModal = true;
    }

    /**
     * Delete selected files
     */
    public function deleteFiles(): void
    {
        if (empty($this->filesToDelete)) {
            return;
        }

        $paths = array_map(
            fn($file) => rtrim($this->currentPath, '/') . '/' . $file,
            $this->filesToDelete
        );

        // Validate all paths
        foreach ($paths as $path) {
            if (!$this->pathValidator->isAllowed($this->webApp, $path)) {
                Notification::make()
                    ->title('Access Denied')
                    ->body('Cannot delete protected files.')
                    ->danger()
                    ->send();
                return;
            }
        }

        $job = AgentJob::create([
            'server_id' => $this->webApp->server_id,
            'team_id' => $this->webApp->team_id,
            'type' => 'file_delete',
            'payload' => [
                'web_app_id' => $this->webApp->id,
                'paths' => $paths,
            ],
            'priority' => 3,
        ]);

        $this->activeJobId = $job->id;
        $this->activeJobType = 'file_delete';
    }

    /**
     * Create a new folder
     */
    public function createFolder(): void
    {
        if (empty(trim($this->newFolderName))) {
            return;
        }

        if (!$this->pathValidator->isValidDirectoryName($this->newFolderName)) {
            Notification::make()
                ->title('Invalid Name')
                ->body('Please enter a valid folder name.')
                ->danger()
                ->send();
            return;
        }

        $path = rtrim($this->currentPath, '/') . '/' . $this->newFolderName;

        $job = AgentJob::create([
            'server_id' => $this->webApp->server_id,
            'team_id' => $this->webApp->team_id,
            'type' => 'file_mkdir',
            'payload' => [
                'web_app_id' => $this->webApp->id,
                'path' => $path,
            ],
            'priority' => 3,
        ]);

        $this->activeJobId = $job->id;
        $this->activeJobType = 'file_mkdir';
    }

    /**
     * Refresh current directory
     */
    public function refresh(): void
    {
        $this->loadDirectory();
    }

    /**
     * Toggle file selection
     */
    public function toggleFileSelection(string $filename): void
    {
        if (in_array($filename, $this->selectedFiles)) {
            $this->selectedFiles = array_values(array_diff($this->selectedFiles, [$filename]));
        } else {
            $this->selectedFiles[] = $filename;
        }
    }

    /**
     * Clear file selection
     */
    public function clearSelection(): void
    {
        $this->selectedFiles = [];
    }

    /**
     * Check if editor has unsaved changes
     */
    public function hasUnsavedChanges(): bool
    {
        return $this->fileContent !== $this->originalContent;
    }

    /**
     * Get file icon based on type/extension
     */
    public function getFileIcon(array $file): string
    {
        if ($file['type'] === 'directory') {
            return 'heroicon-o-folder';
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        return match ($extension) {
            'php' => 'heroicon-o-code-bracket',
            'js', 'ts', 'jsx', 'tsx' => 'heroicon-o-code-bracket-square',
            'vue', 'svelte' => 'heroicon-o-cube',
            'html', 'htm' => 'heroicon-o-globe-alt',
            'css', 'scss', 'sass', 'less' => 'heroicon-o-paint-brush',
            'json', 'xml', 'yaml', 'yml' => 'heroicon-o-document-text',
            'md', 'txt' => 'heroicon-o-document',
            'jpg', 'jpeg', 'png', 'gif', 'svg', 'webp' => 'heroicon-o-photo',
            'pdf' => 'heroicon-o-document',
            'zip', 'tar', 'gz', 'rar' => 'heroicon-o-archive-box',
            default => 'heroicon-o-document',
        };
    }

    /**
     * Format file size for display
     */
    public function formatFileSize(?int $bytes): string
    {
        if ($bytes === null) {
            return '-';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1) . ' ' . $units[$i];
    }

    public function render()
    {
        return view('livewire.file-manager');
    }
}
