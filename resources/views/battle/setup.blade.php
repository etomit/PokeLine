@extends('layouts.app')
@section('title', __($mode === 'solo' ? 'ui.solo' : 'ui.local').' — '.__('ui.title'))
@section('content')
<section class="arcade-setup mode-{{ $mode }}" data-type-labels='@json(__('types'))'>
    <header class="arcade-setup-head">
        <a href="{{ route('home') }}" class="arcade-back">◀ {{ __('ui.back_home') }}</a>
        <div><p class="eyebrow">PARTY // {{ strtoupper($mode) }}</p><h1>{{ __('ui.choose_your_team') }}</h1><p>{{ __('ui.party_help') }}</p></div>
        <div class="arcade-lights"><i></i><i></i><i></i></div>
    </header>

    <form method="post" action="{{ route('battle.session.start', $mode) }}" class="arcade-setup-form">
        @csrf
        <section class="player-loadout player-one">
            <div class="player-banner"><span>P1</span><strong>{{ __('ui.first_player') }}</strong></div>
            <div class="team-preview" data-team-preview="setup-team-1"></div>
            <input type="hidden" id="setup-team-1" data-team-input name="team1" value="{{ old('team1', 'pikachu, charizard, blastoise') }}">
            <div class="party-actions"><button type="button" class="pokedex-picker-button party-add-button" data-pokedex-target="setup-team-1" data-pokedex-mode="append"><span>＋</span> {{ __('ui.add_pokemon') }}</button><small>{{ __('ui.party_picker_help') }}</small></div>
            <div class="arcade-items"><strong>{{ __('ui.sandbox_items') }}</strong><small>{{ __('ui.sandbox_items_select_help') }}</small>
                <div class="item-select-grid">
                    @for($i = 0; $i < 6; $i++)
                        <label><span data-item-slot-label>#{{ $i + 1 }}</span><select name="items1[]" data-item-select="setup-team-1" data-slot="{{ $i }}"><option value="">{{ __('ui.no_item') }}</option>@foreach($items as $item)<option value="{{ $item->slug }}">{{ $item->display_name }} — {{ $item->display_description }}</option>@endforeach</select></label>
                    @endfor
                </div>
            </div>
        </section>

        @if($mode === 'local')
        <div class="versus-badge">VS</div>
        <section class="player-loadout player-two">
            <div class="player-banner"><span>P2</span><strong>{{ __('ui.second_player') }}</strong></div>
            <div class="team-preview" data-team-preview="setup-team-2"></div>
            <input type="hidden" id="setup-team-2" data-team-input name="team2" value="{{ old('team2', 'venusaur, gengar, dragonite') }}">
            <div class="party-actions"><button type="button" class="pokedex-picker-button party-add-button" data-pokedex-target="setup-team-2" data-pokedex-mode="append"><span>＋</span> {{ __('ui.add_pokemon') }}</button><small>{{ __('ui.party_picker_help') }}</small></div>
            <div class="arcade-items"><strong>{{ __('ui.sandbox_items') }}</strong><small>{{ __('ui.sandbox_items_select_help') }}</small>
                <div class="item-select-grid">
                    @for($i = 0; $i < 6; $i++)
                        <label><span data-item-slot-label>#{{ $i + 1 }}</span><select name="items2[]" data-item-select="setup-team-2" data-slot="{{ $i }}"><option value="">{{ __('ui.no_item') }}</option>@foreach($items as $item)<option value="{{ $item->slug }}">{{ $item->display_name }} — {{ $item->display_description }}</option>@endforeach</select></label>
                    @endfor
                </div>
            </div>
        </section>
        @endif

        <div class="arcade-start"><button class="pixel-button" type="submit"><span>▶</span> {{ __('ui.start_battle') }}</button><small>{{ __('ui.ready_hint') }}</small></div>
    </form>
</section>
@endsection
