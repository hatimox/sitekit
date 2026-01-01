<?php

namespace App\Services\GitProviders;

use Illuminate\Support\Collection;

interface GitProviderInterface
{
    public function repositories(int $page = 1): Collection;

    public function branches(string $repo): Collection;

    public function createWebhook(string $repo, string $url, string $secret): array;

    public function deleteWebhook(string $repo, int $hookId): void;

    public function addDeployKey(string $repo, string $publicKey, string $title): array;

    public function removeDeployKey(string $repo, int $keyId): void;

    public function getLatestCommit(string $repo, string $branch): ?array;
}
