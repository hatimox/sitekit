<?php

namespace App\Http\Controllers;

use App\Models\Deployment;
use App\Models\WebApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookController extends Controller
{
    public function deploy(Request $request, WebApp $webApp): JsonResponse
    {
        // Verify signature
        if (!$this->verifySignature($request, $webApp)) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Parse payload based on provider
        $payload = $this->parsePayload($request, $webApp);

        if ($payload === null) {
            return response()->json(['message' => 'Payload could not be parsed']);
        }

        // Check if push is to monitored branch
        if ($payload['branch'] !== $webApp->branch) {
            return response()->json([
                'message' => 'Ignored: different branch',
                'received' => $payload['branch'],
                'expected' => $webApp->branch,
            ]);
        }

        // Check auto-deploy enabled
        if (!$webApp->auto_deploy) {
            return response()->json(['message' => 'Auto-deploy disabled']);
        }

        // Create deployment
        $deployment = Deployment::create([
            'web_app_id' => $webApp->id,
            'team_id' => $webApp->team_id,
            'source_provider_id' => $webApp->source_provider_id,
            'repository' => $webApp->repository,
            'branch' => $webApp->branch,
            'commit_hash' => $payload['commit_hash'],
            'commit_message' => Str::limit($payload['commit_message'], 255),
            'trigger' => Deployment::TRIGGER_WEBHOOK,
        ]);

        // Dispatch to agent
        $deployment->dispatchJob();

        return response()->json([
            'message' => 'Deployment started',
            'deployment_id' => $deployment->id,
        ]);
    }

    protected function verifySignature(Request $request, WebApp $webApp): bool
    {
        $provider = $webApp->sourceProvider?->provider ?? 'github';

        return match ($provider) {
            'github' => $this->verifyGitHubSignature($request, $webApp),
            'gitlab' => $this->verifyGitLabSignature($request, $webApp),
            'bitbucket' => $this->verifyBitbucketSignature($request, $webApp),
            default => false,
        };
    }

    protected function verifyGitHubSignature(Request $request, WebApp $webApp): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $webApp->webhook_secret);

        return hash_equals($expected, $signature);
    }

    protected function verifyGitLabSignature(Request $request, WebApp $webApp): bool
    {
        $token = $request->header('X-Gitlab-Token');

        return hash_equals($webApp->webhook_secret, $token ?? '');
    }

    protected function verifyBitbucketSignature(Request $request, WebApp $webApp): bool
    {
        // Bitbucket uses a different approach - check the webhook secret header
        $signature = $request->header('X-Hub-Signature');

        if (!$signature) {
            return true; // Bitbucket webhooks may not have signatures
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $webApp->webhook_secret);

        return hash_equals($expected, $signature);
    }

    protected function parsePayload(Request $request, WebApp $webApp): ?array
    {
        $provider = $webApp->sourceProvider?->provider ?? 'github';

        return match ($provider) {
            'github' => $this->parseGitHubPayload($request),
            'gitlab' => $this->parseGitLabPayload($request),
            'bitbucket' => $this->parseBitbucketPayload($request),
            default => null,
        };
    }

    protected function parseGitHubPayload(Request $request): ?array
    {
        $payload = $request->all();

        $ref = $payload['ref'] ?? '';
        $branch = str_replace('refs/heads/', '', $ref);

        $headCommit = $payload['head_commit'] ?? [];

        return [
            'branch' => $branch,
            'commit_hash' => $headCommit['id'] ?? $payload['after'] ?? null,
            'commit_message' => $headCommit['message'] ?? '',
        ];
    }

    protected function parseGitLabPayload(Request $request): ?array
    {
        $payload = $request->all();

        $ref = $payload['ref'] ?? '';
        $branch = str_replace('refs/heads/', '', $ref);

        $commits = $payload['commits'] ?? [];
        $lastCommit = end($commits) ?: [];

        return [
            'branch' => $branch,
            'commit_hash' => $payload['after'] ?? null,
            'commit_message' => $lastCommit['message'] ?? '',
        ];
    }

    protected function parseBitbucketPayload(Request $request): ?array
    {
        $payload = $request->all();

        $push = $payload['push'] ?? [];
        $changes = $push['changes'] ?? [];
        $firstChange = $changes[0] ?? [];
        $newTarget = $firstChange['new'] ?? [];

        return [
            'branch' => $newTarget['name'] ?? '',
            'commit_hash' => $newTarget['target']['hash'] ?? null,
            'commit_message' => $newTarget['target']['message'] ?? '',
        ];
    }
}
