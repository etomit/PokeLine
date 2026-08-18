<?php

namespace App\Events;

use App\Models\Battle;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BattleUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Battle $battle)
    {
        $this->battle->refresh();
    }

    public function broadcastOn(): array
    {
        return [new PresenceChannel('battles.'.$this->battle->public_id)];
    }

    public function broadcastAs(): string
    {
        return 'updated';
    }

    public function broadcastWith(): array
    {
        $pending = $this->battle->pending_actions ?? [];

        return [
            'status' => $this->battle->status,
            'state' => $this->battle->state,
            'version' => $this->battle->version,
            'rewards' => $this->battle->rewards,
            'submitted' => ['p1' => isset($pending['p1']), 'p2' => isset($pending['p2'])],
        ];
    }
}
