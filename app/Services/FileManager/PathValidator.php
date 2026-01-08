<?php

namespace App\Services\FileManager;

use App\Models\WebApp;

class PathValidator
{
    /**
     * Files that should never be accessible via file manager
     */
    private const PROTECTED_FILES = [
        '.env',
        '.env.backup',
        '.env.production',
        '.env.local',
        'deploy_private_key',
        'id_rsa',
        'id_ed25519',
    ];

    /**
     * Directories that should never be accessible
     */
    private const PROTECTED_DIRECTORIES = [
        '.git',
        '.ssh',
        'node_modules',
        'vendor',
    ];

    /**
     * File extensions that can be edited in the browser
     */
    private const EDITABLE_EXTENSIONS = [
        // PHP
        'php', 'phtml',
        // JavaScript/TypeScript
        'js', 'mjs', 'cjs', 'ts', 'jsx', 'tsx',
        // Frontend frameworks
        'vue', 'svelte',
        // Markup & Styles
        'html', 'htm', 'css', 'scss', 'sass', 'less',
        // Data formats
        'json', 'xml', 'yaml', 'yml', 'toml',
        // Documentation
        'md', 'mdx', 'txt', 'rst',
        // Config files
        'conf', 'ini', 'cfg',
        // Shell scripts
        'sh', 'bash', 'zsh',
        // Other
        'sql', 'graphql', 'prisma',
        'dockerfile', 'dockerignore',
        'gitignore', 'gitattributes',
        'editorconfig', 'prettierrc', 'eslintrc',
        'htaccess', 'nginx',
        'env.example',
        'lock', // package-lock.json, composer.lock (read-only typically)
    ];

    /**
     * Maximum file size that can be edited (5MB)
     */
    private const MAX_EDITABLE_SIZE = 5 * 1024 * 1024;

    /**
     * Check if a path is allowed for the given web app
     */
    public function isAllowed(WebApp $webApp, string $path): bool
    {
        $normalizedPath = $this->normalizePath($path);
        $basePath = $this->normalizePath($webApp->root_path);

        // Must be within web app directory
        if (!str_starts_with($normalizedPath, $basePath)) {
            return false;
        }

        // Check for directory traversal attempts
        if (str_contains($path, '..')) {
            return false;
        }

        // Check protected directories
        foreach (self::PROTECTED_DIRECTORIES as $dir) {
            if (str_contains($normalizedPath, "/{$dir}/") || str_ends_with($normalizedPath, "/{$dir}")) {
                return false;
            }
        }

        // Check protected files
        $filename = basename($normalizedPath);
        if (in_array(strtolower($filename), array_map('strtolower', self::PROTECTED_FILES))) {
            return false;
        }

        return true;
    }

    /**
     * Check if a file can be edited based on its extension
     */
    public function isEditable(string $path): bool
    {
        $filename = strtolower(basename($path));

        // Handle dotfiles without extension
        $dotfiles = ['dockerfile', 'makefile', 'procfile', 'gemfile', 'rakefile'];
        if (in_array($filename, $dotfiles)) {
            return true;
        }

        // Handle files starting with a dot
        if (str_starts_with($filename, '.')) {
            $withoutDot = substr($filename, 1);
            if (in_array($withoutDot, self::EDITABLE_EXTENSIONS)) {
                return true;
            }
            // Common dotfiles
            $editableDotfiles = ['gitignore', 'gitattributes', 'editorconfig', 'prettierrc', 'eslintrc', 'htaccess', 'env.example'];
            if (in_array($withoutDot, $editableDotfiles)) {
                return true;
            }
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::EDITABLE_EXTENSIONS);
    }

    /**
     * Check if a file size is within editable limits
     */
    public function isSizeEditable(int $sizeInBytes): bool
    {
        return $sizeInBytes <= self::MAX_EDITABLE_SIZE;
    }

    /**
     * Get the maximum editable file size in bytes
     */
    public function getMaxEditableSize(): int
    {
        return self::MAX_EDITABLE_SIZE;
    }

    /**
     * Check if a filename is valid for creation
     */
    public function isValidFilename(string $filename): bool
    {
        // Must not be empty
        if (empty(trim($filename))) {
            return false;
        }

        // Must not contain path separators
        if (str_contains($filename, '/') || str_contains($filename, '\\')) {
            return false;
        }

        // Must not be a protected file
        if (in_array(strtolower($filename), array_map('strtolower', self::PROTECTED_FILES))) {
            return false;
        }

        // Must not contain null bytes or other dangerous characters
        if (preg_match('/[\x00-\x1F\x7F]/', $filename)) {
            return false;
        }

        // Must not be . or ..
        if ($filename === '.' || $filename === '..') {
            return false;
        }

        return true;
    }

    /**
     * Check if a directory name is valid for creation
     */
    public function isValidDirectoryName(string $dirname): bool
    {
        if (!$this->isValidFilename($dirname)) {
            return false;
        }

        // Must not be a protected directory
        if (in_array(strtolower($dirname), array_map('strtolower', self::PROTECTED_DIRECTORIES))) {
            return false;
        }

        return true;
    }

    /**
     * Get the allowed base path for a web app
     */
    public function getAllowedBasePath(WebApp $webApp): string
    {
        return $webApp->root_path;
    }

    /**
     * Normalize a path by removing redundant separators and resolving . components
     */
    private function normalizePath(string $path): string
    {
        // Replace multiple slashes with single slash
        $path = preg_replace('#/+#', '/', $path);

        // Remove trailing slash
        $path = rtrim($path, '/');

        // Split into parts
        $parts = explode('/', $path);
        $normalized = [];

        foreach ($parts as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                // Don't allow going above root
                if (!empty($normalized)) {
                    array_pop($normalized);
                }
            } else {
                $normalized[] = $part;
            }
        }

        return '/' . implode('/', $normalized);
    }

    /**
     * Get list of protected files
     */
    public function getProtectedFiles(): array
    {
        return self::PROTECTED_FILES;
    }

    /**
     * Get list of protected directories
     */
    public function getProtectedDirectories(): array
    {
        return self::PROTECTED_DIRECTORIES;
    }

    /**
     * Get list of editable extensions
     */
    public function getEditableExtensions(): array
    {
        return self::EDITABLE_EXTENSIONS;
    }
}
