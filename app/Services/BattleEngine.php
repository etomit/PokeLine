<?php

namespace App\Services;

use App\Models\Team;

class BattleEngine
{
    public function __construct(private readonly TypeChart $types) {}

    public function createState(array $firstTeam, array $secondTeam, array $names = ['Joueur 1', 'Joueur 2']): array
    {
        return [
            'phase' => 'active',
            'turn' => 1,
            'players' => [
                'p1' => ['name' => $names[0], 'active' => 0, 'roster' => $this->prepareRoster($firstTeam)],
                'p2' => ['name' => $names[1], 'active' => 0, 'roster' => $this->prepareRoster($secondTeam)],
            ],
            'winner' => null,
            'log' => [__('battle.start')],
            'last_events' => [],
        ];
    }

    public function teamSnapshots(Team $team): array
    {
        return $team->pokemon->map(function ($entry) {
            $snapshot = $entry->snapshot;
            $snapshot['held_item'] = $entry->heldItem?->slug;
            $snapshot['held_item_label'] = $entry->heldItem?->name;

            return $snapshot;
        })->all();
    }

    public function chooseAiMove(array $state, string $aiKey = 'p2'): int
    {
        $enemyKey = $aiKey === 'p1' ? 'p2' : 'p1';
        $attacker = $this->active($state, $aiKey);
        $defender = $this->active($state, $enemyKey);
        $scores = [];

        foreach ($attacker['moves'] as $index => $move) {
            $effectiveness = $this->types->multiplier($move['type'], $defender['types']);
            $stab = in_array($move['type'], $attacker['types'], true) ? 1.5 : 1;
            $estimated = $move['power'] * $effectiveness * $stab * (($move['accuracy'] ?? 100) / 100);
            $estimated *= random_int(92, 108) / 100;
            if ($effectiveness <= 0) {
                $estimated = -1000;
            }
            if ($this->estimateDamage($attacker, $defender, $move) >= $defender['current_hp']) {
                $estimated += 180;
            }
            if ($effectiveness > 1) {
                $estimated += 35;
            }
            if ($effectiveness < 1) {
                $estimated -= 25;
            }
            $scores[$index] = $estimated;
        }

        arsort($scores);
        $viable = array_filter($scores, fn ($score) => $score > -999);
        $best = array_slice(array_keys($viable !== [] ? $viable : $scores), 0, min(2, count($viable !== [] ? $viable : $scores)));

        return (int) $best[array_rand($best)];
    }

    public function resolveTurn(array $state, array $actions): array
    {
        if (($state['phase'] ?? null) !== 'active') {
            return $state;
        }

        $events = [];
        $order = ['p1', 'p2'];
        usort($order, function ($a, $b) use ($state, $actions) {
            $moveA = $this->active($state, $a)['moves'][$actions[$a]['move_index']] ?? ['priority' => 0];
            $moveB = $this->active($state, $b)['moves'][$actions[$b]['move_index']] ?? ['priority' => 0];
            if (($moveA['priority'] ?? 0) !== ($moveB['priority'] ?? 0)) {
                return ($moveB['priority'] ?? 0) <=> ($moveA['priority'] ?? 0);
            }
            $speedA = $this->active($state, $a)['stats']['speed'];
            $speedB = $this->active($state, $b)['stats']['speed'];

            return $speedA === $speedB ? random_int(-1, 1) : $speedB <=> $speedA;
        });

        foreach ($order as $actorKey) {
            $targetKey = $actorKey === 'p1' ? 'p2' : 'p1';
            if ($this->active($state, $actorKey)['current_hp'] <= 0 || $state['phase'] !== 'active') {
                continue;
            }
            $moveIndex = (int) ($actions[$actorKey]['move_index'] ?? 0);
            $this->attack($state, $actorKey, $targetKey, $moveIndex, $events);
            $this->ensureActive($state, $targetKey, $events);
        }

        if ($state['phase'] === 'active') {
            foreach (['p1', 'p2'] as $key) {
                $this->endTurnItem($state, $key, $events);
            }
            $state['turn']++;
        }
        $state['last_events'] = $events;
        $state['log'] = array_slice(array_merge($state['log'], array_column($events, 'text')), -30);

        return $state;
    }

