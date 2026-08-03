<?php

namespace Tests\Feature\Authorization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_auditor_login_is_rate_limited_by_email_and_ip(): void
    {
        $email = Str::uuid().'@example.test';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('auditor.login.store'), [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('auditor.login.store'), [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_client_login_is_rate_limited_by_email_and_ip(): void
    {
        $email = Str::uuid().'@example.test';

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('client.login.store'), [
                'email' => $email,
                'password' => 'wrong-password',
            ])->assertRedirect();
        }

        $this->post(route('client.login.store'), [
            'email' => $email,
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }
}
