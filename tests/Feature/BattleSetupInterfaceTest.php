<?php

namespace Tests\Feature;

use App\Http\Controllers\BattleController;
use App\Models\User;
use App\Services\PokeApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
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

    public function test_a_party_can_contain_the_same_pokemon_more_than_once(): void
    {
        $method = new ReflectionMethod(BattleController::class, 'parseTeam');

        $this->assertSame(
            ['yveltal', 'yveltal', 'yveltal'],
            $method->invoke(new BattleController, 'yveltal, yveltal, yveltal'),
        );
    }

    public function test_an_online_team_can_save_duplicate_pokemon(): void
    {
        $user = User::factory()->create();
        $snapshot = [
            'id' => 717,
            'name' => 'yveltal',
            'label' => 'Yveltal',
            'level' => 100,
            'types' => ['dark', 'flying'],
            'stats' => ['hp' => 393],
            'moves' => [],
            'sprites' => ['front' => '/yveltal.png', 'back' => '/back/yveltal.png'],
        ];
        $this->mock(PokeApiService::class)
            ->shouldReceive('pokemon')
            ->times(3)
            ->with('yveltal')
            ->andReturn($snapshot);

        $this->actingAs($user)->post('/teams', [
            'name' => 'Trio Yveltal',
            'pokemon' => ['yveltal', 'yveltal', 'yveltal'],
            'items' => [null, null, null],
        ])->assertRedirect();

        $this->assertSame(3, $user->teams()->firstOrFail()->pokemon()->where('pokemon_name', 'yveltal')->count());
    }
}
