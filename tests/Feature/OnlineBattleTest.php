<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\ItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OnlineBattleTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_users_can_complete_an_online_turn_and_receive_rewards(): void
    {
        $this->seed(ItemSeeder::class);
        $host = User::factory()->create(['name' => 'Host']);
        $guest = User::factory()->create(['name' => 'Guest']);
        $hostTeam = $this->team($host, 'Fast', 300, 1000);
        $guestTeam = $this->team($guest, 'Slow', 20, 40);

        $this->actingAs($host)->post('/online/create', ['team_id' => $hostTeam->id])->assertRedirect();
        $battle = Battle::firstOrFail();
        $this->actingAs($guest)->post("/online/{$battle->public_id}/join", ['team_id' => $guestTeam->id])->assertRedirect();
        $this->assertSame('active', $battle->fresh()->status);
        $this->actingAs($host)->get("/online/{$battle->public_id}")->assertOk()->assertSee('battle-app');

        $this->actingAs($host)->postJson("/online/{$battle->public_id}/action", ['move_index' => 0])->assertOk()->assertJson(['submitted' => true]);
        $this->actingAs($host)->postJson("/online/{$battle->public_id}/action", ['move_index' => 0])->assertStatus(409);
        $this->actingAs($guest)->postJson("/online/{$battle->public_id}/action", ['move_index' => 0])->assertOk()->assertJsonPath('state.phase', 'finished');

        $battle->refresh();
        $this->assertSame($host->id, $battle->winner_id);
        $this->assertBetween(1, 3, $host->inventory()->sum('quantity'));
        $this->assertBetween(0, 2, $guest->inventory()->sum('quantity'));
    }

    public function test_public_search_matches_two_available_players_automatically(): void
    {
        $host = User::factory()->create(['name' => 'Dawn']);
        $guest = User::factory()->create(['name' => 'Lucas']);
        $hostTeam = $this->team($host, 'Torterra', 120, 90);
        $guestTeam = $this->team($guest, 'Infernape', 140, 90);

        $this->actingAs($host)->post('/online/create', [
            'team_id' => $hostTeam->id,
            'queue_type' => 'public',
        ])->assertRedirect();

        $battle = Battle::firstOrFail();
        $this->assertSame('waiting', $battle->status);
        $this->assertSame('online-public', $battle->mode);

        $this->actingAs($guest)->post('/online/create', [
            'team_id' => $guestTeam->id,
            'queue_type' => 'public',
        ])->assertRedirect("/online/{$battle->public_id}");

        $battle->refresh();
        $this->assertSame('active', $battle->status);
        $this->assertSame($guest->id, $battle->guest_id);
        $this->assertDatabaseCount('battles', 1);
    }

    public function test_invitation_code_only_joins_a_private_room(): void
    {
        $host = User::factory()->create(['name' => 'Barry']);
        $guest = User::factory()->create(['name' => 'Dawn']);
        $hostTeam = $this->team($host, 'Empoleon', 110, 90);
        $guestTeam = $this->team($guest, 'Roserade', 130, 90);

        $this->actingAs($host)->post('/online/create', [
            'team_id' => $hostTeam->id,
            'queue_type' => 'private',
        ])->assertRedirect();

        $battle = Battle::firstOrFail();
        $this->assertSame('online-private', $battle->mode);

        $this->actingAs($guest)->post('/online/join', [
            'team_id' => $guestTeam->id,
            'code' => $battle->code,
        ])->assertRedirect("/online/{$battle->public_id}");

        $this->assertSame('active', $battle->fresh()->status);
    }

    private function team(User $user, string $name, int $speed, int $power): Team
    {
        $team = $user->teams()->create(compact('name'));
        $snapshot = [
            'id' => random_int(1, 500), 'name' => strtolower($name), 'label' => $name, 'level' => 100, 'types' => ['normal'],
            'stats' => ['hp' => 250, 'attack' => 300, 'defense' => 150, 'special_attack' => 300, 'special_defense' => 150, 'speed' => $speed],
            'moves' => [['name' => 'blast', 'label' => 'Blast', 'type' => 'normal', 'power' => $power, 'accuracy' => 100, 'priority' => 0, 'damage_class' => 'special']],
            'sprites' => ['front' => '/front.png', 'back' => '/back.png'],
        ];
        $team->pokemon()->create(['slot' => 0, 'pokemon_id' => $snapshot['id'], 'pokemon_name' => $snapshot['name'], 'snapshot' => $snapshot]);

        return $team;
    }

    private function assertBetween(int $minimum, int $maximum, int $actual): void
    {
        $this->assertGreaterThanOrEqual($minimum, $actual);
        $this->assertLessThanOrEqual($maximum, $actual);
    }
}
