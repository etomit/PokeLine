@extends('layouts.app')
@section('title', __('ui.lobby').' — '.__('ui.title'))
@section('content')
<section class="union-room">
    <header class="union-room-head">
        <a href="{{ route('home') }}">◀ {{ __('ui.back_home') }}</a>
        <div><p class="eyebrow">GLOBAL TERMINAL // ONLINE</p><h1>{{ __('ui.online_center') }}</h1><p>{{ __('ui.online_center_intro') }}</p></div>
        <div class="connection-signal"><i></i><i></i><i></i><i></i></div>
    </header>

    @if($teams->isEmpty())
        <section class="union-empty"><div class="union-ball"></div><h2>{{ __('ui.no_team') }}</h2><a class="pixel-button" href="{{ route('home') }}">{{ __('ui.create_team') }}</a></section>
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
</section>
@endsection
