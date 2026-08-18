<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PokeApiService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.pokeapi.url', 'https://pokeapi.co/api/v2'), '/');
    }

    public function pokemon(int|string $identifier): array
    {
        $identifier = strtolower(trim((string) $identifier));

        $locale = app()->getLocale();

        return Cache::remember("pokeapi:pokemon:{$identifier}:{$locale}:v3", now()->addDays(14), function () use ($identifier, $locale) {
            $response = Http::acceptJson()->timeout(15)->retry(2, 250)->get("{$this->baseUrl}/pokemon/{$identifier}");
            if ($response->failed()) {
                throw new RuntimeException(__('ui.pokemon_not_found'));
            }

            $pokemon = $response->json();
            $moveRefs = $this->selectMoveRefs($pokemon['moves'] ?? []);
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (array $move) => $pool->as($move['name'])->acceptJson()->timeout(12)->get($move['url']),
                $moveRefs,
            ));

            $moves = [];
            foreach ($moveRefs as $moveRef) {
                $moveResponse = $responses[$moveRef['name']] ?? null;
                if (! $moveResponse || $moveResponse->failed()) {
                    continue;
                }
                $move = $moveResponse->json();
                if (($move['power'] ?? null) === null || ($move['damage_class']['name'] ?? 'status') === 'status') {
                    continue;
                }
                $moves[] = [
                    'name' => $move['name'],
                    'label' => $this->localizedName($move['names'] ?? [], $move['name'], $locale),
                    'type' => $move['type']['name'],
                    'power' => (int) $move['power'],
                    'accuracy' => (int) ($move['accuracy'] ?? 100),
                    'priority' => (int) ($move['priority'] ?? 0),
                    'damage_class' => $move['damage_class']['name'],
                ];
                if (count($moves) === 4) {
                    break;
                }
            }

            if ($moves === []) {
                $moves[] = ['name' => 'tackle', 'label' => $locale === 'fr' ? 'Charge' : 'Tackle', 'type' => 'normal', 'power' => 40, 'accuracy' => 100, 'priority' => 0, 'damage_class' => 'physical'];
            }

            $baseStats = collect($pokemon['stats'])->mapWithKeys(fn ($stat) => [$stat['stat']['name'] => (int) $stat['base_stat']])->all();
            $stats = $this->levelHundredStats($baseStats);
            $animated = data_get($pokemon, 'sprites.versions.generation-v.black-white.animated');

            return [
                'id' => (int) $pokemon['id'],
                'name' => $pokemon['name'],
                'label' => ucfirst(str_replace('-', ' ', $pokemon['name'])),
                'level' => 100,
                'types' => collect($pokemon['types'])->sortBy('slot')->pluck('type.name')->values()->all(),
                'stats' => $stats,
                'moves' => $moves,
                'sprites' => [
                    'front' => $animated['front_default'] ?? data_get($pokemon, 'sprites.front_default'),
                    'back' => $animated['back_default'] ?? data_get($pokemon, 'sprites.back_default') ?? data_get($pokemon, 'sprites.front_default'),
                    'artwork' => data_get($pokemon, 'sprites.other.official-artwork.front_default'),
                ],
            ];
        });
    }

    private function selectMoveRefs(array $moves): array
    {
        $weighted = [];
        foreach ($moves as $move) {
            $details = collect($move['version_group_details'] ?? []);
            $blackWhite = $details->filter(fn ($detail) => ($detail['version_group']['name'] ?? '') === 'black-white');
            $usable = ($blackWhite->isNotEmpty() ? $blackWhite : $details)
                ->filter(fn ($detail) => in_array($detail['move_learn_method']['name'] ?? '', ['level-up', 'machine'], true));
            if ($usable->isEmpty()) {
                continue;
            }
            $weighted[] = [
                'name' => $move['move']['name'],
                'url' => $move['move']['url'],
                'level' => (int) $usable->max('level_learned_at'),
            ];
        }

        usort($weighted, fn ($a, $b) => $b['level'] <=> $a['level']);

        return array_slice($weighted, 0, 12);
    }

    private function levelHundredStats(array $base): array
    {
        $stats = [];
        foreach (['hp', 'attack', 'defense', 'special-attack', 'special-defense', 'speed'] as $name) {
            $value = $base[$name] ?? 1;
            $stats[str_replace('-', '_', $name)] = $name === 'hp' ? (2 * $value) + 141 : (2 * $value) + 36;
        }

        return $stats;
    }

    private function localizedName(array $names, string $fallback, string $locale): string
    {
        $localized = Arr::first($names, fn ($name) => ($name['language']['name'] ?? '') === $locale);

        return $localized['name'] ?? ucfirst(str_replace('-', ' ', $fallback));
    }
}
