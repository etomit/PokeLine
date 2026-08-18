<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Battle extends Model
{
    protected $fillable = [
        'public_id', 'code', 'mode', 'status', 'host_id', 'guest_id',
        'host_team_id', 'guest_team_id', 'winner_id', 'turn', 'version',
        'state', 'pending_actions', 'rewards', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
            'pending_actions' => 'array',
            'rewards' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guest_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function hostTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'host_team_id');
    }

    public function guestTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'guest_team_id');
    }
}
