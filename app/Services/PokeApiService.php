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

        return Cache::remember("pokeapi:pokemon:{$identifier}:{$locale}:v5", now()->addDays(14), function () use ($identifier, $locale) {
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
                $isStatus = ($move['damage_class']['name'] ?? 'status') === 'status';
                $hasUsefulEffect = $isStatus && (
                    (int) data_get($move, 'meta.healing', 0) !== 0
                    || data_get($move, 'meta.ailment.name', 'none') !== 'none'
                    || ($move['stat_changes'] ?? []) !== []
                    || in_array($move['name'], ['protect', 'detect', 'rain-dance', 'sunny-day', 'sandstorm', 'hail'], true)
                );
                if (($move['power'] ?? null) === null && ! $hasUsefulEffect) {
                    continue;
                }
                $moves[] = [
                    'name' => $move['name'],
                    'label' => $this->localizedName($move['names'] ?? [], $move['name'], $locale),
                    'type' => $move['type']['name'],
                    'power' => (int) ($move['power'] ?? 0),
                    'accuracy' => (int) ($move['accuracy'] ?? 100),
                    'priority' => (int) ($move['priority'] ?? 0),
                    'damage_class' => $move['damage_class']['name'],
                    'pp' => (int) ($move['pp'] ?? 10),
                    'ailment' => data_get($move, 'meta.ailment.name', 'none'),
                    'ailment_chance' => (int) (data_get($move, 'meta.ailment_chance') ?: data_get($move, 'effect_chance', 0)),
                    'healing' => (int) data_get($move, 'meta.healing', 0),
                    'drain' => (int) data_get($move, 'meta.drain', 0),
                    'crit_rate' => (int) data_get($move, 'meta.crit_rate', 0),
                    'target' => data_get($move, 'target.name', 'selected-pokemon'),
                    'stat_changes' => collect($move['stat_changes'] ?? [])->map(fn ($change) => [
                        'stat' => str_replace('-', '_', data_get($change, 'stat.name', '')),
                        'change' => (int) ($change['change'] ?? 0),
                    ])->all(),
                    'protect' => in_array($move['name'], ['protect', 'detect'], true),
                    'weather' => match ($move['name']) {
                        'rain-dance' => 'rain', 'sunny-day' => 'sun', 'sandstorm' => 'sandstorm', 'hail' => 'hail', default => null,
                    },
                ];
                if (count($moves) === 4) {
                    break;
                }
            }

            if ($moves === []) {
                $moves[] = ['name' => 'tackle', 'label' => $locale === 'fr' ? 'Charge' : 'Tackle', 'type' => 'normal', 'power' => 40, 'accuracy' => 100, 'priority' => 0, 'damage_class' => 'physical', 'pp' => 35];
            }

            $baseStats = collect($pokemon['stats'])->mapWithKeys(fn ($stat) => [$stat['stat']['name'] => (int) $stat['base_stat']])->all();
            $stats = $this->levelHundredStats($baseStats);
            $spriteRepository = 'https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon';

            return [
                'id' => (int) $pokemon['id'],
                'name' => $pokemon['name'],
                'label' => ucfirst(str_replace('-', ' ', $pokemon['name'])),
                'level' => 100,
                'types' => collect($pokemon['types'])->sortBy('slot')->pluck('type.name')->values()->all(),
                'stats' => $stats,
                'ability' => collect($pokemon['abilities'] ?? [])->first(fn ($ability) => ! ($ability['is_hidden'] ?? false))['ability']['name'] ?? null,
                'moves' => $moves,
                'sprites' => [
                    'front' => "{$spriteRepository}/{$pokemon['id']}.png",
                    'back' => "{$spriteRepository}/back/{$pokemon['id']}.png",
                    'artwork' => data_get($pokemon, 'sprites.other.official-artwork.front_default'),
                ],
            ];
        });
    }

    public function catalog(int $page = 1, string $search = '', int $perPage = 30): array
    {
        $entries = Cache::remember('pokeapi:catalog:v1', now()->addDays(7), function () {
            $response = Http::acceptJson()->timeout(20)->retry(2, 300)->get("{$this->baseUrl}/pokemon", ['limit' => 2000, 'offset' => 0]);
            if ($response->failed()) {
                throw new RuntimeException(__('ui.pokedex_unavailable'));
            }

            return collect($response->json('results', []))->map(function (array $entry) {
                preg_match('~/pokemon/(\d+)/?$~', $entry['url'], $matches);
                $id = (int) ($matches[1] ?? 0);

                return [
                    'id' => $id,
                    'name' => $entry['name'],
                    'label' => ucfirst(str_replace('-', ' ', $entry['name'])),
                    'sprite' => "https://raw.githubusercontent.com/PokeAPI/sprites/master/sprites/pokemon/{$id}.png",
                ];
            })->filter(fn ($entry) => $entry['id'] > 0)->values()->all();
        });

        $search = strtolower(trim($search));
        if ($search !== '') {
            $entries = array_values(array_filter($entries, fn ($entry) => str_contains($entry['name'], $search) || (string) $entry['id'] === $search));
        }
        $total = count($entries);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));

        return [
            'data' => array_slice($entries, ($page - 1) * $perPage, $perPage),
            'page' => $page,
            'last_page' => $lastPage,
            'total' => $total,
        ];
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
