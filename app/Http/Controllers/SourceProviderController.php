<?php

namespace App\Http\Controllers;

use App\Models\SourceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;

class SourceProviderController extends Controller
{
    protected array $scopes = [
        'github' => ['repo', 'admin:repo_hook', 'read:org'],
        'gitlab' => ['api', 'read_repository'],
        'bitbucket' => ['repository', 'webhook'],
    ];

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        $scopes = $this->scopes[$provider] ?? [];

        return Socialite::driver($provider)
            ->scopes($scopes)
            ->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        $team = auth()->user()->currentTeam;

        try {
            $user = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()
                ->route('filament.app.pages.source-providers', ['tenant' => $team])
                ->with('error', 'Failed to connect: ' . $e->getMessage());
        }

        SourceProvider::updateOrCreate(
            [
                'team_id' => $team->id,
                'provider' => $provider,
                'provider_user_id' => $user->getId(),
            ],
            [
                'name' => $user->getName() ?? $user->getNickname() ?? 'Unknown',
                'provider_username' => $user->getNickname() ?? $user->getId(),
                'access_token' => $user->token,
                'refresh_token' => $user->refreshToken,
                'token_expires_at' => $user->expiresIn
                    ? now()->addSeconds($user->expiresIn)
                    : null,
            ]
        );

        return redirect()
            ->route('filament.app.pages.source-providers', ['tenant' => $team])
            ->with('success', "Connected to {$provider} successfully!");
    }

    public function disconnect(Request $request, SourceProvider $provider): RedirectResponse
    {
        // Ensure the provider belongs to the current team
        if ($provider->team_id !== auth()->user()->currentTeam->id) {
            abort(403);
        }

        // Check if any web apps are using this provider
        if ($provider->webApps()->exists()) {
            return back()->with('error', 'Cannot disconnect: This provider is in use by web applications.');
        }

        $provider->delete();

        return back()->with('success', 'Provider disconnected successfully.');
    }

    protected function validateProvider(string $provider): void
    {
        if (!in_array($provider, ['github', 'gitlab', 'bitbucket'])) {
            abort(404, 'Unknown provider');
        }
    }
}
