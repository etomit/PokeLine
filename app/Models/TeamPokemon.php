<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamPokemon extends Model
{
    protected $table = 'team_pokemon';

    protected $fillable = ['team_id', 'slot', 'pokemon_id', 'pokemon_name', 'snapshot', 'held_item_id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function heldItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'held_item_id');
    }
}
