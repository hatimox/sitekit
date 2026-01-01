<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_verification_screen_can_be_rendered(): void
    {
        if (! Features::enabled(Features::emailVerification())) {
            $this->markTestSkipped('Email verification not enabled.');
        }

        $this->markTestSkipped('Email verification via Filament uses Livewire components.');
    }

    public function test_email_can_be_verified(): void
    {
        $this->markTestSkipped('Email verification via Filament uses Livewire components.');
    }

    public function test_email_can_not_verified_with_invalid_hash(): void
    {
        $this->markTestSkipped('Email verification via Filament uses Livewire components.');
    }
}
