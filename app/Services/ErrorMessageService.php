<?php

namespace App\Services;

/**
 * Maps technical error messages to user-friendly messages with suggested actions.
 */
class ErrorMessageService
{
    /**
     * Error patterns and their user-friendly mappings.
     * Order matters - first match wins.
     */
    protected array $patterns = [
        // Database authentication errors
        [
            'pattern' => '/Access denied for user/i',
            'title' => 'Database Authentication Failed',
            'message' => 'The stored database credentials are invalid or the user does not exist.',
            'action' => 'Try repairing the database service to reset credentials.',
            'action_type' => 'repair_service',
            'service' => 'database',
        ],
        [
            'pattern' => '/using password: (YES|NO)/i',
            'title' => 'Database Authentication Failed',
            'message' => 'Could not authenticate with the database server.',
            'action' => 'Try repairing the database service to reset credentials.',
            'action_type' => 'repair_service',
            'service' => 'database',
        ],

        // Connection errors
        [
            'pattern' => '/Connection refused/i',
            'title' => 'Service Not Running',
            'message' => 'The service is not running or not accepting connections.',
            'action' => 'Check if the service is running and try restarting it.',
            'action_type' => 'restart_service',
            'service' => null,
        ],
        [
            'pattern' => '/Can\'t connect to.*MySQL/i',
            'title' => 'Database Connection Failed',
            'message' => 'Could not connect to the MySQL/MariaDB server.',
            'action' => 'Ensure the database service is running.',
            'action_type' => 'restart_service',
            'service' => 'database',
        ],
        [
            'pattern' => '/could not connect to server.*PostgreSQL/i',
            'title' => 'Database Connection Failed',
            'message' => 'Could not connect to the PostgreSQL server.',
            'action' => 'Ensure PostgreSQL is running.',
            'action_type' => 'restart_service',
            'service' => 'postgresql',
        ],

        // Database/table not found
        [
            'pattern' => '/Unknown database/i',
            'title' => 'Database Not Found',
            'message' => 'The specified database does not exist on the server.',
            'action' => 'The database may have been deleted directly on the server.',
            'action_type' => null,
            'service' => 'database',
        ],
        [
            'pattern' => '/Table.*doesn\'t exist/i',
            'title' => 'Table Not Found',
            'message' => 'A required database table is missing.',
            'action' => 'The table may need to be recreated.',
            'action_type' => null,
            'service' => 'database',
        ],

        // File/directory errors
        [
            'pattern' => '/No such file or directory/i',
            'title' => 'File or Directory Missing',
            'message' => 'A required file or directory could not be found.',
            'action' => 'Check if the path exists on the server.',
            'action_type' => null,
            'service' => null,
        ],
        [
            'pattern' => '/Permission denied/i',
            'title' => 'Permission Denied',
            'message' => 'The operation was blocked due to insufficient permissions.',
            'action' => 'Check file ownership and permissions on the server.',
            'action_type' => null,
            'service' => null,
        ],

        // SSL/Certificate errors
        [
            'pattern' => '/certificate.*expired/i',
            'title' => 'SSL Certificate Expired',
            'message' => 'The SSL certificate has expired.',
            'action' => 'Renew the SSL certificate.',
            'action_type' => 'renew_ssl',
            'service' => 'ssl',
        ],
        [
            'pattern' => '/rate limit/i',
            'title' => 'Rate Limited',
            'message' => 'Too many certificate requests. Let\'s Encrypt has rate limits.',
            'action' => 'Wait before requesting another certificate.',
            'action_type' => null,
            'service' => 'ssl',
        ],

        // Supervisor errors
        [
            'pattern' => '/supervisor.*refused/i',
            'title' => 'Supervisor Not Running',
            'message' => 'The Supervisor service is not responding.',
            'action' => 'Try restarting the Supervisor service.',
            'action_type' => 'restart_service',
            'service' => 'supervisor',
        ],
        [
            'pattern' => '/no such process/i',
            'title' => 'Process Not Found',
            'message' => 'The specified process does not exist in Supervisor.',
            'action' => 'The program may need to be recreated.',
            'action_type' => null,
            'service' => 'supervisor',
        ],

        // Git/deployment errors
        [
            'pattern' => '/fatal: repository.*not found/i',
            'title' => 'Repository Not Found',
            'message' => 'The Git repository could not be found.',
            'action' => 'Check if the repository URL is correct and accessible.',
            'action_type' => null,
            'service' => 'git',
        ],
        [
            'pattern' => '/Permission denied \(publickey\)/i',
            'title' => 'Git Authentication Failed',
            'message' => 'Could not authenticate with the Git repository.',
            'action' => 'Check if the SSH key is added to the repository.',
            'action_type' => null,
            'service' => 'git',
        ],

        // Nginx errors
        [
            'pattern' => '/nginx.*test failed/i',
            'title' => 'Nginx Configuration Invalid',
            'message' => 'The Nginx configuration has errors.',
            'action' => 'Check the Nginx configuration for syntax errors.',
            'action_type' => null,
            'service' => 'nginx',
        ],

        // Timeout errors
        [
            'pattern' => '/timed? ?out/i',
            'title' => 'Operation Timed Out',
            'message' => 'The operation took too long and was cancelled.',
            'action' => 'Try again or check if the server is responsive.',
            'action_type' => 'retry',
            'service' => null,
        ],

        // Disk space
        [
            'pattern' => '/No space left on device/i',
            'title' => 'Disk Full',
            'message' => 'The server has run out of disk space.',
            'action' => 'Free up disk space on the server.',
            'action_type' => null,
            'service' => null,
        ],

        // Memory
        [
            'pattern' => '/Cannot allocate memory|out of memory/i',
            'title' => 'Out of Memory',
            'message' => 'The server does not have enough memory for this operation.',
            'action' => 'Consider upgrading the server or reducing memory usage.',
            'action_type' => null,
            'service' => null,
        ],
    ];

    /**
     * Parse an error message and return user-friendly information.
     */
    public function parse(string $error): array
    {
        foreach ($this->patterns as $entry) {
            if (preg_match($entry['pattern'], $error)) {
                return [
                    'title' => $entry['title'],
                    'message' => $entry['message'],
                    'action' => $entry['action'],
                    'action_type' => $entry['action_type'],
                    'service' => $entry['service'],
                    'original_error' => $error,
                ];
            }
        }

        // Default for unrecognized errors
        return [
            'title' => 'Operation Failed',
            'message' => 'An unexpected error occurred.',
            'action' => 'Check the error details for more information.',
            'action_type' => null,
            'service' => null,
            'original_error' => $error,
        ];
    }

    /**
     * Get just the user-friendly title for an error.
     */
    public function getTitle(string $error): string
    {
        return $this->parse($error)['title'];
    }

    /**
     * Get the suggested action type for an error.
     */
    public function getSuggestedAction(string $error): ?string
    {
        return $this->parse($error)['action_type'];
    }

    /**
     * Check if an error is related to authentication.
     */
    public function isAuthError(string $error): bool
    {
        return str_contains(strtolower($error), 'access denied')
            || str_contains(strtolower($error), 'authentication')
            || str_contains(strtolower($error), 'permission denied (publickey)');
    }

    /**
     * Check if an error is related to a service not running.
     */
    public function isServiceDownError(string $error): bool
    {
        return str_contains(strtolower($error), 'connection refused')
            || str_contains(strtolower($error), 'not running')
            || str_contains(strtolower($error), 'could not connect');
    }
}
