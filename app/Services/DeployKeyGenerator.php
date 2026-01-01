<?php

namespace App\Services;

use App\Models\WebApp;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

class DeployKeyGenerator
{
    /**
     * Generate a new deploy key pair.
     *
     * @param string|WebApp $identifier Either a domain string or WebApp model
     * @return array{private_key: string, public_key: string}
     */
    public function generate(string|WebApp $identifier): array
    {
        $keyId = $identifier instanceof WebApp
            ? $identifier->id
            : Str::slug($identifier) . '-' . Str::random(8);

        $comment = $identifier instanceof WebApp
            ? "sitekit-deploy-{$identifier->id}"
            : "sitekit-deploy-{$keyId}";

        // Generate Ed25519 key pair (more secure than RSA)
        $privateKeyPath = sys_get_temp_dir() . '/deploy_key_' . $keyId;
        $publicKeyPath = $privateKeyPath . '.pub';

        // Remove existing files if any
        @unlink($privateKeyPath);
        @unlink($publicKeyPath);

        $result = Process::run([
            'ssh-keygen',
            '-t', 'ed25519',
            '-f', $privateKeyPath,
            '-N', '', // No passphrase
            '-C', $comment,
        ]);

        if (!$result->successful()) {
            throw new RuntimeException('Failed to generate deploy key: ' . $result->errorOutput());
        }

        if (!file_exists($privateKeyPath) || !file_exists($publicKeyPath)) {
            throw new RuntimeException('Deploy key files were not created');
        }

        $privateKey = file_get_contents($privateKeyPath);
        $publicKey = file_get_contents($publicKeyPath);

        // Clean up temp files
        @unlink($privateKeyPath);
        @unlink($publicKeyPath);

        // If WebApp model passed, update it
        if ($identifier instanceof WebApp) {
            $identifier->update([
                'deploy_private_key' => $privateKey,
                'deploy_public_key' => $publicKey,
            ]);
        }

        return [
            'private_key' => $privateKey,
            'public_key' => $publicKey,
        ];
    }

    /**
     * Get existing keys or generate new ones for a WebApp.
     */
    public function getOrGenerate(WebApp $app): array
    {
        if ($app->deploy_public_key && $app->deploy_private_key) {
            return [
                'private_key' => $app->deploy_private_key,
                'public_key' => $app->deploy_public_key,
            ];
        }

        return $this->generate($app);
    }

    /**
     * Regenerate keys for a WebApp.
     */
    public function regenerate(WebApp $app): array
    {
        return $this->generate($app);
    }
}