    private function attack(array &$state, string $actorKey, string $targetKey, int $moveIndex, array &$events): void
    {
        $actorIndex = $state['players'][$actorKey]['active'];
        $targetIndex = $state['players'][$targetKey]['active'];
        $actor = &$state['players'][$actorKey]['roster'][$actorIndex];
        $target = &$state['players'][$targetKey]['roster'][$targetIndex];
        $move = $actor['moves'][$moveIndex] ?? $actor['moves'][0];

        $events[] = ['type' => 'attack', 'actor' => $actorKey, 'move' => $move['name'], 'text' => __('battle.uses', ['pokemon' => $actor['label'], 'move' => $move['label']])];
        if (random_int(1, 100) > ($move['accuracy'] ?? 100)) {
            $events[] = ['type' => 'miss', 'actor' => $actorKey, 'text' => __('battle.miss', ['pokemon' => $actor['label']])];

            return;
        }

        $effectiveness = $this->types->multiplier($move['type'], $target['types']);
        if ($effectiveness === 0.0) {
            $events[] = ['type' => 'immune', 'target' => $targetKey, 'text' => __('battle.immune')];

            return;
        }

        $damage = $this->damage($actor, $target, $move, $effectiveness);
        $wasFull = $target['current_hp'] === $target['max_hp'];
        if ($target['held_item'] === 'focus-sash' && ! $target['item_consumed'] && $wasFull && $damage >= $target['current_hp']) {
            $damage = $target['current_hp'] - 1;
            $target['item_consumed'] = true;
            $events[] = ['type' => 'item', 'target' => $targetKey, 'text' => __('battle.focus_sash', ['pokemon' => $target['label']])];
        }
        $target['current_hp'] = max(0, $target['current_hp'] - $damage);
        $events[] = ['type' => 'damage', 'target' => $targetKey, 'amount' => $damage, 'effectiveness' => $effectiveness, 'text' => $effectiveness > 1 ? __('battle.super_effective') : ($effectiveness < 1 ? __('battle.not_effective') : __('battle.loses_hp', ['pokemon' => $target['label'], 'damage' => $damage]))];

        if ($actor['held_item'] === 'life-orb' && $actor['current_hp'] > 0) {
            $recoil = max(1, intdiv($actor['max_hp'], 10));
            $actor['current_hp'] = max(0, $actor['current_hp'] - $recoil);
            $events[] = ['type' => 'recoil', 'target' => $actorKey, 'text' => __('battle.life_orb', ['pokemon' => $actor['label'], 'damage' => $recoil])];
        }
        if ($target['held_item'] === 'rocky-helmet' && $move['damage_class'] === 'physical' && $actor['current_hp'] > 0) {
            $recoil = max(1, intdiv($actor['max_hp'], 6));
            $actor['current_hp'] = max(0, $actor['current_hp'] - $recoil);
            $events[] = ['type' => 'recoil', 'target' => $actorKey, 'text' => __('battle.rocky_helmet', ['pokemon' => $actor['label'], 'damage' => $recoil])];
        }
        $this->triggerBerry($target, $targetKey, $events);
    }

    private function damage(array $attacker, array $defender, array $move, float $effectiveness): int
    {
        $attackStat = $move['damage_class'] === 'special' ? 'special_attack' : 'attack';
        $defenseStat = $move['damage_class'] === 'special' ? 'special_defense' : 'defense';
        $attack = $attacker['stats'][$attackStat];
        $defense = $defender['stats'][$defenseStat];
        if ($defender['held_item'] === 'assault-vest' && $move['damage_class'] === 'special') {
            $defense *= 1.5;
        }
        $base = floor(floor(floor((42 * $move['power'] * $attack) / max(1, $defense)) / 50) + 2);
        $modifier = (in_array($move['type'], $attacker['types'], true) ? 1.5 : 1) * $effectiveness * (random_int(85, 100) / 100);
        if ($attacker['held_item'] === 'life-orb') {
            $modifier *= 1.3;
        }
        if ($attacker['held_item'] === 'choice-band' && $move['damage_class'] === 'physical') {
            $modifier *= 1.5;
        }
        if ($attacker['held_item'] === 'choice-specs' && $move['damage_class'] === 'special') {
            $modifier *= 1.5;
        }
        if ($attacker['held_item'] === 'expert-belt' && $effectiveness > 1) {
            $modifier *= 1.2;
        }

        return max(1, (int) floor($base * $modifier));
    }

