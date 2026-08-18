@extends('layouts.app')

@section('content')
@php($hubCollisionConfig = json_decode(file_get_contents(public_path('data/hub-collisions.json')), true))
<section id="world-hub" class="world-hub" tabindex="0" aria-label="{{ __('ui.map_label') }}"
         data-solo="{{ route('battle.setup', 'solo') }}"
         data-local="{{ route('battle.setup', 'local') }}"
         data-online="{{ auth()->check() ? route('battle.lobby') : route('login', ['next' => 'online']) }}"
         data-collision-config='@json($hubCollisionConfig)'>
    <img class="hub-map" src="{{ asset('images/hub-map-v2.png') }}" alt="">
    <button type="button" class="map-settings-button" id="settings-open" aria-label="{{ __('ui.settings') }}" title="{{ __('ui.settings') }} (P)">
        <img src="{{ asset('images/settings-icon.png') }}" alt=""><kbd>P</kbd>
    </button>
    <button class="map-destination destination-solo" data-destination="solo"><strong><span>01</span>{{ __('ui.solo') }}</strong></button>
    <button class="map-destination destination-local" data-destination="local"><strong><span>02</span>{{ __('ui.local') }}</strong></button>
    <button class="map-destination destination-online" data-destination="online"><strong><span>03</span>{{ __('ui.online') }}</strong></button>
    <div id="hub-character" class="hub-character face-up" role="img" aria-label="{{ __('ui.trainer') }}"></div>
    <div id="hub-prompt" class="hub-prompt" aria-live="polite">{{ __('ui.walk_hint') }}</div>
    <dialog id="game-space-dialog" class="game-space-dialog" aria-label="{{ __('ui.game_space') }}">
        <button type="button" class="game-space-close" data-game-close aria-label="{{ __('ui.close') }}">×</button>
        <iframe id="game-space-frame" title="{{ __('ui.game_space') }}"></iframe>
    </dialog>
</section>
@endsection
