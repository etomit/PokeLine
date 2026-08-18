<?php

namespace Tests\Unit;

use App\Services\BattleEngine;
use App\Services\TypeChart;
use Tests\TestCase;

class BattleEngineTest extends TestCase
{
    public function test_type_chart_combines_dual_types_and_immunities(): void
    {
        $chart = new TypeChart;
        $this->assertSame(4.0, $chart->multiplier('ice', ['dragon', 'flying']));
        $this->assertSame(0.0, $chart->multiplier('electric', ['water', 'ground']));
        $this->assertSame(0.25, $chart->multiplier('fire', ['water', 'dragon']));
    }

    public function test_level_one_hundred_battle_respects_type_immunity(): void
    {
        $engine = new BattleEngine(new TypeChart);
        $state = $engine->createState(
            [$this->pokemon('Pikachu', 'electric', 'electric', 90)],
            [$this->pokemon('Golem', 'ground', 'ground', 50)],
        );
        $before = $state['players']['p2']['roster'][0]['current_hp'];
        $state = $engine->resolveTurn($state, ['p1' => ['move_index' => 0], 'p2' => ['move_index' => 0]]);
        $this->assertSame($before, $state['players']['p2']['roster'][0]['current_hp']);
        $this->assertContains('immune', array_column($state['last_events'], 'type'));
    }

    public function test_ai_never_prefers_an_immune_move_when_damage_is_available(): void
    {
        $engine = new BattleEngine(new TypeChart);
        $ai = $this->pokemon('AI', 'electric', 'electric', 90);
        $ai['moves'][] = ['name' => 'tackle', 'label' => 'Tackle', 'type' => 'normal', 'power' => 40, 'accuracy' => 100, 'priority' => 0, 'damage_class' => 'physical'];
        $state = $engine->createState([$this->pokemon('Ground', 'ground', 'normal', 40)], [$ai]);
        for ($i = 0; $i < 12; $i++) {
            $this->assertSame(1, $engine->chooseAiMove($state));
        }
    }

    public function test_a_manual_switch_has_priority_and_changes_the_active_pokemon(): void
    {
        $engine = new BattleEngine(new TypeChart);
        $reserve = $this->pokemon('Reserve', 'water', 'water', 20);
        $state = $engine->createState(
            [$this->pokemon('Lead', 'normal', 'normal', 20), $reserve],
            [$this->pokemon('Enemy', 'normal', 'normal', 200)],
        );

        $state = $engine->resolveTurn($state, [
            'p1' => ['action_type' => 'switch', 'pokemon_index' => 1],
            'p2' => ['action_type' => 'move', 'move_index' => 0],
        ]);

        $this->assertSame(1, $state['players']['p1']['active']);
        $this->assertLessThan($reserve['stats']['hp'], $state['players']['p1']['roster'][1]['current_hp']);
        $this->assertContains('switch', array_column($state['last_events'], 'type'));
    }

    public function test_status_moves_apply_burn_and_end_turn_damage(): void
    {
        $engine = new BattleEngine(new TypeChart);
        $burner = $this->pokemon('Burner', 'fire', 'fire', 200);
        $burner['moves'][0] = [
            'name' => 'will-o-wisp', 'label' => 'Will-O-Wisp', 'type' => 'fire', 'power' => 0,
            'accuracy' => 100, 'priority' => 0, 'damage_class' => 'status', 'pp' => 15,
            'ailment' => 'burn', 'ailment_chance' => 100, 'stat_changes' => [],
        ];
        $state = $engine->createState([$burner], [$this->pokemon('Target', 'normal', 'normal', 20)]);
        $state = $engine->resolveTurn($state, [
            'p1' => ['move_index' => 0], 'p2' => ['move_index' => 0],
        ]);

        $this->assertSame('burn', $state['players']['p2']['roster'][0]['status']);
        $this->assertContains('status-damage', array_column($state['last_events'], 'type'));
        $this->assertSame(14, $state['players']['p1']['roster'][0]['moves'][0]['current_pp']);
    }

    public function test_a_fainted_pokemon_requires_a_manual_replacement(): void
    {
        $engine = new BattleEngine(new TypeChart);
        $fragile = $this->pokemon('Fragile', 'normal', 'normal', 20);
        $fragile['stats']['hp'] = 1;
        $state = $engine->createState(
            [$fragile, $this->pokemon('Reserve', 'water', 'water', 30)],
            [$this->pokemon('Attacker', 'normal', 'normal', 300)],
        );

        $state = $engine->resolveTurn($state, [
            'p1' => ['move_index' => 0],
            'p2' => ['move_index' => 0],
        ]);

        $this->assertTrue($state['forced_switch']['p1']);
        $this->assertSame(0, $state['players']['p1']['active']);

        $state = $engine->resolveForcedSwitch($state, 'p1', 1);

        $this->assertSame(1, $state['players']['p1']['active']);
        $this->assertArrayNotHasKey('p1', $state['forced_switch']);
        $this->assertContains('switch', array_column($state['last_events'], 'type'));
    }

    private function pokemon(string $label, string $type, string $moveType, int $speed): array
    {
        return [
            'id' => 1, 'name' => strtolower($label), 'label' => $label, 'level' => 100, 'types' => [$type],
            'stats' => ['hp' => 300, 'attack' => 200, 'defense' => 200, 'special_attack' => 200, 'special_defense' => 200, 'speed' => $speed],
            'moves' => [['name' => 'bolt', 'label' => 'Bolt', 'type' => $moveType, 'power' => 70, 'accuracy' => 100, 'priority' => 0, 'damage_class' => 'special']],
            'sprites' => ['front' => '/front.png', 'back' => '/back.png'],
        ];
    }
}
