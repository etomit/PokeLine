<?php

namespace Tests\Feature;

use App\Services\PokeApiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PokeApiServiceTest extends TestCase
{
    public function test_it_builds_a_level_one_hundred_snapshot_from_pokeapi(): void
    {
        Cache::flush();
        Http::fake([
            '*/pokemon/pikachu' => Http::response($this->pokemonResponse()),
            '*/move/85/' => Http::response([
                'name' => 'thunderbolt', 'power' => 90, 'accuracy' => 100, 'priority' => 0,
                'damage_class' => ['name' => 'special'], 'type' => ['name' => 'electric'],
                'names' => [['name' => 'Tonnerre', 'language' => ['name' => 'fr']]],
            ]),
        ]);
        app()->setLocale('fr');
        $pokemon = app(PokeApiService::class)->pokemon('pikachu');
        $this->assertSame(100, $pokemon['level']);
        $this->assertSame((2 * 35) + 141, $pokemon['stats']['hp']);
        $this->assertSame((2 * 55) + 36, $pokemon['stats']['attack']);
        $this->assertSame('Tonnerre', $pokemon['moves'][0]['label']);
        $this->assertSame('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/25.png', $pokemon['sprites']['front']);
        $this->assertSame('https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/back/25.png', $pokemon['sprites']['back']);
    }

    private function pokemonResponse(): array
    {
        $base = ['hp' => 35, 'attack' => 55, 'defense' => 40, 'special-attack' => 50, 'special-defense' => 50, 'speed' => 90];

        return [
            'id' => 25, 'name' => 'pikachu', 'types' => [['slot' => 1, 'type' => ['name' => 'electric']]],
            'stats' => collect($base)->map(fn ($value, $name) => ['base_stat' => $value, 'stat' => ['name' => $name]])->values()->all(),
            'sprites' => ['front_default' => '/front.png', 'back_default' => '/back.png', 'other' => ['official-artwork' => ['front_default' => '/art.png']]],
            'moves' => [['move' => ['name' => 'thunderbolt', 'url' => 'https://pokeapi.co/api/v2/move/85/'], 'version_group_details' => [['level_learned_at' => 26, 'move_learn_method' => ['name' => 'level-up'], 'version_group' => ['name' => 'black-white']]]]],
        ];
    }
}