    private function estimateDamage(array $attacker, array $defender, array $move): int
    {
        $effectiveness = $this->types->multiplier($move['type'], $defender['types']);
        if ($effectiveness === 0.0) {
            return 0;
        }
        $attackStat = $move['damage_class'] === 'special' ? 'special_attack' : 'attack';
        $defenseStat = $move['damage_class'] === 'special' ? 'special_defense' : 'defense';

        return (int) (((42 * $move['power'] * $attacker['stats'][$attackStat] / $defender['stats'][$defenseStat]) / 50 + 2) * $effectiveness);
    }

    private function endTurnItem(array &$state, string $key, array &$events): void
    {
        $index = $state['players'][$key]['active'];
        $pokemon = &$state['players'][$key]['roster'][$index];
        if ($pokemon['current_hp'] <= 0) {
            return;
        }
        if ($pokemon['held_item'] === 'leftovers' && $pokemon['current_hp'] < $pokemon['max_hp']) {
            $heal = min(max(1, intdiv($pokemon['max_hp'], 16)), $pokemon['max_hp'] - $pokemon['current_hp']);
            $pokemon['current_hp'] += $heal;
            $events[] = ['type' => 'heal', 'target' => $key, 'text' => __('battle.leftovers', ['pokemon' => $pokemon['label'], 'heal' => $heal])];
        }
    }

    private function triggerBerry(array &$pokemon, string $key, array &$events): void
    {
        if ($pokemon['held_item'] !== 'sitrus-berry' || $pokemon['item_consumed'] || $pokemon['current_hp'] <= 0 || $pokemon['current_hp'] > $pokemon['max_hp'] / 2) {
            return;
        }
        $heal = min(max(1, intdiv($pokemon['max_hp'], 4)), $pokemon['max_hp'] - $pokemon['current_hp']);
        $pokemon['current_hp'] += $heal;
        $pokemon['item_consumed'] = true;
        $events[] = ['type' => 'heal', 'target' => $key, 'text' => __('battle.sitrus', ['pokemon' => $pokemon['label'], 'heal' => $heal])];
    }

    private function ensureActive(array &$state, string $key, array &$events): void
    {
        $active = $state['players'][$key]['active'];
        if ($state['players'][$key]['roster'][$active]['current_hp'] > 0) {
            return;
        }
        $fainted = $state['players'][$key]['roster'][$active]['label'];
        $events[] = ['type' => 'faint', 'target' => $key, 'text' => __('battle.fainted', ['pokemon' => $fainted])];
        foreach ($state['players'][$key]['roster'] as $index => $pokemon) {
            if ($pokemon['current_hp'] > 0) {
                $state['players'][$key]['active'] = $index;
                $events[] = ['type' => 'switch', 'target' => $key, 'text' => __('battle.enters', ['pokemon' => $pokemon['label']])];

                return;
            }
        }
        $winner = $key === 'p1' ? 'p2' : 'p1';
        $state['phase'] = 'finished';
        $state['winner'] = $winner;
        $events[] = ['type' => 'finish', 'winner' => $winner, 'text' => __('battle.wins', ['player' => $state['players'][$winner]['name']])];
    }

    private function prepareRoster(array $team): array
    {
        return array_map(function ($pokemon) {
            $pokemon['max_hp'] = $pokemon['stats']['hp'];
            $pokemon['current_hp'] = $pokemon['stats']['hp'];
            $pokemon['item_consumed'] = false;
            $pokemon['held_item'] = $pokemon['held_item'] ?? null;

            return $pokemon;
        }, $team);
    }

    private function active(array $state, string $key): array
    {
        return $state['players'][$key]['roster'][$state['players'][$key]['active']];
    }
}
