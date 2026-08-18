@extends('layouts.app')

@section('content')
<section class="hub-intro">
    <div><p class="eyebrow">BATTLE TOWN // LV.100</p><h1>{{ __('ui.title') }}</h1><p>{{ __('ui.tagline') }}</p></div>
    <div class="control-help"><span>↑ ↓ ← → / WASD / ZQSD</span><span>ENTER / E — {{ __('ui.enter') }}</span><span>P — {{ __('ui.settings') }}</span></div>
</section>

<section id="world-hub" class="world-hub" tabindex="0" aria-label="{{ __('ui.map_label') }}"
         data-solo="{{ route('battle.setup', 'solo') }}"
         data-local="{{ route('battle.setup', 'local') }}"
         data-online="{{ auth()->check() ? route('battle.lobby') : route('login') }}">
    <img class="hub-map" src="{{ asset('images/hub-map.png') }}" alt="">
    <button class="map-destination destination-solo" data-destination="solo"><span>01</span>{{ __('ui.solo') }}</button>
    <button class="map-destination destination-local" data-destination="local"><span>02</span>{{ __('ui.local') }}</button>
    <button class="map-destination destination-online" data-destination="online"><span>03</span>{{ __('ui.online') }}</button>
    <div id="hub-character" class="hub-character face-up" role="img" aria-label="{{ __('ui.trainer') }}"></div>
    <div id="hub-prompt" class="hub-prompt" aria-live="polite">{{ __('ui.walk_hint') }}</div>
</section>
<p class="rule-note hub-rule">{{ __('ui.level_rule') }}</p>

@auth
<section class="dashboard-grid">
    <div class="screen-panel">
        <div class="section-heading"><div><p class="eyebrow">TEAM STORAGE {{ $teams->count() }}/10</p><h2>{{ __('ui.teams') }}</h2></div></div>
        <div class="team-list">
            @forelse($teams as $team)
                <article class="team-row">
                    <div><strong>{{ $team->name }}</strong><div class="mini-roster">@foreach($team->pokemon as $pokemon)<span title="{{ $pokemon->pokemon_name }}{{ $pokemon->heldItem ? ' · '.$pokemon->heldItem->display_name : '' }}"><img src="{{ data_get($pokemon->snapshot, 'sprites.front') }}" alt="{{ $pokemon->pokemon_name }}"></span>@endforeach</div></div>
                    <form action="{{ route('teams.destroy', $team) }}" method="post">@csrf @method('DELETE')<button class="danger-button">×</button></form>
                </article>
            @empty <p>{{ __('ui.no_team') }}</p> @endforelse
        </div>
    </div>
    <form class="screen-panel team-builder" action="{{ route('teams.store') }}" method="post">
        @csrf
        <p class="eyebrow">NEW SAVE SLOT</p><h2>{{ __('ui.create_team') }}</h2>
        <label>{{ __('ui.team_name') }}<input name="name" value="{{ old('name') }}" required maxlength="40"></label>
        <p class="form-help">{{ __('ui.pokemon_help') }}</p>
        @for($i = 0; $i < 6; $i++)
            <div class="pokemon-slot"><span>#{{ $i + 1 }}</span><input name="pokemon[]" placeholder="{{ $i === 0 ? 'pikachu' : '—' }}" @required($i === 0)><select name="items[]"><option value="">{{ __('ui.no_item') }}</option>@foreach($inventory as $owned)<option value="{{ $owned->item_id }}">{{ $owned->item->display_name }} ×{{ $owned->quantity }}</option>@endforeach</select></div>
        @endfor
        <p class="form-help">{{ __('ui.items_help') }}</p>
        <button class="pixel-button" type="submit">{{ __('ui.save') }}</button>
    </form>
</section>

<section class="screen-panel inventory-panel">
    <p class="eyebrow">ONLINE REWARDS</p><h2>{{ __('ui.inventory') }}</h2>
    <div class="item-grid">@forelse($inventory as $owned)<article><strong>{{ $owned->item->display_name }} ×{{ $owned->quantity }}</strong><small>{{ $owned->item->display_description }}</small></article>@empty<p>{{ __('ui.inventory_empty') }}</p>@endforelse</div>
</section>
@endauth
@endsection
