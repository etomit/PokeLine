@extends('layouts.app')
@section('title', __('ui.teams').' — '.__('ui.title'))
@section('content')
<div class="online-team-page">
    <a class="arcade-back" href="{{ route('battle.lobby') }}">◀ {{ __('ui.return_to_online_center') }}</a>
    <section id="online-team-workshop" class="online-team-workshop">
        <header class="online-workshop-head">
            <div><p class="eyebrow">ONLINE BOX // {{ $teams->count() }}/10</p><h2>{{ __('ui.online_team_workshop') }}</h2></div>
            <div class="arcade-lights" aria-hidden="true"><i></i><i></i><i></i></div>
        </header>
        <div class="online-workshop-grid">
            <section class="online-team-storage">
                <div class="section-heading"><h3>{{ __('ui.teams') }}</h3><strong>{{ $teams->count() }} / 10</strong></div>
                <div class="team-list">
                    @forelse($teams as $team)
                        <article class="team-row">
                            <div><strong>{{ $team->name }}</strong><div class="mini-roster">@foreach($team->pokemon as $pokemon)<span title="{{ data_get($pokemon->snapshot, 'label', $pokemon->pokemon_name) }}{{ $pokemon->heldItem ? ' · '.$pokemon->heldItem->display_name : '' }}"><img src="{{ data_get($pokemon->snapshot, 'sprites.front') }}" alt="{{ data_get($pokemon->snapshot, 'label', $pokemon->pokemon_name) }}"></span>@endforeach</div></div>
                            <form action="{{ route('teams.destroy', $team) }}" method="post">@csrf @method('DELETE')<button class="danger-button" aria-label="{{ __('ui.delete') }}">×</button></form>
                        </article>
                    @empty <div class="online-team-empty"><span>＋</span><p>{{ __('ui.no_team') }}</p></div> @endforelse
                </div>
            </section>

            <form class="player-loadout online-team-builder" action="{{ route('teams.store') }}" method="post" data-type-labels='@json(__('types'))'>
                @csrf
                <div class="player-banner"><span>01</span><strong>{{ __('ui.create_team') }}</strong></div>
                <label class="online-team-name">{{ __('ui.team_name') }}<input name="name" value="{{ old('name') }}" required maxlength="40"></label>
                <div class="team-preview" data-team-preview="online-team-roster"></div>
                <input type="hidden" id="online-team-roster" data-team-input name="team_roster" value="{{ old('team_roster', '') }}">
                <div class="party-actions"><button type="button" class="pokedex-picker-button party-add-button" data-pokedex-target="online-team-roster" data-pokedex-mode="append"><span>＋</span> {{ __('ui.add_pokemon') }}</button></div>
                <div class="arcade-items" hidden>
                    <strong>{{ __('ui.online_held_items') }}</strong>
                    <div class="item-select-grid">
                        @for($i = 0; $i < 6; $i++)
                            <label><span data-item-slot-label>#{{ $i + 1 }}</span><select name="items[]" data-item-select="online-team-roster" data-slot="{{ $i }}"><option value="">{{ __('ui.no_item') }}</option>@foreach($inventory as $owned)<option value="{{ $owned->item_id }}" data-item-label="{{ $owned->item->display_name }}" data-quantity="{{ $owned->quantity }}" @selected((string) old("items.$i") === (string) $owned->item_id)>{{ $owned->item->display_name }} ×{{ $owned->quantity }}</option>@endforeach</select></label>
                        @endfor
                    </div>
                </div>
                <button class="pixel-button online-team-save" type="submit">{{ __('ui.save_team') }}</button>
            </form>
        </div>
    </section>
</div>
@endsection
