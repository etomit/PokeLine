@extends('layouts.app')
@section('title', __('ui.login').' — '.__('ui.title'))
@section('content')
<div class="auth-wrap"><form class="screen-panel auth-card" method="post" action="{{ route('login') }}">@csrf<p class="eyebrow">PLAYER LINK</p><h1>{{ __('ui.login') }}</h1><label>{{ __('ui.email') }}<input type="email" name="email" value="{{ old('email') }}" required autofocus></label><label>{{ __('ui.password') }}<input type="password" name="password" required></label><button class="pixel-button">{{ __('ui.login') }}</button><a href="{{ route('register') }}">{{ __('ui.register') }}</a></form></div>
@endsection
