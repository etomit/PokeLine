@extends('layouts.app')
@section('title', __($mode === 'solo' ? 'ui.solo' : 'ui.local').' — '.__('ui.title'))
@section('content')
<section class="setup-wrap screen-panel">
    <p class="eyebrow">MODE // {{ strtoupper($mode) }}</p><h1>{{ __($mode === 'solo' ? 'ui.solo' : 'ui.local') }}</h1>
    <p>{{ __('ui.level_rule') }}</p>
    <form method="post" action="{{ route('battle.session.start', $mode) }}" class="setup-form">@csrf
        <label>{{ __('ui.first_player') }}<input name="team1" value="{{ old('team1', 'pikachu, charizard, blastoise') }}" required><small>{{ __('ui.pokemon_help') }}</small></label>
        <label>{{ __('ui.sandbox_items') }}<input name="items1" value="{{ old('items1', 'leftovers, life-orb, sitrus-berry') }}"><small>{{ __('ui.sandbox_items_help') }} {{ $items->pluck('slug')->join(', ') }}</small></label>
        @if($mode === 'local')<label>{{ __('ui.second_player') }}<input name="team2" value="{{ old('team2', 'venusaur, gengar, dragonite') }}" required><small>{{ __('ui.pokemon_help') }}</small></label>@endif
        @if($mode === 'local')<label>{{ __('ui.sandbox_items') }}<input name="items2" value="{{ old('items2', 'expert-belt, focus-sash, rocky-helmet') }}"><small>{{ __('ui.sandbox_items_help') }}</small></label>@endif
        <button class="pixel-button" type="submit">{{ __('ui.start_battle') }}</button>
    </form>
</section>
@endsection
