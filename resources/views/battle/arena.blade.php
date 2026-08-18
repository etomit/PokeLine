@extends('layouts.app')
@section('title', __('ui.battle').' — '.__('ui.title'))
@section('content')
<section class="arena-wrap">
    @php
        $battleTranslations = [
            'turn' => __('ui.turn'), 'choose' => __('ui.choose_attack'),
            'waiting' => __('ui.waiting_opponent'), 'victory' => __('ui.victory'),
            'defeat' => __('ui.defeat'), 'sound' => __('ui.sound'), 'rewards' => __('ui.rewards'),
            'switch' => __('ui.switch_pokemon'), 'weather' => __('ui.weather'),
            'types' => __('types'),
        ];
    @endphp
    @if($kind === 'online' && $battle->status === 'waiting')
        <div class="waiting-card screen-panel"><div class="signal-loader"><i></i><i></i><i></i></div><p>{{ __('ui.waiting') }}</p><strong class="room-code">{{ $battle->code }}</strong><small>{{ __('ui.share_code') }}</small></div>
    @endif
    <div id="battle-app" class="battle-app arena-{{ $mode }} {{ $kind === 'online' && $battle->status === 'waiting' ? 'is-waiting' : '' }}"
         data-kind="{{ $kind }}"
         data-mode="{{ $mode }}"
         data-state-url="{{ $kind === 'online' ? route('battle.online.state', $battle) : route('battle.session.state') }}"
         data-action-url="{{ $kind === 'online' ? route('battle.online.action', $battle) : route('battle.session.action') }}"
         data-translations='@json($battleTranslations)'>
        <div class="battle-toolbar"><span id="turn-label">{{ __('ui.turn') }} 1</span><span id="weather-label"></span><button id="sound-toggle" type="button">🔊 {{ __('ui.sound') }}</button></div>
        <div class="battle-screen">
            <div class="scanlines"></div>
            <div id="opponent-hud" class="combatant-hud opponent"></div>
            <div class="sprite-platform opponent-platform"><img id="opponent-sprite" alt=""></div>
            <div class="sprite-platform player-platform"><img id="player-sprite" alt=""></div>
            <div id="player-hud" class="combatant-hud player"></div>
        </div>
        <div class="command-panel"><div id="battle-message" class="battle-message">{{ __('ui.choose_attack') }}</div><div id="moves" class="moves-grid"></div></div>
        <div id="local-controls" class="local-controls">
            <section><strong>{{ __('ui.player_one') }}</strong><div id="local-moves-p1" class="moves-grid"></div></section>
            <section><strong>{{ __('ui.player_two') }}</strong><div id="local-moves-p2" class="moves-grid"></div></section>
        </div>
        <div id="battle-log" class="battle-log" aria-live="polite"></div>
    </div>
</section>
@endsection
