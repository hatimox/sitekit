<?php

namespace App\Services\GitProviders;

use App\Models\SourceProvider;
use InvalidArgumentException;

class GitProviderFactory
{
    public static function make(SourceProvider $provider): GitProviderInterface
    {
        return match ($provider->provider) {
            SourceProvider::PROVIDER_GITHUB => new GitHubService($provider),
            SourceProvider::PROVIDER_GITLAB => new GitLabService($provider),
            SourceProvider::PROVIDER_BITBUCKET => new BitbucketService($provider),
            default => throw new InvalidArgumentException("Unknown provider: {$provider->provider}"),
        };
    }
}
