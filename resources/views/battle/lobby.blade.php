@extends('layouts.app')
@section('title', __('ui.lobby').' — '.__('ui.title'))
@section('content')
<section class="screen-panel lobby-head"><p class="eyebrow">LINK CABLE // ONLINE</p><h1>{{ __('ui.lobby') }}</h1><p>{{ __('ui.online_desc') }}</p></section>
@if($teams->isEmpty())<section class="screen-panel"><p>{{ __('ui.no_team') }}</p><a class="pixel-button" href="{{ route('home') }}">{{ __('ui.create_team') }}</a></section>@else
<section class="lobby-grid">
    <form class="screen-panel" action="{{ route('battle.online.create') }}" method="post">@csrf<h2>{{ __('ui.create_room') }}</h2><label>{{ __('ui.teams') }}<select name="team_id">@foreach($teams as $team)<option value="{{ $team->id }}">{{ $team->name }} ({{ $team->pokemon->count() }})</option>@endforeach</select></label><button class="pixel-button">{{ __('ui.create_room') }}</button></form>
    <form class="screen-panel" action="{{ route('battle.online.join-code') }}" method="post">@csrf<h2>{{ __('ui.join') }}</h2><label>{{ __('ui.room_code') }}<input name="code" maxlength="8" required></label><label>{{ __('ui.teams') }}<select name="team_id">@foreach($teams as $team)<option value="{{ $team->id }}">{{ $team->name }}</option>@endforeach</select></label><button class="pixel-button">{{ __('ui.join') }}</button></form>
</section>
<section class="screen-panel"><h2>{{ __('ui.available_rooms') }}</h2><div class="room-list">@forelse($battles as $room)<form action="{{ route('battle.online.join', $room) }}" method="post">@csrf<div><strong>{{ $room->host->name }}</strong><span>CODE {{ $room->code }}</span></div><select name="team_id">@foreach($teams as $team)<option value="{{ $team->id }}">{{ $team->name }}</option>@endforeach</select><button class="pixel-button small">{{ __('ui.join') }}</button></form>@empty<p>{{ __('ui.no_rooms') }}</p>@endforelse</div></section>
@endif
@endsection
