<?php

namespace App\Http\Controllers;

use App\Events\BattleUpdated;
use App\Models\Battle;
use App\Models\InventoryItem;
use App\Models\Item;
use App\Models\Team;
use App\Models\User;
use App\Services\BattleEngine;
use App\Services\PokeApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

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
            'items1' => ['nullable', 'array', 'max:6'],
            'items1.*' => ['nullable', 'string', 'exists:items,slug'],
            'items2' => ['nullable', 'array', 'max:6'],
            'items2.*' => ['nullable', 'string', 'exists:items,slug'],
        ]);
        $firstIds = $this->parseTeam($data['team1']);
        $secondIds = $mode === 'solo'
            ? collect(self::STARTERS)->shuffle()->take(count($firstIds))->all()
            : $this->parseTeam($data['team2']);
        $first = array_map(fn ($id) => $pokeApi->pokemon($id), $firstIds);
        $second = array_map(fn ($id) => $pokeApi->pokemon($id), $secondIds);
        $first = $this->equipSandboxItems($first, $data['items1'] ?? []);
        $second = $this->equipSandboxItems($second, $data['items2'] ?? []);
        $state = $engine->createState($first, $second, [$mode === 'solo' ? __('ui.battle_trainer') : __('ui.player_one'), $mode === 'solo' ? __('ui.ai_name') : __('ui.player_two')]);
        $request->session()->put('battle', ['mode' => $mode, 'state' => $state, 'pending' => null]);

        return redirect()->route('battle.session.show');
    }

    public function sessionShow(Request $request)
    {
        abort_unless($request->session()->has('battle'), 404);

        return view('battle.arena', ['kind' => 'session', 'mode' => $request->session()->get('battle.mode'), 'battle' => null]);
    }

    public function sessionState(Request $request): JsonResponse
    {
        abort_unless($request->session()->has('battle'), 404);

        return response()->json($request->session()->get('battle'));
    }

    public function sessionAction(Request $request, BattleEngine $engine): JsonResponse
    {
        $data = $this->validateAction($request);
        $battle = $request->session()->get('battle');
        abort_unless($battle && ($battle['state']['phase'] ?? null) === 'active', 409);

        $forced = array_keys(array_filter($battle['state']['forced_switch'] ?? []));
        if ($forced !== []) {
            $key = $battle['mode'] === 'solo' ? 'p1' : $forced[0];
            abort_unless(in_array($key, $forced, true) && $data['action_type'] === 'switch', 409, __('ui.choose_replacement'));
            $battle['state'] = $engine->resolveForcedSwitch($battle['state'], $key, (int) $data['pokemon_index']);
            $battle['pending'] = null;
            $request->session()->put('battle', $battle);

            return response()->json($battle);
        }

        if ($battle['mode'] === 'solo') {
            $aiMove = $engine->chooseAiMove($battle['state']);
            $battle['state'] = $engine->resolveTurn($battle['state'], ['p1' => $data, 'p2' => ['action_type' => 'move', 'move_index' => $aiMove]]);
            if ($battle['state']['forced_switch']['p2'] ?? false) {
                $battle['state'] = $engine->resolveForcedSwitch(
                    $battle['state'],
                    'p2',
                    $this->firstAvailableSwitch($battle['state'], 'p2'),
                    true,
                );
            }
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
        $userId = $request->user()->id;

        return view('battle.lobby', [
            'teams' => $request->user()->teams()->with('pokemon')->get(),
            'battles' => Battle::with(['host', 'hostTeam.pokemon'])
                ->where('status', 'waiting')
                ->where('mode', 'online-public')
                ->where('host_id', '!=', $request->user()->id)
                ->oldest()
                ->get(),
            'liveBattles' => Battle::with(['host', 'guest', 'hostTeam.pokemon', 'guestTeam.pokemon'])
                ->where('status', 'active')
                ->where('mode', 'online-public')
                ->latest('updated_at')
                ->limit(12)
                ->get(),
            'myBattles' => Battle::with(['host', 'guest', 'hostTeam.pokemon', 'guestTeam.pokemon'])
                ->whereIn('status', ['waiting', 'active'])
                ->where(fn ($query) => $query->where('host_id', $userId)->orWhere('guest_id', $userId))
                ->latest('updated_at')
                ->get(),
            'recentBattles' => Battle::with(['host', 'guest', 'winner'])
                ->where('status', 'finished')
                ->where(fn ($query) => $query->where('host_id', $userId)->orWhere('guest_id', $userId))
                ->latest('finished_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function createOnline(Request $request, BattleEngine $engine)
    {
        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'queue_type' => ['nullable', 'in:public,private'],
        ]);
        $team = $request->user()->teams()->findOrFail($data['team_id']);
        abort_if($team->pokemon()->count() === 0, 422);
        $queueType = $data['queue_type'] ?? 'public';

        if ($currentBattle = $this->openBattleFor($request->user()->id)) {
            return redirect()->route('battle.online.show', $currentBattle);
        }

        if ($queueType === 'public') {
            $battle = DB::transaction(function () use ($request, $team, $engine) {
                $waiting = Battle::where('status', 'waiting')
                    ->where('mode', 'online-public')
                    ->where('host_id', '!=', $request->user()->id)
                    ->oldest()
                    ->lockForUpdate()
                    ->first();

                if ($waiting) {
                    $this->connectGuest($waiting, $team, $request->user(), $engine);

                    return $waiting;
                }

                return $this->createWaitingBattle($request->user()->id, $team->id, 'online-public');
            });

            return redirect()->route('battle.online.show', $battle);
        }

        $battle = $this->createWaitingBattle($request->user()->id, $team->id, 'online-private');

        return redirect()->route('battle.online.show', $battle);
    }

    private function createWaitingBattle(int $userId, int $teamId, string $mode): Battle
    {
        return Battle::create([
            'public_id' => (string) Str::uuid(),
            'code' => $this->uniqueCode(),
            'host_id' => $userId,
            'host_team_id' => $teamId,
            'status' => 'waiting',
            'mode' => $mode,
            'host_last_seen_at' => now(),
        ]);
    }

    public function joinOnline(Request $request, BattleEngine $engine, ?Battle $battle = null)
    {
        $data = $request->validate([
            'team_id' => ['required', 'exists:teams,id'],
            'code' => [$battle ? 'nullable' : 'required', 'string', 'max:8'],
        ]);
        $team = $request->user()->teams()->findOrFail($data['team_id']);
        $battle ??= Battle::where('code', strtoupper($data['code']))->where('mode', 'online-private')->firstOrFail();

        if ($currentBattle = $this->openBattleFor($request->user()->id)) {
            return redirect()->route('battle.online.show', $currentBattle);
        }

        DB::transaction(function () use ($request, $team, $battle, $engine) {
            $locked = Battle::whereKey($battle->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'waiting' || $locked->host_id === $request->user()->id) {
                throw ValidationException::withMessages(['code' => __('ui.room_unavailable')]);
            }
            $this->connectGuest($locked, $team, $request->user(), $engine);
        });

        return redirect()->route('battle.online.show', $battle);
    }

    public function onlineShow(Request $request, Battle $battle)
    {
        $this->authorizeParticipant($request, $battle);
        $this->touchPresence($battle, $request->user()->id);

        return view('battle.arena', ['kind' => 'online', 'mode' => 'online', 'battle' => $battle]);
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

    public function spectate(Battle $battle)
    {
        $this->authorizeSpectatorBattle($battle);

        return view('battle.arena', ['kind' => 'spectator', 'mode' => 'online', 'battle' => $battle]);
    }

    public function spectatorState(Battle $battle): JsonResponse
    {
        $this->authorizeSpectatorBattle($battle);
        $battle->refresh();

        return response()->json([
            'status' => $battle->status,
            'state' => $battle->state,
            'version' => $battle->version,
            'mode' => 'online',
            'you' => 'p1',
            'submitted' => false,
            'spectator' => true,
        ]);
    }

    public function cancelOnline(Request $request, Battle $battle)
    {
        abort_unless($battle->host_id === $request->user()->id, 403);

        DB::transaction(function () use ($battle) {
            $locked = Battle::whereKey($battle->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'waiting', 409);
            $locked->delete();
        });

        return redirect()->route('battle.lobby')->with('success', __('ui.search_cancelled'));
    }

    public function forfeitOnline(Request $request, Battle $battle)
    {
        $this->authorizeParticipant($request, $battle);

        DB::transaction(function () use ($request, $battle) {
            $locked = Battle::whereKey($battle->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'active' && $locked->guest_id !== null, 409);

            $loser = $locked->host_id === $request->user()->id ? 'p1' : 'p2';
            $winner = $loser === 'p1' ? 'p2' : 'p1';
            $winnerId = $winner === 'p1' ? $locked->host_id : $locked->guest_id;
            $state = $locked->state;
            $trainerName = $state['players'][$loser]['name'] ?? $request->user()->name;
            $event = ['type' => 'finish', 'winner' => $winner, 'text' => __('battle.forfeit', ['player' => $trainerName])];
            $state['phase'] = 'finished';
            $state['winner'] = $winner;
            $state['forced_switch'] = [];
            $state['last_events'] = [$event];
            $state['log'] = array_slice(array_merge($state['log'] ?? [], [$event['text']]), -40);

            $locked->update([
                'status' => 'finished',
                'state' => $state,
                'pending_actions' => [],
                'winner_id' => $winnerId,
                'version' => $locked->version + 1,
                'rewards' => $this->grantRewards($locked, $winnerId),
                'finished_at' => now(),
            ]);
        });

        $this->broadcastBattle($battle->fresh());

        return redirect()->route('battle.lobby')->with('success', __('ui.battle_forfeited'));
    }

    public function heartbeatOnline(Request $request, Battle $battle): JsonResponse
    {
        $this->authorizeParticipant($request, $battle);
        $finishedByTimeout = false;
        $opponentMissingFor = 0;

        DB::transaction(function () use ($request, $battle, &$finishedByTimeout, &$opponentMissingFor) {
            $locked = Battle::whereKey($battle->id)->lockForUpdate()->firstOrFail();
            $side = $locked->host_id === $request->user()->id ? 'p1' : 'p2';
            $currentField = $side === 'p1' ? 'host_last_seen_at' : 'guest_last_seen_at';
            $opponentField = $side === 'p1' ? 'guest_last_seen_at' : 'host_last_seen_at';
            $cutoff = now()->subSeconds(90);
            $currentWasPresent = $locked->{$currentField}?->greaterThan($cutoff) ?? false;
            $opponentLastSeen = $locked->{$opponentField};

            if (in_array($locked->status, ['waiting', 'active'], true)) {
                $locked->forceFill([$currentField => now()])->save();
            }

            if ($opponentLastSeen) {
                $opponentMissingFor = max(0, (int) $opponentLastSeen->diffInSeconds(now()));
            }

            if ($locked->status === 'active' && $currentWasPresent && (! $opponentLastSeen || $opponentLastSeen->lessThanOrEqualTo($cutoff))) {
                $this->finishDisconnectedBattle($locked, $side);
                $finishedByTimeout = true;
            }
        });

        $battle->refresh();
        if ($finishedByTimeout) {
            $this->broadcastBattle($battle);
        }

        return response()->json([
            'status' => $battle->status,
            'opponent_missing_for' => $opponentMissingFor,
            'timeout' => 90,
            'battle' => $finishedByTimeout ? $this->broadcastPayload($battle) : null,
        ]);
    }

    public function onlineAction(Request $request, Battle $battle, BattleEngine $engine): JsonResponse
    {
        $this->authorizeParticipant($request, $battle);
        $data = $this->validateAction($request);

        DB::transaction(function () use ($request, $battle, $data, $engine) {
            $locked = Battle::whereKey($battle->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'active', 409);
            $key = $locked->host_id === $request->user()->id ? 'p1' : 'p2';
            $presenceField = $key === 'p1' ? 'host_last_seen_at' : 'guest_last_seen_at';
            $locked->forceFill([$presenceField => now()])->save();
            $forced = array_filter($locked->state['forced_switch'] ?? []);
            if ($forced !== []) {
                abort_unless(($forced[$key] ?? false) && $data['action_type'] === 'switch', 409, __('ui.choose_replacement'));
                $state = $engine->resolveForcedSwitch($locked->state, $key, (int) $data['pokemon_index']);
                $locked->update(['state' => $state, 'pending_actions' => [], 'version' => $locked->version + 1]);

                return;
            }
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

        $this->broadcastBattle($battle->fresh());

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

    private function connectGuest(Battle $battle, Team $team, User $guest, BattleEngine $engine): void
    {
        $battle->load(['hostTeam.pokemon.heldItem', 'host']);
        $pokeApi = app(PokeApiService::class);
        $state = $engine->createState(
            array_map($pokeApi->localizeSnapshot(...), $engine->teamSnapshots($battle->hostTeam)),
            array_map($pokeApi->localizeSnapshot(...), $engine->teamSnapshots($team->load('pokemon.heldItem'))),
            [$battle->host->name, $guest->name],
        );
        $battle->update([
            'guest_id' => $guest->id,
            'guest_team_id' => $team->id,
            'status' => 'active',
            'state' => $state,
            'pending_actions' => [],
            'host_last_seen_at' => now(),
            'guest_last_seen_at' => now(),
        ]);
        DB::afterCommit(fn () => $this->broadcastBattle($battle->fresh()));
    }

    private function touchPresence(Battle $battle, int $userId): void
    {
        if (! in_array($battle->status, ['waiting', 'active'], true)) {
            return;
        }

        $field = $battle->host_id === $userId ? 'host_last_seen_at' : 'guest_last_seen_at';
        $battle->forceFill([$field => now()])->save();
    }

    private function finishDisconnectedBattle(Battle $battle, string $winner): void
    {
        $winnerId = $winner === 'p1' ? $battle->host_id : $battle->guest_id;
        $loser = $winner === 'p1' ? 'p2' : 'p1';
        $state = $battle->state;
        $winnerName = $state['players'][$winner]['name'] ?? __('ui.battle_trainer');
        $loserName = $state['players'][$loser]['name'] ?? __('ui.battle_trainer');
        $event = [
            'type' => 'finish',
            'winner' => $winner,
            'text' => __('battle.disconnected', ['loser' => $loserName, 'winner' => $winnerName]),
        ];
        $state['phase'] = 'finished';
        $state['winner'] = $winner;
        $state['forced_switch'] = [];
        $state['last_events'] = [$event];
        $state['log'] = array_slice(array_merge($state['log'] ?? [], [$event['text']]), -40);

        $battle->update([
            'status' => 'finished',
            'state' => $state,
            'pending_actions' => [],
            'winner_id' => $winnerId,
            'version' => $battle->version + 1,
            'rewards' => $this->grantRewards($battle, $winnerId),
            'finished_at' => now(),
        ]);
    }

    private function broadcastPayload(Battle $battle): array
    {
        $pending = $battle->pending_actions ?? [];

        return [
            'status' => $battle->status,
            'state' => $battle->state,
            'version' => $battle->version,
            'rewards' => $battle->rewards,
            'submitted' => ['p1' => isset($pending['p1']), 'p2' => isset($pending['p2'])],
        ];
    }

    private function broadcastBattle(Battle $battle): void
    {
        try {
            BattleUpdated::dispatch($battle);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function validateAction(Request $request): array
    {
        $data = $request->validate([
            'action_type' => ['nullable', 'in:move,switch'],
            'move_index' => ['nullable', 'integer', 'between:0,3'],
            'pokemon_index' => ['nullable', 'integer', 'between:0,5'],
        ]);
        $data['action_type'] ??= isset($data['pokemon_index']) ? 'switch' : 'move';
        if ($data['action_type'] === 'switch' && ! isset($data['pokemon_index'])) {
            throw ValidationException::withMessages(['pokemon_index' => __('ui.switch_pokemon')]);
        }
        if ($data['action_type'] === 'move' && ! isset($data['move_index'])) {
            throw ValidationException::withMessages(['move_index' => __('ui.choose_attack')]);
        }

        return $data;
    }

    private function parseTeam(string $value): array
    {
        $ids = collect(explode(',', $value))->map(fn ($id) => strtolower(trim($id)))->filter()->values()->all();
        if (count($ids) < 1 || count($ids) > 6) {
            throw ValidationException::withMessages(['team1' => __('ui.team_size')]);
        }

        return $ids;
    }

    private function equipSandboxItems(array $team, array $slugs): array
    {
        $slugs = collect($slugs)->map(fn ($slug) => strtolower(trim((string) $slug)))->values();
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

    private function firstAvailableSwitch(array $state, string $key): int
    {
        foreach ($state['players'][$key]['roster'] as $index => $pokemon) {
            if ($index !== $state['players'][$key]['active'] && $pokemon['current_hp'] > 0) {
                return $index;
            }
        }

        return $state['players'][$key]['active'];
    }

    private function authorizeParticipant(Request $request, Battle $battle): void
    {
        abort_unless(in_array($request->user()->id, [$battle->host_id, $battle->guest_id], true), 403);
    }

    private function authorizeSpectatorBattle(Battle $battle): void
    {
        abort_unless(
            $battle->mode === 'online-public' && in_array($battle->status, ['active', 'finished'], true),
            404,
        );
    }

    private function openBattleFor(int $userId): ?Battle
    {
        return Battle::whereIn('status', ['waiting', 'active'])
            ->where(fn ($query) => $query->where('host_id', $userId)->orWhere('guest_id', $userId))
            ->latest('updated_at')
            ->first();
    }

    private function uniqueCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Battle::where('code', $code)->exists());

        return $code;
    }
}
