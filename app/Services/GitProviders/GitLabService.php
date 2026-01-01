<?php

namespace App\Services\GitProviders;

use App\Models\SourceProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitLabService implements GitProviderInterface
{
    public function __construct(protected SourceProvider $provider) {}

    protected function client(): PendingRequest
    {
        return Http::withToken($this->provider->access_token)
            ->baseUrl('https://gitlab.com/api/v4')
            ->acceptJson();
    }

    protected function encodeRepo(string $repo): string
    {
        return urlencode($repo);
    }

    public function repositories(int $page = 1): Collection
    {
        $response = $this->client()->get('/projects', [
            'per_page' => 100,
            'page' => $page,
            'order_by' => 'last_activity_at',
            'membership' => true,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to fetch repositories: ' . $response->body());
        }

        return collect($response->json())->map(fn ($repo) => [
            'id' => $repo['id'],
            'full_name' => $repo['path_with_namespace'],
            'name' => $repo['name'],
            'private' => $repo['visibility'] !== 'public',
            'default_branch' => $repo['default_branch'] ?? 'main',
            'clone_url' => $repo['http_url_to_repo'],
            'ssh_url' => $repo['ssh_url_to_repo'],
        ]);
    }

    public function branches(string $repo): Collection
    {
        $response = $this->client()->get("/projects/{$this->encodeRepo($repo)}/repository/branches", [
            'per_page' => 100,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to fetch branches: ' . $response->body());
        }

        return collect($response->json())->pluck('name');
    }

    public function createWebhook(string $repo, string $url, string $secret): array
    {
        $response = $this->client()->post("/projects/{$this->encodeRepo($repo)}/hooks", [
            'url' => $url,
            'push_events' => true,
            'token' => $secret,
            'enable_ssl_verification' => true,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to create webhook: ' . $response->body());
        }

        return $response->json();
    }

    public function deleteWebhook(string $repo, int $hookId): void
    {
        $this->client()->delete("/projects/{$this->encodeRepo($repo)}/hooks/{$hookId}");
    }

    public function addDeployKey(string $repo, string $publicKey, string $title): array
    {
        $response = $this->client()->post("/projects/{$this->encodeRepo($repo)}/deploy_keys", [
            'title' => $title,
            'key' => trim($publicKey),
            'can_push' => false,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to add deploy key: ' . $response->body());
        }

        return $response->json();
    }

    public function removeDeployKey(string $repo, int $keyId): void
    {
        $this->client()->delete("/projects/{$this->encodeRepo($repo)}/deploy_keys/{$keyId}");
    }

    public function getLatestCommit(string $repo, string $branch): ?array
    {
        $response = $this->client()->get("/projects/{$this->encodeRepo($repo)}/repository/commits/{$branch}");

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        return [
            'sha' => $data['id'],
            'message' => $data['message'] ?? '',
            'author' => $data['author_name'] ?? '',
            'date' => $data['created_at'] ?? null,
        ];
    }
}
