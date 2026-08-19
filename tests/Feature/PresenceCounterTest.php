<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PresenceCounterTest extends TestCase
{
    use RefreshDatabase;

    public function test_presence_counts_active_guests_and_distinct_connected_accounts(): void
    {
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $now = now()->timestamp;

        DB::table('sessions')->insert([
            ['id' => 'guest-active', 'user_id' => null, 'payload' => '', 'last_activity' => $now],
            ['id' => 'guest-stale', 'user_id' => null, 'payload' => '', 'last_activity' => $now - 180],
            ['id' => 'member-one-a', 'user_id' => $firstUser->id, 'payload' => '', 'last_activity' => $now],
            ['id' => 'member-one-b', 'user_id' => $firstUser->id, 'payload' => '', 'last_activity' => $now - 20],
            ['id' => 'member-two', 'user_id' => $secondUser->id, 'payload' => '', 'last_activity' => $now - 60],
        ]);

        $this->getJson('/api/presence')
            ->assertOk()
            ->assertJson([
                'active_players' => 3,
                'connected_accounts' => 2,
            ]);
    }

    public function test_global_layout_exposes_the_live_counters(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-presence-counters', false)
            ->assertSee('data-active-players', false)
            ->assertSee('data-connected-accounts', false);
    }
}
