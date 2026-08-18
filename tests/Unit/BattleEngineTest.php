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
