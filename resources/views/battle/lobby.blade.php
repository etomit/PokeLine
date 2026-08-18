@extends('layouts.app')
@section('title', __('ui.lobby').' — '.__('ui.title'))
@section('content')
<section class="union-room">
    <header class="union-room-head">
        <a href="{{ route('home') }}">◀ {{ __('ui.back_home') }}</a>
        <div><p class="eyebrow">GLOBAL TERMINAL // ONLINE</p><h1>{{ __('ui.online_center') }}</h1><p>{{ __('ui.online_center_intro') }}</p><a class="manage-teams-link" href="{{ route('teams.index') }}">{{ __('ui.manage_teams') }} ▶</a></div>
        <div class="connection-signal" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
    </header>

    @if(session('success'))
        <p class="online-flash">{{ session('success') }}</p>
    @endif

    @if($myBattles->isNotEmpty())
        <section class="online-trainers my-online-battles">
            <div class="online-trainers-head"><div><p class="eyebrow">LINK RESTORE</p><h2>{{ __('ui.my_online_battles') }}</h2></div><span>{{ $myBattles->count() }}</span></div>
            <div class="online-battle-grid">
                @foreach($myBattles as $currentBattle)
                    @php
                        $opponent = $currentBattle->host_id === auth()->id() ? $currentBattle->guest : $currentBattle->host;
                        $team = $currentBattle->host_id === auth()->id() ? $currentBattle->hostTeam : $currentBattle->guestTeam;
                    @endphp
                    <article class="online-battle-card {{ $currentBattle->status === 'active' ? 'is-live' : 'is-waiting' }}">
                        <span class="online-status"><i></i>{{ $currentBattle->status === 'active' ? __('ui.battle_in_progress') : __('ui.searching') }}</span>
                        <h3>{{ $opponent?->name ?? ($currentBattle->mode === 'online-private' ? __('ui.private_room') : __('ui.quick_battle')) }}</h3>
                        <p>{{ $team?->name }} · {{ $team?->pokemon?->count() ?? 0 }}/6</p>
                        @if($currentBattle->mode === 'online-private' && $currentBattle->status === 'waiting')
                            <strong class="mini-room-code">{{ $currentBattle->code }}</strong>
                        @endif
                        <div class="online-card-actions">
                            <a class="union-action secondary" href="{{ route('battle.online.show', $currentBattle) }}">{{ __('ui.resume_battle') }} ▶</a>
                            @if($currentBattle->status === 'waiting' && $currentBattle->host_id === auth()->id())
                                <form action="{{ route('battle.online.cancel', $currentBattle) }}" method="post">
                                    @csrf @method('DELETE')
                                    <button class="online-danger" type="submit">{{ __('ui.cancel_search') }}</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if($teams->isEmpty())
        <section class="union-empty"><div class="union-ball"></div><h2>{{ __('ui.no_team') }}</h2><a class="pixel-button" href="{{ route('teams.index') }}">{{ __('ui.create_team') }}</a></section>
    @else
        <div class="online-paths">
            <section class="online-path public-path">
                <div class="path-number">01</div>
                <div class="path-copy"><span class="online-chip"><i></i>{{ __('ui.players_online') }}</span><h2>{{ __('ui.quick_battle') }}</h2><p>{{ __('ui.quick_battle_desc') }}</p></div>
                <form action="{{ route('battle.online.create') }}" method="post">
                    @csrf
                    <input type="hidden" name="queue_type" value="public">
                    <label>{{ __('ui.choose_team') }}<select name="team_id">@foreach($teams as $team)<option value="{{ $team->id }}">{{ $team->name }} · {{ $team->pokemon->count() }}/6</option>@endforeach</select></label>
                    <button class="union-action"><span>⚡</span>{{ __('ui.find_opponent') }}</button>
                </form>
            </section>

            <section class="online-path private-path">
                <div class="path-number">02</div>
                <div class="path-copy"><span class="online-chip private">⌁ {{ __('ui.private_room') }}</span><h2>{{ __('ui.invitation_battle') }}</h2><p>{{ __('ui.invitation_battle_desc') }}</p></div>
                <div class="private-actions">
                    <form action="{{ route('battle.online.create') }}" method="post">
                        @csrf
                        <input type="hidden" name="queue_type" value="private">
                        <label>{{ __('ui.choose_team') }}<select name="team_id">@foreach($teams as $team)<option value="{{ $team->id }}">{{ $team->name }} · {{ $team->pokemon->count() }}/6</option>@endforeach</select></label>
                        <button class="union-action"><span>＋</span>{{ __('ui.create_invitation') }}</button>
                    </form>
                    <div class="or-divider"><span>{{ __('ui.or') }}</span></div>
                    <form action="{{ route('battle.online.join-code') }}" method="post">
                        @csrf
                        <label>{{ __('ui.invitation_code') }}<input class="code-input" name="code" maxlength="8" autocomplete="off" required placeholder="ABC123"></label>
                        <label>{{ __('ui.choose_team') }}<select name="team_id">@foreach($teams as $team)<option value="{{ $team->id }}">{{ $team->name }}</option>@endforeach</select></label>
                        <button class="union-action secondary"><span>▶</span>{{ __('ui.join') }}</button>
                    </form>
                </div>
            </section>
        </div>

        <section class="online-trainers">
            <div class="online-trainers-head"><div><p class="eyebrow">LIVE SEARCH</p><h2>{{ __('ui.available_trainers') }}</h2></div><span>{{ $battles->count() }} {{ __('ui.searching_now') }}</span></div>
            <div class="trainer-search-list">
                @forelse($battles as $room)
                    <form action="{{ route('battle.online.join', $room) }}" method="post" class="trainer-search-card">
                        @csrf
                        <div class="trainer-avatar">{{ strtoupper(mb_substr($room->host->name, 0, 1)) }}</div>
                        <div><strong>{{ $room->host->name }}</strong><small>{{ $room->hostTeam->name }} · {{ $room->hostTeam->pokemon->count() }}/6</small></div>
                        <span class="pulse-label"><i></i>{{ __('ui.searching') }}</span>
                        <select name="team_id">@foreach($teams as $team)<option value="{{ $team->id }}">{{ $team->name }}</option>@endforeach</select>
                        <button>{{ __('ui.challenge') }} ▶</button>
                    </form>
                @empty
                    <div class="no-trainers"><div class="radar-animation"><i></i></div><p>{{ __('ui.no_public_search') }}</p></div>
                @endforelse
            </div>
        </section>
    @endif

    <section class="online-trainers watch-center">
        <div class="online-trainers-head"><div><p class="eyebrow">BATTLE TV // LIVE</p><h2>{{ __('ui.watch_center') }}</h2><p>{{ __('ui.watch_center_intro') }}</p></div><span>{{ $liveBattles->count() }} LIVE</span></div>
        <div class="online-battle-grid">
            @forelse($liveBattles as $liveBattle)
                @php
                    $isParticipant = in_array(auth()->id(), [$liveBattle->host_id, $liveBattle->guest_id], true);
                @endphp
                <article class="online-battle-card is-live broadcast-card">
                    <span class="online-status"><i></i>{{ __('ui.live_battle') }} · {{ __('ui.turn') }} {{ $liveBattle->turn }}</span>
                    <h3>{{ $liveBattle->host->name }} <b>VS</b> {{ $liveBattle->guest->name }}</h3>
                    <p>{{ $liveBattle->hostTeam->name }} · {{ $liveBattle->guestTeam->name }}</p>
                    <a class="union-action secondary" href="{{ $isParticipant ? route('battle.online.show', $liveBattle) : route('battle.spectate', $liveBattle) }}">{{ $isParticipant ? __('ui.resume_battle') : __('ui.watch_battle') }} ▶</a>
                </article>
            @empty
                <div class="no-trainers"><div class="battle-tv-icon">TV</div><p>{{ __('ui.no_live_battles') }}</p></div>
            @endforelse
        </div>
    </section>

    <section class="online-trainers battle-history">
        <div class="online-trainers-head"><div><p class="eyebrow">TRAINER RECORD</p><h2>{{ __('ui.battle_history') }}</h2></div><span>{{ $recentBattles->count() }}/10</span></div>
        <div class="battle-history-list">
            @forelse($recentBattles as $pastBattle)
                @php
                    $opponent = $pastBattle->host_id === auth()->id() ? $pastBattle->guest : $pastBattle->host;
                    $won = $pastBattle->winner_id === auth()->id();
                @endphp
                <article class="history-row {{ $won ? 'won' : 'lost' }}">
                    <strong>{{ $won ? __('ui.victory_short') : __('ui.defeat_short') }}</strong>
                    <span>{{ __('ui.opponent') }} : {{ $opponent?->name ?? '—' }}</span>
                    <small>{{ $pastBattle->finished_at?->diffForHumans() }}</small>
                    <a href="{{ route('battle.online.show', $pastBattle) }}">{{ __('ui.review_battle') }} ▶</a>
                </article>
            @empty
                <p class="no-history">{{ __('ui.no_battle_history') }}</p>
            @endforelse
        </div>
    </section>
</section>
@endsection
