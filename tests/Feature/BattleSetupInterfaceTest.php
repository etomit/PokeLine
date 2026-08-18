<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BattleSetupInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_solo_and_local_parties_are_empty_by_default_and_use_visual_controls(): void
    {
        foreach (['solo', 'local'] as $mode) {
            $response = $this->get("/play/{$mode}");

            $response->assertOk()
                ->assertSee('value=""', false)
                ->assertSee('data-team-input', false)
                ->assertSee('data-pokedex-mode="append"', false)
                ->assertSee('data-local-team-library', false)
                ->assertDontSee('pikachu, charizard, blastoise');
        }
    }
}
