<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Maximum Upload Size
    |--------------------------------------------------------------------------
    |
    | The maximum file size in megabytes that can be uploaded via the
    | file manager. Larger files should be uploaded via SFTP.
    |
    */
    'max_upload_size' => env('FILE_MANAGER_MAX_UPLOAD_MB', 50),

    /*
    |--------------------------------------------------------------------------
    | Chunk Size
    |--------------------------------------------------------------------------
    |
    | The size of each chunk in kilobytes when uploading files.
    | Smaller chunks are more reliable on slow connections.
    |
    */
    'chunk_size' => env('FILE_MANAGER_CHUNK_SIZE_KB', 1024),

    /*
    |--------------------------------------------------------------------------
    | Maximum Editable File Size
    |--------------------------------------------------------------------------
    |
    | The maximum file size in megabytes that can be opened in the editor.
    | Larger files may cause browser performance issues.
    |
    */
    'max_editable_size' => env('FILE_MANAGER_MAX_EDITABLE_MB', 5),

    /*
    |--------------------------------------------------------------------------
    | Protected Files
    |--------------------------------------------------------------------------
    |
    | Files that should never be accessible via the file manager.
    | These typically contain sensitive information like credentials.
    |
    */
    'protected_files' => [
        '.env',
        '.env.backup',
        '.env.production',
        '.env.local',
        'deploy_private_key',
        'id_rsa',
        'id_ed25519',
    ],

    /*
    |--------------------------------------------------------------------------
    | Protected Directories
    |--------------------------------------------------------------------------
    |
    | Directories that should never be browsable via the file manager.
    | Accessing these could expose sensitive data or cause issues.
    |
    */
    'protected_directories' => [
        '.git',
        '.ssh',
        'node_modules',
        'vendor',
    ],

    /*
    |--------------------------------------------------------------------------
    | Blocked Upload Extensions
    |--------------------------------------------------------------------------
    |
    | File extensions that cannot be uploaded for security reasons.
    | These could be executed on the server if uploaded.
    |
    */
    'blocked_upload_extensions' => [
        'php',
        'phtml',
        'php3',
        'php4',
        'php5',
        'php7',
        'php8',
        'phar',
        'sh',
        'bash',
        'exe',
        'bat',
        'cmd',
        'com',
        'cgi',
        'pl',
        'py',
        'rb',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limits for file operations to prevent abuse.
    |
    */
    'rate_limits' => [
        'list_per_minute' => 60,
        'read_per_minute' => 30,
        'write_per_minute' => 20,
        'delete_per_minute' => 10,
        'upload_per_minute' => 10,
    ],
];
