@extends('layouts.app')
@section('title', __('ui.battle').' — '.__('ui.title'))
@section('content')
<section class="arena-wrap">
    @php
        $battleTranslations = [
            'turn' => __('ui.turn'), 'choose' => __('ui.choose_attack'),
            'waiting' => __('ui.waiting_opponent'), 'victory' => __('ui.victory'),
            'defeat' => __('ui.defeat'), 'sound' => __('ui.sound'), 'music' => __('ui.music'), 'rewards' => __('ui.rewards'),
            'musicVolume' => __('ui.music_volume'), 'effectsVolume' => __('ui.effects_volume'),
            'switch' => __('ui.switch_pokemon'), 'weather' => __('ui.weather'),
            'types' => __('types'),
            'hp' => __('ui.hp'),
            'chooseReplacement' => __('ui.choose_replacement'),
            'waitingReplacement' => __('ui.waiting_replacement'),
            'victoryMessage' => __('ui.victory_message'),
            'defeatMessage' => __('ui.defeat_message'),
            'spectating' => __('ui.spectating'),
            'battleFinished' => __('ui.battle_finished'),
            'winnerMessage' => __('ui.winner_message'),
            'liveSpectator' => __('ui.live_spectator'),
            'connected' => __('ui.websocket_connected'),
            'reconnecting' => __('ui.websocket_reconnecting'),
            'unavailable' => __('ui.websocket_unavailable'),
            'opponentDisconnected' => __('ui.opponent_disconnected'),
            'autoWinCountdown' => __('ui.auto_win_countdown'),
        ];
        $spectator = $kind === 'spectator';
        $stateUrl = match ($kind) {
            'online' => route('battle.online.state', $battle),
            'spectator' => route('battle.spectate.state', $battle),
            default => route('battle.session.state'),
        };
        $actionUrl = $kind === 'online' ? route('battle.online.action', $battle) : ($kind === 'session' ? route('battle.session.action') : '');
        $heartbeatUrl = $kind === 'online' ? route('battle.online.heartbeat', $battle) : '';
        $you = $kind === 'online' ? ($battle->host_id === auth()->id() ? 'p1' : 'p2') : 'p1';
    @endphp
    @if($kind === 'online' && $battle->status === 'waiting')
        <div class="waiting-card screen-panel">
            <div class="signal-loader"><i></i><i></i><i></i></div>
            <p>{{ $battle->mode === 'online-private' ? __('ui.waiting_invited_player') : __('ui.searching_opponent') }}</p>
            @if($battle->mode === 'online-private')<strong class="room-code">{{ $battle->code }}</strong><small>{{ __('ui.share_code') }}</small>@else<div class="radar-animation large"><i></i></div><small>{{ __('ui.public_search_active') }}</small>@endif
            <form action="{{ route('battle.online.cancel', $battle) }}" method="post">@csrf @method('DELETE')<button class="online-danger" type="submit">{{ __('ui.cancel_search') }}</button></form>
        </div>
    @endif
    <div id="battle-app" class="battle-app arena-{{ $mode }} {{ $mode === 'local' ? 'local-mode' : '' }} {{ $kind === 'online' && $battle->status === 'waiting' ? 'is-waiting' : '' }} {{ $spectator ? 'is-spectator' : '' }}"
         data-kind="{{ $kind }}"
         data-mode="{{ $mode }}"
         data-state-url="{{ $stateUrl }}"
         data-action-url="{{ $actionUrl }}"
         data-heartbeat-url="{{ $heartbeatUrl }}"
         data-channel="{{ $battle?->public_id }}"
         data-you="{{ $you }}"
         data-user-id="{{ auth()->id() }}"
         data-translations='@json($battleTranslations)'>
        <div class="battle-toolbar">
            <span id="turn-label">{{ __('ui.turn') }} 1</span>
            <span id="weather-label">{{ $spectator ? __('ui.live_spectator') : '' }}</span>
            @if(in_array($kind, ['online', 'spectator'], true))<span id="connection-label" class="connection-label is-connecting">{{ __('ui.websocket_reconnecting') }}</span>@endif
            <div class="battle-audio-controls">
                @if($kind === 'online' && $battle->status === 'active')
                    <form action="{{ route('battle.online.forfeit', $battle) }}" method="post">@csrf<button class="battle-forfeit" type="submit">{{ __('ui.forfeit') }}</button></form>
                @endif
                <div class="battle-volume-control">
                    <button id="music-toggle" type="button">🎵 {{ __('ui.music') }}</button>
                    <label title="{{ __('ui.music_volume') }}"><input id="battle-music-volume" type="range" min="0" max="100" step="5" aria-label="{{ __('ui.music_volume') }}"></label>
                </div>
                <div class="battle-volume-control">
                    <button id="sound-toggle" type="button">🔊 {{ __('ui.sound') }}</button>
                    <label title="{{ __('ui.effects_volume') }}"><input id="battle-sound-volume" type="range" min="0" max="100" step="5" aria-label="{{ __('ui.effects_volume') }}"></label>
                </div>
            </div>
        </div>
        <div class="battle-screen">
            <div class="scanlines"></div>
            <div id="attack-effects" class="attack-effects" aria-hidden="true"></div>
            <div id="opponent-hud" class="combatant-hud opponent"></div>
            <div class="sprite-platform opponent-platform"><img id="opponent-sprite" crossorigin="anonymous" alt=""></div>
            <div class="sprite-platform player-platform"><img id="player-sprite" crossorigin="anonymous" alt=""></div>
            <div id="player-hud" class="combatant-hud player"></div>
        </div>
        <div class="command-panel"><div id="battle-message" class="battle-message">{{ __('ui.choose_attack') }}</div><div id="moves" class="moves-grid"></div></div>
        <div id="local-controls" class="local-controls">
            <section><strong>{{ __('ui.player_one') }}</strong><div id="local-moves-p1" class="moves-grid"></div></section>
            <section><strong>{{ __('ui.player_two') }}</strong><div id="local-moves-p2" class="moves-grid"></div></section>
        </div>
        <div id="battle-log" class="battle-log" aria-live="polite"></div>
        <section id="battle-result" class="battle-result" hidden>
            <div class="result-emblem" aria-hidden="true"><i></i></div>
            <p class="eyebrow">BATTLE RESULT</p>
            <h2 data-result-title></h2>
            <p data-result-message></p>
            <p class="result-rewards" data-result-rewards hidden></p>
            <a class="pixel-button" href="{{ in_array($kind, ['online', 'spectator'], true) ? route('battle.lobby') : route('home') }}">{{ in_array($kind, ['online', 'spectator'], true) ? __('ui.return_to_online_center') : __('ui.return_to_menu') }}</a>
        </section>
    </div>
</section>
@endsection
