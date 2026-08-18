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

    public function test_a_host_can_cancel_a_waiting_search(): void
    {
        $host = User::factory()->create();
        $team = $this->team($host, 'Search Team', 100, 70);

        $this->actingAs($host)->post('/online/create', ['team_id' => $team->id])->assertRedirect();
        $battle = Battle::firstOrFail();

        $this->actingAs($host)->delete("/online/{$battle->public_id}/cancel")->assertRedirect('/online');
        $this->assertDatabaseMissing('battles', ['id' => $battle->id]);
    }

    public function test_forfeit_finishes_the_battle_and_awards_both_players(): void
    {
        $this->seed(ItemSeeder::class);
        $host = User::factory()->create(['name' => 'Host']);
        $guest = User::factory()->create(['name' => 'Guest']);
        $hostTeam = $this->team($host, 'Host Team', 100, 70);
        $guestTeam = $this->team($guest, 'Guest Team', 90, 70);

        $this->actingAs($host)->post('/online/create', ['team_id' => $hostTeam->id]);
        $battle = Battle::firstOrFail();
        $this->actingAs($guest)->post("/online/{$battle->public_id}/join", ['team_id' => $guestTeam->id]);

        $this->actingAs($guest)->post("/online/{$battle->public_id}/forfeit")->assertRedirect('/online');

        $battle->refresh();
        $this->assertSame('finished', $battle->status);
        $this->assertSame($host->id, $battle->winner_id);
        $this->assertSame('finished', $battle->state['phase']);
        $this->assertSame('p1', $battle->state['winner']);
        $this->assertBetween(1, 3, $host->inventory()->sum('quantity'));
        $this->assertBetween(0, 2, $guest->inventory()->sum('quantity'));
    }

    public function test_public_battles_can_be_watched_but_private_battles_cannot(): void
    {
        $host = User::factory()->create(['name' => 'Dawn']);
        $guest = User::factory()->create(['name' => 'Lucas']);
        $spectator = User::factory()->create(['name' => 'Cynthia']);
        $hostTeam = $this->team($host, 'Torterra', 100, 70);
        $guestTeam = $this->team($guest, 'Infernape', 90, 70);

        $this->actingAs($host)->post('/online/create', ['team_id' => $hostTeam->id, 'queue_type' => 'public']);
        $publicBattle = Battle::firstOrFail();
        $this->actingAs($guest)->post("/online/{$publicBattle->public_id}/join", ['team_id' => $guestTeam->id]);

        $this->actingAs($spectator)->get("/online/{$publicBattle->public_id}/watch")
            ->assertOk()
            ->assertSee('data-kind="spectator"', false);
        $this->actingAs($spectator)->getJson("/online/{$publicBattle->public_id}/watch/state")
            ->assertOk()
            ->assertJson(['spectator' => true, 'status' => 'active']);
        $this->actingAs($spectator)->postJson("/online/{$publicBattle->public_id}/action", ['move_index' => 0])->assertForbidden();

        $privateBattle = Battle::create([
            'public_id' => fake()->uuid(), 'code' => 'SECRET', 'mode' => 'online-private', 'status' => 'active',
            'host_id' => $host->id, 'guest_id' => $guest->id, 'host_team_id' => $hostTeam->id, 'guest_team_id' => $guestTeam->id,
            'state' => $publicBattle->fresh()->state,
        ]);
        $this->actingAs($spectator)->get("/online/{$privateBattle->public_id}/watch")->assertNotFound();
    }

    public function test_lobby_lists_active_battles_for_reconnection_and_public_spectating(): void
    {
        $host = User::factory()->create(['name' => 'Dawn']);
        $guest = User::factory()->create(['name' => 'Lucas']);
        $viewer = User::factory()->create();
        $hostTeam = $this->team($host, 'Torterra', 100, 70);
        $guestTeam = $this->team($guest, 'Infernape', 90, 70);

        $this->actingAs($host)->post('/online/create', ['team_id' => $hostTeam->id]);
        $battle = Battle::firstOrFail();
        $this->actingAs($guest)->post("/online/{$battle->public_id}/join", ['team_id' => $guestTeam->id]);

        $this->actingAs($host)->get('/online')
            ->assertOk()
            ->assertSee(route('battle.online.show', $battle), false);
        $this->actingAs($viewer)->get('/online')
            ->assertOk()
            ->assertSee(route('battle.spectate', $battle), false);
    }

    public function test_a_connected_player_wins_after_the_opponent_has_been_offline_for_ninety_seconds(): void
    {
        $this->seed(ItemSeeder::class);
        $host = User::factory()->create(['name' => 'Connected']);
        $guest = User::factory()->create(['name' => 'Offline']);
        $hostTeam = $this->team($host, 'Host Team', 100, 70);
        $guestTeam = $this->team($guest, 'Guest Team', 90, 70);

        $this->actingAs($host)->post('/online/create', ['team_id' => $hostTeam->id]);
        $battle = Battle::firstOrFail();
        $this->actingAs($guest)->post("/online/{$battle->public_id}/join", ['team_id' => $guestTeam->id]);
        $battle->update([
            'host_last_seen_at' => now()->subSeconds(10),
            'guest_last_seen_at' => now()->subSeconds(91),
        ]);

        $this->actingAs($host)->postJson("/online/{$battle->public_id}/heartbeat")
            ->assertOk()
            ->assertJsonPath('status', 'finished')
            ->assertJsonPath('battle.state.winner', 'p1');

        $battle->refresh();
        $this->assertSame('finished', $battle->status);
        $this->assertSame($host->id, $battle->winner_id);
        $this->assertSame('p1', $battle->state['winner']);
        $this->assertBetween(1, 3, $host->inventory()->sum('quantity'));
        $this->assertBetween(0, 2, $guest->inventory()->sum('quantity'));
    }

    public function test_heartbeat_does_not_end_a_battle_while_the_opponent_is_present(): void
    {
        $host = User::factory()->create();
        $guest = User::factory()->create();
        $hostTeam = $this->team($host, 'Host Team', 100, 70);
        $guestTeam = $this->team($guest, 'Guest Team', 90, 70);

        $this->actingAs($host)->post('/online/create', ['team_id' => $hostTeam->id]);
        $battle = Battle::firstOrFail();
        $this->actingAs($guest)->post("/online/{$battle->public_id}/join", ['team_id' => $guestTeam->id]);
        $battle->update(['host_last_seen_at' => now(), 'guest_last_seen_at' => now()->subSeconds(89)]);

        $this->actingAs($host)->postJson("/online/{$battle->public_id}/heartbeat")
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('battle', null);

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
