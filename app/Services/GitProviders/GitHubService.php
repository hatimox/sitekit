<?php

namespace App\Services\GitProviders;

use App\Models\SourceProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GitHubService implements GitProviderInterface
{
    public function __construct(protected SourceProvider $provider) {}

    protected function client(): PendingRequest
    {
        return Http::withToken($this->provider->access_token)
            ->baseUrl('https://api.github.com')
            ->acceptJson()
            ->withHeaders([
                'X-GitHub-Api-Version' => '2022-11-28',
            ]);
    }

    public function repositories(int $page = 1): Collection
    {
        $allRepos = collect();

        // Fetch user's own repos (owned + collaborator)
        $response = $this->client()->get('/user/repos', [
            'per_page' => 100,
            'page' => $page,
            'sort' => 'updated',
            'type' => 'all',
        ]);

        if ($response->successful()) {
            $allRepos = $allRepos->concat($response->json());
        }

        // Also fetch repos from organizations the user belongs to
        $orgsResponse = $this->client()->get('/user/orgs', [
            'per_page' => 100,
        ]);

        if ($orgsResponse->successful()) {
            foreach ($orgsResponse->json() as $org) {
                $orgReposResponse = $this->client()->get("/orgs/{$org['login']}/repos", [
                    'per_page' => 100,
                    'sort' => 'updated',
                    'type' => 'all',
                ]);

                if ($orgReposResponse->successful()) {
                    $allRepos = $allRepos->concat($orgReposResponse->json());
                }
            }
        }

        // Remove duplicates and map to our format
        return $allRepos
            ->unique('id')
            ->sortByDesc('updated_at')
            ->values()
            ->map(fn ($repo) => [
                'id' => $repo['id'],
                'full_name' => $repo['full_name'],
                'name' => $repo['name'],
                'private' => $repo['private'],
                'default_branch' => $repo['default_branch'],
                'clone_url' => $repo['clone_url'],
                'ssh_url' => $repo['ssh_url'],
            ]);
    }

    public function branches(string $repo): Collection
    {
        $response = $this->client()->get("/repos/{$repo}/branches", [
            'per_page' => 100,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to fetch branches: ' . $response->body());
        }

        return collect($response->json())->pluck('name');
    }

    public function createWebhook(string $repo, string $url, string $secret): array
    {
        $response = $this->client()->post("/repos/{$repo}/hooks", [
            'name' => 'web',
            'config' => [
                'url' => $url,
                'content_type' => 'json',
                'secret' => $secret,
                'insecure_ssl' => '0',
            ],
            'events' => ['push'],
            'active' => true,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to create webhook: ' . $response->body());
        }

        return $response->json();
    }

    public function deleteWebhook(string $repo, int $hookId): void
    {
        $this->client()->delete("/repos/{$repo}/hooks/{$hookId}");
    }

    public function addDeployKey(string $repo, string $publicKey, string $title): array
    {
        $response = $this->client()->post("/repos/{$repo}/keys", [
            'title' => $title,
            'key' => trim($publicKey),
            'read_only' => true,
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Failed to add deploy key: ' . $response->body());
        }

        return $response->json();
    }

    public function removeDeployKey(string $repo, int $keyId): void
    {
        $this->client()->delete("/repos/{$repo}/keys/{$keyId}");
    }

    public function getLatestCommit(string $repo, string $branch): ?array
    {
        $response = $this->client()->get("/repos/{$repo}/commits/{$branch}");

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        return [
            'sha' => $data['sha'],
            'message' => $data['commit']['message'] ?? '',
            'author' => $data['commit']['author']['name'] ?? '',
            'date' => $data['commit']['author']['date'] ?? null,
        ];
    }
}
