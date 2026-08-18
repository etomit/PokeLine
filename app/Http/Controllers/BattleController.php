<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Services\BattleEngine;
use App\Services\PokeApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BattleController extends Controller
{
    private const STARTERS = ['venusaur', 'charizard', 'blastoise', 'pikachu', 'alakazam', 'gengar', 'dragonite', 'tyranitar', 'lucario', 'garchomp'];

    public function setup(string $mode)
    {
        abort_unless(in_array($mode, ['solo', 'local'], true), 404);

        return view('battle.setup', ['mode' => $mode, 'items' => Item::orderBy('name')->get()]);
    }

    public function startSession(Request $request, string $mode, PokeApiService $pokeApi, BattleEngine $engine)
    {
        abort_unless(in_array($mode, ['solo', 'local'], true), 404);
        $data = $request->validate([
            'team1' => ['required', 'string', 'max:200'],
            'team2' => [$mode === 'local' ? 'required' : 'nullable', 'string', 'max:200'],
            'items1' => ['nullable', 'string', 'max:200'],
            'items2' => ['nullable', 'string', 'max:200'],
        ]);
        $firstIds = $this->parseTeam($data['team1']);
        $secondIds = $mode === 'solo'
            ? collect(self::STARTERS)->shuffle()->take(count($firstIds))->all()
            : $this->parseTeam($data['team2']);
        $first = array_map(fn ($id) => $pokeApi->pokemon($id), $firstIds);
        $second = array_map(fn ($id) => $pokeApi->pokemon($id), $secondIds);
        $first = $this->equipSandboxItems($first, $data['items1'] ?? '');
        $second = $this->equipSandboxItems($second, $data['items2'] ?? '');
        $state = $engine->createState($first, $second, [$mode === 'solo' ? __('ui.battle_trainer') : __('ui.player_one'), $mode === 'solo' ? __('ui.ai_name') : __('ui.player_two')]);
        $request->session()->put('battle', ['mode' => $mode, 'state' => $state, 'pending' => null]);

        return redirect()->route('battle.session.show');
    }

    public function sessionShow(Request $request)
    {
        abort_unless($request->session()->has('battle'), 404);

        return view('battle.arena', ['kind' => 'session', 'battle' => null]);
    }

    public function sessionState(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('battle'), 404);

        return response()->json($request->session()->get('battle'));
    }

    public function sessionAction(Request $request, BattleEngine $engine): JsonResponse
    {
        $data = $request->validate(['move_index' => ['required', 'integer', 'between:0,3']]);
        $battle = $request->session()->get('battle');
        abort_unless($battle && ($battle['state']['phase'] ?? null) === 'active', 409);

        if ($battle['mode'] === 'solo') {
            $aiMove = $engine->chooseAiMove($battle['state']);
            $battle['state'] = $engine->resolveTurn($battle['state'], ['p1' => $data, 'p2' => ['move_index' => $aiMove]]);
        } elseif ($battle['pending'] === null) {
            $battle['pending'] = $data;
            $battle['state']['last_events'] = [['type' => 'handoff', 'text' => __('ui.handoff')]];
        } else {
            $battle['state'] = $engine->resolveTurn($battle['state'], ['p1' => $battle['pending'], 'p2' => $data]);
            $battle['pending'] = null;
        }
        $request->session()->put('battle', $battle);

        return response()->json($battle);
    }

    public function lobby(Request $request)
    {
        return view('battle.lobby', [
            'teams' => $request->user()->teams()->with('pokemon')->get(),
            'battles' => Battle::with('host')->where('status', 'waiting')->where('host_id', '!=', $request->user()->id)->latest()->get(),
        ]);
    }

    public function createOnline(Request $request)
    {
        $data = $request->validate(['team_id' => ['required', 'exists:teams,id']]);
        $team = $request->user()->teams()->findOrFail($data['team_id']);
        abort_if($team->pokemon()->count() === 0, 422);
        $battle = Battle::create([
            'public_id' => (string) Str::uuid(),
            'code' => $this->uniqueCode(),
            'host_id' => $request->user()->id,
            'host_team_id' => $team->id,
            'status' => 'waiting',
            'mode' => 'online',
        ]);

        return redirect()->route('battle.online.show', $battle);
    }

    public function joinOnline(Request $request, BattleEngine $engine, ?Battle $battle = null)
    {
        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'code' => [$battle ? 'nullable' : 'required', 'string', 'max:8'],
        ]);
        $team = $request->user()->teams()->findOrFail($data['team_id']);
        $battle ??= Battle::where('code', strtoupper($data['code']))->firstOrFail();

        DB::transaction(function () use ($request, $team, $battle, $engine) {
            $locked = Battle::whereKey($battle->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'waiting' || $locked->host_id === $request->user()->id) {
                throw ValidationException::withMessages(['code' => __('ui.room_unavailable')]);
            }
            $locked->load(['hostTeam.pokemon.heldItem', 'guestTeam', 'host']);
            $state = $engine->createState(
                $engine->teamSnapshots($locked->hostTeam),
                $engine->teamSnapshots($team->load('pokemon.heldItem')),
                [$locked->host->name, $request->user()->name],
            );
            $locked->update(['guest_id' => $request->user()->id, 'guest_team_id' => $team->id, 'status' => 'active', 'state' => $state, 'pending_actions' => []]);
        });

        return redirect()->route('battle.online.show', $battle);
    }

    public function onlineShow(Request $request, Battle $battle)
    {
        $this->authorizeParticipant($request, $battle);

        return view('battle.arena', ['kind' => 'online', 'battle' => $battle]);
    }

    public function onlineState(Request $request, Battle $battle): JsonResponse
    {
        $this->authorizeParticipant($request, $battle);
        $battle->refresh();
        $you = $battle->host_id === $request->user()->id ? 'p1' : 'p2';

        return response()->json([
            'status' => $battle->status,
            'state' => $battle->state,
            'version' => $battle->version,
            'rewards' => $battle->rewards,
            'reward' => ($battle->rewards ?? [])[(string) $request->user()->id] ?? [],
            'you' => $you,
            'submitted' => isset(($battle->pending_actions ?? [])[$you]),
        ]);
    }

    public function onlineAction(Request $request, Battle $battle, BattleEngine $engine): JsonResponse
    {
        $this->authorizeParticipant($request, $battle);
        $data = $request->validate(['move_index' => ['required', 'integer', 'between:0,3']]);

        DB::transaction(function () use ($request, $battle, $data, $engine) {
            $locked = Battle::whereKey($battle->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'active', 409);
            $key = $locked->host_id === $request->user()->id ? 'p1' : 'p2';
            $pending = $locked->pending_actions ?? [];
            abort_if(isset($pending[$key]), 409, __('ui.action_already_submitted'));
            $pending[$key] = $data;
            $updates = ['pending_actions' => $pending];
            if (isset($pending['p1'], $pending['p2'])) {
                $state = $engine->resolveTurn($locked->state, $pending);
                $updates = ['state' => $state, 'pending_actions' => [], 'turn' => $state['turn'], 'version' => $locked->version + 1];
                if ($state['phase'] === 'finished') {
                    $winnerId = $state['winner'] === 'p1' ? $locked->host_id : $locked->guest_id;
                    $updates += ['status' => 'finished', 'winner_id' => $winnerId, 'finished_at' => now()];
                    $updates['rewards'] = $this->grantRewards($locked, $winnerId);
                }
            }
            $locked->update($updates);
        });

        return $this->onlineState($request, $battle);
    }

    private function grantRewards(Battle $battle, int $winnerId): array
    {
        $items = Item::all();
        $rewards = [];
        foreach ([$battle->host_id, $battle->guest_id] as $userId) {
            $count = $userId === $winnerId ? random_int(1, 3) : random_int(0, 2);
            $picked = $items->shuffle()->take($count);
            $rewards[(string) $userId] = $picked->map->display_name->all();
            foreach ($picked as $item) {
                $inventory = InventoryItem::firstOrNew(['user_id' => $userId, 'item_id' => $item->id]);
                $inventory->quantity = ($inventory->quantity ?? 0) + 1;
                $inventory->save();
            }
        }

        return $rewards;
    }

    private function parseTeam(string $value): array
    {
        $ids = collect(explode(',', $value))->map(fn ($id) => strtolower(trim($id)))->filter()->unique()->values()->all();
        if (count($ids) < 1 || count($ids) > 6) {
            throw ValidationException::withMessages(['team1' => __('ui.team_size')]);
        }

        return $ids;
    }

    private function equipSandboxItems(array $team, string $value): array
    {
        $slugs = collect(explode(',', $value))->map(fn ($slug) => strtolower(trim($slug)))->values();
        $valid = Item::all()->mapWithKeys(fn (Item $item) => [$item->slug => $item->display_name]);
        foreach ($team as $index => &$pokemon) {
            $slug = $slugs->get($index);
            if ($slug && $valid->has($slug)) {
                $pokemon['held_item'] = $slug;
                $pokemon['held_item_label'] = $valid[$slug];
            }
        }

        return $team;
    }

    private function authorizeParticipant(Request $request, Battle $battle): void
    {
        abort_unless(in_array($request->user()->id, [$battle->host_id, $battle->guest_id], true), 403);
    }

    private function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Battle::where('code', $code)->exists());

        return $code;
    }
}
