<?php

use App\Models\Battle;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('battles.{publicId}', function (User $user, string $publicId) {
    $battle = Battle::where('public_id', $publicId)->first();
    if (! $battle) {
        return false;
    }

    $side = $battle->host_id === $user->id ? 'p1' : ($battle->guest_id === $user->id ? 'p2' : null);
    $canWatch = $battle->mode === 'online-public' && in_array($battle->status, ['active', 'finished'], true);
    if ($side === null && ! $canWatch) {
        return false;
    }

    return ['id' => $user->id, 'name' => $user->name, 'role' => $side ? 'player' : 'spectator', 'side' => $side];
});
