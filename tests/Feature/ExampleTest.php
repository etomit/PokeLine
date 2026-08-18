<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200)
            ->assertSee('world-hub')
            ->assertSee('hub-character')
            ->assertSee('global-music')
            ->assertSee('global-music-volume')
            ->assertSee('global-sound-volume')
            ->assertDontSee('online-team-workshop');
    }

    public function test_local_setup_and_arena_views_render(): void
    {
        $this->get('/play/local')->assertOk()->assertSee('items2');
        $this->withSession(['battle' => ['mode' => 'local', 'state' => [], 'pending' => null]])
            ->get('/battle')
            ->assertOk()
            ->assertSee('local-controls')
            ->assertSee('music-toggle')
            ->assertSee('battle-music-volume')
            ->assertSee('battle-sound-volume');
    }
}
