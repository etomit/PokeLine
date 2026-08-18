<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationAndLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_register_and_locale_is_persisted(): void
    {
        $response = $this->post('/register', [
            'name' => 'Red', 'email' => 'red@example.test', 'password' => 'password',
            'password_confirmation' => 'password', 'locale' => 'en',
        ]);
        $response->assertRedirect('/');
        $this->assertAuthenticated();
        $this->assertSame('en', User::first()->locale);
    }

    public function test_guest_locale_can_be_stored_in_a_cookie(): void
    {
        $this->from('/')->post('/language', ['locale' => 'en'])
            ->assertRedirect('/')
            ->assertCookie('pokeline_locale', 'en');
    }
}
