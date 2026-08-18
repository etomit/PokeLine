<?php

namespace App\Http\Controllers;

use App\Models\Team;
use App\Services\PokeApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function store(Request $request, PokeApiService $pokeApi)
    {
        $pokemon = [];
        $items = [];
        foreach ((array) $request->input('pokemon', []) as $slot => $identifier) {
            if (trim((string) $identifier) === '') {
                continue;
            }
            $pokemon[] = trim($identifier);
            $items[] = $request->input("items.{$slot}");
        }
        $request->merge(['pokemon' => $pokemon, 'items' => $items]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:40'],
            'pokemon' => ['required', 'array', 'min:1', 'max:6'],
            'pokemon.*' => ['required', 'string', 'max:30'],
            'items' => ['nullable', 'array', 'max:6'],
            'items.*' => ['nullable', 'exists:items,id'],
        ]);

        if ($request->user()->teams()->count() >= 10) {
            throw ValidationException::withMessages(['name' => __('ui.max_teams')]);
        }

        $itemCounts = collect($data['items'] ?? [])->filter()->countBy();
        foreach ($itemCounts as $itemId => $needed) {
            $owned = $request->user()->inventory()->where('item_id', $itemId)->value('quantity') ?? 0;
            if ($owned < $needed) {
                throw ValidationException::withMessages(['items' => __('ui.item_not_owned')]);
            }
        }

        $snapshots = [];
        foreach ($data['pokemon'] as $identifier) {
            $snapshots[] = $pokeApi->pokemon($identifier);
        }

        DB::transaction(function () use ($request, $data, $snapshots) {
            $team = $request->user()->teams()->create(['name' => $data['name']]);
            foreach ($snapshots as $slot => $snapshot) {
                $team->pokemon()->create([
                    'slot' => $slot,
                    'pokemon_id' => $snapshot['id'],
                    'pokemon_name' => $snapshot['name'],
                    'snapshot' => $snapshot,
                    'held_item_id' => ($data['items'][$slot] ?? null) ?: null,
                ]);
            }
        });

        return back()->with('success', __('ui.team_created'));
    }

    public function destroy(Request $request, Team $team)
    {
        abort_unless($team->user_id === $request->user()->id, 403);
        $team->delete();

        return back()->with('success', __('ui.team_deleted'));
    }
}
