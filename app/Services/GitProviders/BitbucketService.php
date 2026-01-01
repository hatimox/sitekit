<?php

namespace App\Services\GitProviders;

use App\Models\SourceProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BitbucketService implements GitProviderInterface
{
    public function __construct(protected SourceProvider $provider) {}

    protected function client(): PendingRequest
    {
        return Http::withToken($this->provider->access_token, 'Bearer')
            ->baseUrl('https://api.bitbucket.org/2.0')
            ->acceptJson();
    }

    public function repositories(int $page = 1): Collection
    {
        $response = $this->client()->get('/user/permissions/repositories', [
            'pagelen' => 100,
            'page' => $page,
            'sort' => '-updated_on',
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to fetch repositories: ' . $response->body());
        }

        return collect($response->json()['values'] ?? [])->map(fn ($item) => [
            'id' => $item['repository']['uuid'],
            'full_name' => $item['repository']['full_name'],
            'name' => $item['repository']['name'],
            'private' => $item['repository']['is_private'],
            'default_branch' => $item['repository']['mainbranch']['name'] ?? 'main',
            'clone_url' => collect($item['repository']['links']['clone'] ?? [])->firstWhere('name', 'https')['href'] ?? '',
            'ssh_url' => collect($item['repository']['links']['clone'] ?? [])->firstWhere('name', 'ssh')['href'] ?? '',
        ]);
    }

    public function branches(string $repo): Collection
    {
        $response = $this->client()->get("/repositories/{$repo}/refs/branches", [
            'pagelen' => 100,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to fetch branches: ' . $response->body());
        }

        return collect($response->json()['values'] ?? [])->pluck('name');
    }

    public function createWebhook(string $repo, string $url, string $secret): array
    {
        $response = $this->client()->post("/repositories/{$repo}/hooks", [
            'description' => 'SiteKit Deployment',
            'url' => $url,
            'active' => true,
            'events' => ['repo:push'],
            'secret' => $secret,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to create webhook: ' . $response->body());
        }

        return $response->json();
    }

    public function deleteWebhook(string $repo, int $hookId): void
    {
        $this->client()->delete("/repositories/{$repo}/hooks/{$hookId}");
    }

    public function addDeployKey(string $repo, string $publicKey, string $title): array
    {
        $response = $this->client()->post("/repositories/{$repo}/deploy-keys", [
            'label' => $title,
            'key' => trim($publicKey),
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to add deploy key: ' . $response->body());
        }

        return $response->json();
    }

    public function removeDeployKey(string $repo, int $keyId): void
    {
        $this->client()->delete("/repositories/{$repo}/deploy-keys/{$keyId}");
    }

    public function getLatestCommit(string $repo, string $branch): ?array
    {
        $response = $this->client()->get("/repositories/{$repo}/commits/{$branch}", [
            'pagelen' => 1,
        ]);

        if (!$response->successful()) {
            return null;
        }

        $commits = $response->json()['values'] ?? [];
        if (empty($commits)) {
            return null;
        }

        $data = $commits[0];

        return [
            'sha' => $data['hash'],
            'message' => $data['message'] ?? '',
            'author' => $data['author']['raw'] ?? '',
            'date' => $data['date'] ?? null,
        ];
    }
}
