<?php

namespace App\Services;

use App\Models\Team;

class BattleEngine
{
    private const STATS = ['attack', 'defense', 'special_attack', 'special_defense', 'speed', 'accuracy', 'evasion'];

    public function __construct(private readonly TypeChart $types) {}

    public function createState(array $firstTeam, array $secondTeam, array $names = ['Joueur 1', 'Joueur 2']): array
    {
        return [
            'phase' => 'active', 'turn' => 1, 'weather' => null, 'weather_turns' => 0,
            'players' => [
                'p1' => ['name' => $names[0], 'active' => 0, 'roster' => $this->prepareRoster($firstTeam)],
                'p2' => ['name' => $names[1], 'active' => 0, 'roster' => $this->prepareRoster($secondTeam)],
            ],
            'winner' => null, 'forced_switch' => [], 'log' => [__('battle.start')], 'last_events' => [],
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

    /** Inspired by the score-based trainer AI used by the fifth generation. */
    public function chooseAiMove(array $state, string $aiKey = 'p2'): int
    {
        $enemyKey = $aiKey === 'p1' ? 'p2' : 'p1';
        $attacker = $this->active($state, $aiKey);
        $defender = $this->active($state, $enemyKey);
        $scores = [];

        foreach ($attacker['moves'] as $index => $move) {
            if (($move['current_pp'] ?? $move['pp'] ?? 1) <= 0) {
                $scores[$index] = -2000;

                continue;
            }
            if (($move['power'] ?? 0) <= 0) {
                $score = 18;
                if (($move['healing'] ?? 0) > 0) {
                    $missing = 1 - ($attacker['current_hp'] / max(1, $attacker['max_hp']));
                    $score += $missing > .5 ? 95 : ($missing > .25 ? 35 : -40);
                }
                if (($move['ailment'] ?? 'none') !== 'none' && ($defender['status'] ?? null) === null) {
                    $score += 45;
                }
                if (($move['weather'] ?? null) && ($state['weather'] ?? null) !== $move['weather']) {
                    $score += 25;
                }
                if (($move['stat_changes'] ?? []) !== []) {
                    $score += $state['turn'] < 4 ? 30 : 5;
                }
                $scores[$index] = $score * (random_int(90, 110) / 100);

                continue;
            }
            $effectiveness = $this->types->multiplier($move['type'], $defender['types']);
            $stab = in_array($move['type'], $attacker['types'], true) ? 1.5 : 1;
            $score = $move['power'] * $effectiveness * $stab * (($move['accuracy'] ?? 100) / 100);
            $score *= random_int(92, 108) / 100;
            if ($effectiveness <= 0 || $this->abilityImmunity($defender, $move)) {
                $score = -1000;
            }
            if ($this->estimateDamage($attacker, $defender, $move) >= $defender['current_hp']) {
                $score += 180;
            }
            if ($effectiveness > 1) {
                $score += 35;
            }
            if ($effectiveness < 1) {
                $score -= 25;
            }
            $scores[$index] = $score;
        }

        arsort($scores);
        $viable = array_filter($scores, fn ($score) => $score > -999);
        $pool = $viable !== [] ? $viable : $scores;
        $best = array_slice(array_keys($pool), 0, min(2, count($pool)));

        return (int) $best[array_rand($best)];
    }

    public function resolveTurn(array $state, array $actions): array
    {
        if (($state['phase'] ?? null) !== 'active' || array_filter($state['forced_switch'] ?? []) !== []) {
            return $state;
        }

        foreach (['p1', 'p2'] as $key) {
            $actions[$key] = ($actions[$key] ?? []) + ['action_type' => 'move', 'move_index' => 0];
            $this->activeReference($state, $key)['protected'] = false;
        }

        $events = [];
        $order = ['p1', 'p2'];
        usort($order, function ($a, $b) use ($state, $actions) {
            $priorityA = $this->actionPriority($state, $a, $actions[$a]);
            $priorityB = $this->actionPriority($state, $b, $actions[$b]);
            if ($priorityA !== $priorityB) {
                return $priorityB <=> $priorityA;
            }
            $speedA = $this->effectiveStat($this->active($state, $a), 'speed');
            $speedB = $this->effectiveStat($this->active($state, $b), 'speed');

            return $speedA === $speedB ? random_int(-1, 1) : $speedB <=> $speedA;
        });

        foreach ($order as $actorKey) {
            if ($state['phase'] !== 'active') {
                break;
            }
            $targetKey = $actorKey === 'p1' ? 'p2' : 'p1';
            if (($actions[$actorKey]['action_type'] ?? 'move') === 'switch') {
                $this->performSwitch($state, $actorKey, (int) ($actions[$actorKey]['pokemon_index'] ?? -1), $events);

                continue;
            }
            if ($this->active($state, $actorKey)['current_hp'] <= 0) {
                continue;
            }
            $this->attack($state, $actorKey, $targetKey, (int) ($actions[$actorKey]['move_index'] ?? 0), $events);
            $this->ensureActive($state, $targetKey, $events);
            $this->ensureActive($state, $actorKey, $events);
        }

        if ($state['phase'] === 'active') {
            foreach (['p1', 'p2'] as $key) {
                $this->endTurn($state, $key, $events);
                $this->ensureActive($state, $key, $events);
            }
            if (($state['weather_turns'] ?? 0) > 0 && --$state['weather_turns'] <= 0) {
                $state['weather'] = null;
                $events[] = ['type' => 'weather', 'text' => __('battle.weather_ended')];
            }
            if ($state['phase'] === 'active') {
                $state['turn']++;
            }
        }
        $state['last_events'] = $events;
        $state['log'] = array_slice(array_merge($state['log'], array_column($events, 'text')), -40);

        return $state;
    }

    public function resolveForcedSwitch(array $state, string $key, int $index, bool $appendEvents = false): array
    {
        if (($state['phase'] ?? null) !== 'active' || ! ($state['forced_switch'][$key] ?? false)) {
            return $state;
        }

        $before = $state['players'][$key]['active'];
        $events = [];
        $this->performSwitch($state, $key, $index, $events);
        if ($state['players'][$key]['active'] === $before) {
            return $state;
        }

        unset($state['forced_switch'][$key]);
        $state['forced_switch'] = array_filter($state['forced_switch']);
        $state['last_events'] = $appendEvents
            ? array_merge($state['last_events'] ?? [], $events)
            : $events;
        $state['log'] = array_slice(array_merge($state['log'], array_column($events, 'text')), -40);

        return $state;
    }

    private function attack(array &$state, string $actorKey, string $targetKey, int $moveIndex, array &$events): void
    {
        $actor = &$this->activeReference($state, $actorKey);
        $target = &$this->activeReference($state, $targetKey);
        $moveIndex = isset($actor['moves'][$moveIndex]) ? $moveIndex : 0;
        if (($actor['moves'][$moveIndex]['current_pp'] ?? 0) <= 0) {
            $moveIndex = array_key_first(array_filter($actor['moves'], fn ($move) => ($move['current_pp'] ?? 0) > 0)) ?? 0;
        }
        $move = &$actor['moves'][$moveIndex];
        $move['current_pp'] = max(0, ($move['current_pp'] ?? $move['pp'] ?? 1) - 1);
        $events[] = ['type' => 'attack', 'actor' => $actorKey, 'move' => $move['name'], 'text' => __('battle.uses', ['pokemon' => $actor['label'], 'move' => $move['label']])];

        if (! $this->canAct($actor, $actorKey, $events)) {
            return;
        }
        if (random_int(1, 100) > $this->effectiveAccuracy($actor, $target, (int) ($move['accuracy'] ?? 100))) {
            $events[] = ['type' => 'miss', 'actor' => $actorKey, 'text' => __('battle.miss', ['pokemon' => $actor['label']])];

            return;
        }
        if (($move['power'] ?? 0) <= 0) {
            $this->statusMove($state, $actorKey, $targetKey, $move, $events);

            return;
        }
        if ($target['protected']) {
            $events[] = ['type' => 'protect', 'target' => $targetKey, 'text' => __('battle.protected', ['pokemon' => $target['label']])];

            return;
        }
        if ($this->abilityImmunity($target, $move)) {
            $events[] = ['type' => 'immune', 'target' => $targetKey, 'text' => __('battle.ability_immune', ['pokemon' => $target['label'], 'ability' => $target['ability']])];

            return;
        }
        $effectiveness = $this->types->multiplier($move['type'], $target['types']);
        if ($effectiveness === 0.0) {
            $events[] = ['type' => 'immune', 'target' => $targetKey, 'text' => __('battle.immune')];

            return;
        }

        $critical = random_int(1, 24) <= max(1, 1 + (int) ($move['crit_rate'] ?? 0) * 2);
        $damage = $this->damage($state, $actor, $target, $move, $effectiveness, $critical);
        $wasFull = $target['current_hp'] === $target['max_hp'];
        if (($target['held_item'] === 'focus-sash' && ! $target['item_consumed'] || ($target['ability'] ?? null) === 'sturdy') && $wasFull && $damage >= $target['current_hp']) {
            $damage = max(0, $target['current_hp'] - 1);
            if ($target['held_item'] === 'focus-sash') {
                $target['item_consumed'] = true;
            }
            $events[] = ['type' => 'item', 'target' => $targetKey, 'text' => $target['held_item'] === 'focus-sash' ? __('battle.focus_sash', ['pokemon' => $target['label']]) : __('battle.sturdy', ['pokemon' => $target['label']])];
        }
        $target['current_hp'] = max(0, $target['current_hp'] - $damage);
        if ($critical) {
            $events[] = ['type' => 'critical', 'text' => __('battle.critical')];
        }
        $events[] = ['type' => 'damage', 'target' => $targetKey, 'amount' => $damage, 'effectiveness' => $effectiveness, 'text' => $effectiveness > 1 ? __('battle.super_effective') : ($effectiveness < 1 ? __('battle.not_effective') : __('battle.loses_hp', ['pokemon' => $target['label'], 'damage' => $damage]))];

        $this->afterDamage($actor, $target, $actorKey, $targetKey, $move, $damage, $events);
        $this->applyAilment($target, $targetKey, $move, $events);
        $this->triggerBerry($target, $targetKey, $events);
    }

    private function statusMove(array &$state, string $actorKey, string $targetKey, array $move, array &$events): void
    {
        $actor = &$this->activeReference($state, $actorKey);
        $target = &$this->activeReference($state, $targetKey);
        if (($move['protect'] ?? false)) {
            $actor['protected'] = true;
            $events[] = ['type' => 'protect', 'target' => $actorKey, 'text' => __('battle.protects', ['pokemon' => $actor['label']])];
        }
        if ($weather = ($move['weather'] ?? null)) {
            $state['weather'] = $weather;
            $state['weather_turns'] = 5;
            $events[] = ['type' => 'weather', 'text' => __('battle.weather_'.$weather)];
        }
        if (($move['healing'] ?? 0) > 0) {
            $heal = min($actor['max_hp'] - $actor['current_hp'], max(1, (int) floor($actor['max_hp'] * $move['healing'] / 100)));
            $actor['current_hp'] += $heal;
            $events[] = ['type' => 'heal', 'target' => $actorKey, 'amount' => $heal, 'text' => __('battle.heals', ['pokemon' => $actor['label'], 'heal' => $heal])];
        }
        foreach (($move['stat_changes'] ?? []) as $change) {
            $selfTarget = str_contains((string) ($move['target'] ?? ''), 'user');
            $recipient = &$this->activeReference($state, $selfTarget ? $actorKey : $targetKey);
            $stat = $change['stat'];
            if (! in_array($stat, self::STATS, true)) {
                continue;
            }
            $before = $recipient['stat_stages'][$stat];
            $recipient['stat_stages'][$stat] = max(-6, min(6, $before + (int) $change['change']));
            $events[] = ['type' => 'stat', 'target' => $selfTarget ? $actorKey : $targetKey, 'text' => __('battle.stat_changed', ['pokemon' => $recipient['label'], 'stat' => $stat, 'direction' => $change['change'] > 0 ? '↑' : '↓'])];
            unset($recipient);
        }
        $this->applyAilment($target, $targetKey, $move, $events, true);
    }

    private function canAct(array &$pokemon, string $key, array &$events): bool
    {
        if ($pokemon['status'] === 'sleep') {
            if (($pokemon['status_turns'] ?? 1) > 0) {
                $pokemon['status_turns']--;
                $events[] = ['type' => 'status', 'target' => $key, 'text' => __('battle.asleep', ['pokemon' => $pokemon['label']])];

                return false;
            }
            $pokemon['status'] = null;
            $events[] = ['type' => 'status', 'target' => $key, 'text' => __('battle.woke_up', ['pokemon' => $pokemon['label']])];
        }
        if ($pokemon['status'] === 'paralysis' && random_int(1, 4) === 1) {
            $events[] = ['type' => 'status', 'target' => $key, 'text' => __('battle.paralyzed', ['pokemon' => $pokemon['label']])];

            return false;
        }
        if ($pokemon['status'] === 'freeze') {
            if (random_int(1, 5) !== 1) {
                $events[] = ['type' => 'status', 'target' => $key, 'text' => __('battle.frozen', ['pokemon' => $pokemon['label']])];

                return false;
            }
            $pokemon['status'] = null;
        }

        return true;
    }

    private function applyAilment(array &$pokemon, string $key, array $move, array &$events, bool $statusMove = false): void
    {
        $ailment = $move['ailment'] ?? 'none';
        if ($ailment === 'none' || $pokemon['status'] !== null || $pokemon['current_hp'] <= 0) {
            return;
        }
        $chance = (int) ($move['ailment_chance'] ?? 0);
        if ($statusMove && $chance === 0) {
            $chance = 100;
        }
        if (random_int(1, 100) > $chance) {
            return;
        }
        $pokemon['status'] = match ($ailment) {
            'burn' => 'burn', 'poison', 'badly-poisoned' => 'poison', 'paralysis' => 'paralysis', 'sleep' => 'sleep', 'freeze' => 'freeze', default => null
        };
        if ($pokemon['status'] === null) {
            return;
        }
        if ($pokemon['status'] === 'sleep') {
            $pokemon['status_turns'] = random_int(1, 3);
        }
        $events[] = ['type' => 'status', 'target' => $key, 'text' => __('battle.status_inflicted', ['pokemon' => $pokemon['label'], 'status' => __('battle.status_'.$pokemon['status'])])];
    }

    private function damage(array $state, array $attacker, array $defender, array $move, float $effectiveness, bool $critical): int
    {
        $attackStat = $move['damage_class'] === 'special' ? 'special_attack' : 'attack';
        $defenseStat = $move['damage_class'] === 'special' ? 'special_defense' : 'defense';
        $attack = $this->effectiveStat($attacker, $attackStat, $critical && ($attacker['stat_stages'][$attackStat] ?? 0) < 0);
        $defense = $this->effectiveStat($defender, $defenseStat, $critical && ($defender['stat_stages'][$defenseStat] ?? 0) > 0);
        if ($defender['held_item'] === 'assault-vest' && $move['damage_class'] === 'special') {
            $defense *= 1.5;
        }
        $base = floor(floor(floor((42 * $move['power'] * $attack) / max(1, $defense)) / 50) + 2);
        $modifier = (in_array($move['type'], $attacker['types'], true) ? 1.5 : 1) * $effectiveness * (random_int(85, 100) / 100) * ($critical ? 1.5 : 1);
        if ($attacker['status'] === 'burn' && $move['damage_class'] === 'physical') {
            $modifier *= .5;
        }
        if (($state['weather'] ?? null) === 'rain') {
            $modifier *= $move['type'] === 'water' ? 1.5 : ($move['type'] === 'fire' ? .5 : 1);
        }
        if (($state['weather'] ?? null) === 'sun') {
            $modifier *= $move['type'] === 'fire' ? 1.5 : ($move['type'] === 'water' ? .5 : 1);
        }
        if (in_array($attacker['ability'] ?? null, ['blaze', 'torrent', 'overgrow', 'swarm'], true) && $attacker['current_hp'] <= $attacker['max_hp'] / 3) {
            $abilityType = ['blaze' => 'fire', 'torrent' => 'water', 'overgrow' => 'grass', 'swarm' => 'bug'][$attacker['ability']];
            if ($move['type'] === $abilityType) {
                $modifier *= 1.5;
            }
        }
        if (in_array($defender['ability'] ?? null, ['thick-fat', 'heatproof'], true) && in_array($move['type'], $defender['ability'] === 'thick-fat' ? ['fire', 'ice'] : ['fire'], true)) {
            $modifier *= .5;
        }
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

    private function afterDamage(array &$actor, array &$target, string $actorKey, string $targetKey, array $move, int $damage, array &$events): void
    {
        if (($move['drain'] ?? 0) > 0 && $actor['current_hp'] > 0) {
            $heal = min($actor['max_hp'] - $actor['current_hp'], max(1, (int) floor($damage * $move['drain'] / 100)));
            $actor['current_hp'] += $heal;
            $events[] = ['type' => 'heal', 'target' => $actorKey, 'amount' => $heal, 'text' => __('battle.heals', ['pokemon' => $actor['label'], 'heal' => $heal])];
        }
        if (($move['drain'] ?? 0) < 0 && $actor['current_hp'] > 0) {
            $actor['current_hp'] = max(0, $actor['current_hp'] - max(1, (int) floor($damage * abs($move['drain']) / 100)));
        }
        if ($actor['held_item'] === 'life-orb' && $actor['current_hp'] > 0) {
            $recoil = max(1, intdiv($actor['max_hp'], 10));
            $actor['current_hp'] = max(0, $actor['current_hp'] - $recoil);
            $events[] = ['type' => 'recoil', 'target' => $actorKey, 'amount' => $recoil, 'text' => __('battle.life_orb', ['pokemon' => $actor['label'], 'damage' => $recoil])];
        }
        if ($target['held_item'] === 'rocky-helmet' && $move['damage_class'] === 'physical' && $actor['current_hp'] > 0) {
            $recoil = max(1, intdiv($actor['max_hp'], 6));
            $actor['current_hp'] = max(0, $actor['current_hp'] - $recoil);
            $events[] = ['type' => 'recoil', 'target' => $actorKey, 'amount' => $recoil, 'text' => __('battle.rocky_helmet', ['pokemon' => $actor['label'], 'damage' => $recoil])];
        }
        if (in_array($target['ability'] ?? null, ['static', 'flame-body'], true) && $move['damage_class'] === 'physical' && $actor['status'] === null && random_int(1, 100) <= 30) {
            $actor['status'] = $target['ability'] === 'static' ? 'paralysis' : 'burn';
            $events[] = ['type' => 'status', 'target' => $actorKey, 'text' => __('battle.status_inflicted', ['pokemon' => $actor['label'], 'status' => __('battle.status_'.$actor['status'])])];
        }
    }

    private function endTurn(array &$state, string $key, array &$events): void
    {
        $pokemon = &$this->activeReference($state, $key);
        if ($pokemon['current_hp'] <= 0) {
            return;
        }
        if (in_array($pokemon['status'], ['burn', 'poison'], true)) {
            $divisor = $pokemon['status'] === 'burn' ? 16 : 8;
            $damage = max(1, intdiv($pokemon['max_hp'], $divisor));
            $pokemon['current_hp'] = max(0, $pokemon['current_hp'] - $damage);
            $events[] = ['type' => 'status-damage', 'target' => $key, 'amount' => $damage, 'text' => __('battle.status_damage', ['pokemon' => $pokemon['label'], 'damage' => $damage])];
        }
        $weather = $state['weather'] ?? null;
        $immune = $weather === 'sandstorm' ? array_intersect($pokemon['types'], ['rock', 'ground', 'steel']) !== [] : in_array('ice', $pokemon['types'], true);
        if (in_array($weather, ['sandstorm', 'hail'], true) && ! $immune && $pokemon['current_hp'] > 0) {
            $damage = max(1, intdiv($pokemon['max_hp'], 16));
            $pokemon['current_hp'] = max(0, $pokemon['current_hp'] - $damage);
            $events[] = ['type' => 'weather-damage', 'target' => $key, 'amount' => $damage, 'text' => __('battle.weather_damage', ['pokemon' => $pokemon['label'], 'damage' => $damage])];
        }
        if ($pokemon['held_item'] === 'leftovers' && $pokemon['current_hp'] > 0 && $pokemon['current_hp'] < $pokemon['max_hp']) {
            $heal = min(max(1, intdiv($pokemon['max_hp'], 16)), $pokemon['max_hp'] - $pokemon['current_hp']);
            $pokemon['current_hp'] += $heal;
            $events[] = ['type' => 'heal', 'target' => $key, 'amount' => $heal, 'text' => __('battle.leftovers', ['pokemon' => $pokemon['label'], 'heal' => $heal])];
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
        $events[] = ['type' => 'heal', 'target' => $key, 'amount' => $heal, 'text' => __('battle.sitrus', ['pokemon' => $pokemon['label'], 'heal' => $heal])];
    }

    private function performSwitch(array &$state, string $key, int $index, array &$events): void
    {
        $player = &$state['players'][$key];
        if (! isset($player['roster'][$index]) || $player['roster'][$index]['current_hp'] <= 0 || $index === $player['active']) {
            return;
        }
        $player['roster'][$player['active']]['stat_stages'] = array_fill_keys(self::STATS, 0);
        $player['active'] = $index;
        $events[] = ['type' => 'switch', 'target' => $key, 'text' => __('battle.enters', ['pokemon' => $player['roster'][$index]['label']])];
        $this->entryAbility($state, $key, $events);
    }

    private function entryAbility(array &$state, string $key, array &$events): void
    {
        $pokemon = $this->active($state, $key);
        if (($pokemon['ability'] ?? null) !== 'intimidate') {
            return;
        }
        $targetKey = $key === 'p1' ? 'p2' : 'p1';
        $target = &$this->activeReference($state, $targetKey);
        $target['stat_stages']['attack'] = max(-6, $target['stat_stages']['attack'] - 1);
        $events[] = ['type' => 'ability', 'target' => $targetKey, 'text' => __('battle.intimidate', ['pokemon' => $pokemon['label']])];
    }

    private function ensureActive(array &$state, string $key, array &$events): void
    {
        $active = $state['players'][$key]['active'];
        if ($state['players'][$key]['roster'][$active]['current_hp'] > 0) {
            unset($state['forced_switch'][$key]);

            return;
        }
        if ($state['forced_switch'][$key] ?? false) {
            return;
        }
        $fainted = $state['players'][$key]['roster'][$active]['label'];
        $events[] = ['type' => 'faint', 'target' => $key, 'text' => __('battle.fainted', ['pokemon' => $fainted])];
        foreach ($state['players'][$key]['roster'] as $index => $pokemon) {
            if ($index !== $active && $pokemon['current_hp'] > 0) {
                $state['forced_switch'][$key] = true;

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
            $pokemon['ability'] = $pokemon['ability'] ?? null;
            $pokemon['status'] = null;
            $pokemon['status_turns'] = 0;
            $pokemon['protected'] = false;
            $pokemon['stat_stages'] = array_fill_keys(self::STATS, 0);
            $pokemon['moves'] = array_map(function ($move) {
                $move['pp'] = max(1, (int) ($move['pp'] ?? 10));
                $move['current_pp'] = $move['pp'];

                return $move;
            }, $pokemon['moves']);

            return $pokemon;
        }, $team);
    }

    private function effectiveStat(array $pokemon, string $stat, bool $ignoreStage = false): float
    {
        $base = (float) ($pokemon['stats'][$stat] ?? 1);
        $stage = $ignoreStage ? 0 : (int) ($pokemon['stat_stages'][$stat] ?? 0);
        $multiplier = $stage >= 0 ? (2 + $stage) / 2 : 2 / (2 - $stage);
        if ($stat === 'speed' && ($pokemon['status'] ?? null) === 'paralysis') {
            $multiplier *= .5;
        }

        return $base * $multiplier;
    }

    private function effectiveAccuracy(array $actor, array $target, int $base): int
    {
        $stage = max(-6, min(6, ($actor['stat_stages']['accuracy'] ?? 0) - ($target['stat_stages']['evasion'] ?? 0)));
        $multiplier = $stage >= 0 ? (3 + $stage) / 3 : 3 / (3 - $stage);

        return max(1, min(100, (int) round($base * $multiplier)));
    }

    private function actionPriority(array $state, string $key, array $action): int
    {
        if (($action['action_type'] ?? 'move') === 'switch') {
            return 6;
        }

        return (int) ($this->active($state, $key)['moves'][$action['move_index'] ?? 0]['priority'] ?? 0);
    }

    private function abilityImmunity(array $defender, array $move): bool
    {
        return ($defender['ability'] ?? null) === 'levitate' && ($move['type'] ?? null) === 'ground';
    }

    private function estimateDamage(array $attacker, array $defender, array $move): int
    {
        $effectiveness = $this->types->multiplier($move['type'], $defender['types']);
        if ($effectiveness === 0.0 || ($move['power'] ?? 0) <= 0) {
            return 0;
        }
        $attackStat = $move['damage_class'] === 'special' ? 'special_attack' : 'attack';
        $defenseStat = $move['damage_class'] === 'special' ? 'special_defense' : 'defense';

        return (int) (((42 * $move['power'] * $attacker['stats'][$attackStat] / max(1, $defender['stats'][$defenseStat])) / 50 + 2) * $effectiveness);
    }

    private function active(array $state, string $key): array
    {
        return $state['players'][$key]['roster'][$state['players'][$key]['active']];
    }

    private function &activeReference(array &$state, string $key): array
    {
        $index = $state['players'][$key]['active'];

        return $state['players'][$key]['roster'][$index];
    }
}
