<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Models\Team;
use App\Services\PokeApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        return view('teams.index', [
            'teams' => $request->user()->teams()->with(['pokemon.heldItem'])->latest()->get(),
            'inventory' => $request->user()->inventory()->with('item')->where('quantity', '>', 0)->get(),
        ]);
    }

    public function store(Request $request, PokeApiService $pokeApi)
    {
        $pokemon = [];
        $items = [];
        $submittedPokemon = $request->filled('team_roster')
            ? explode(',', $request->string('team_roster')->value())
            : (array) $request->input('pokemon', []);

        foreach ($submittedPokemon as $slot => $identifier) {
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
        abort_if(
            Battle::whereIn('status', ['waiting', 'active'])
                ->where(fn ($query) => $query->where('host_team_id', $team->id)->orWhere('guest_team_id', $team->id))
                ->exists(),
            409,
            __('ui.team_in_active_battle'),
        );
        $team->delete();

        return back()->with('success', __('ui.team_deleted'));
    }
}
