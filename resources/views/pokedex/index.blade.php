@extends('layouts.app')
@section('title', __('ui.pokedex').' — '.__('ui.title'))
@section('content')
<section class="pokedex-page">
    <header class="pokedex-hero">
        <div><p class="eyebrow">POKÉAPI // NATIONAL DATA</p><h1>{{ __('ui.pokedex') }}</h1><p>{{ __('ui.pokedex_intro') }}</p></div>
        <div class="pokedex-light"><i></i><i></i><i></i></div>
    </header>
    <div class="pokedex-device" data-pokedex-browser data-catalog-url="{{ route('pokedex.catalog') }}">
        <div class="pokedex-toolbar"><input type="search" data-pokedex-search placeholder="{{ __('ui.search_pokemon') }}"><button type="button" class="pixel-button" data-pokedex-submit>{{ __('ui.search') }}</button></div>
        <div class="pokedex-grid" data-pokedex-grid><div class="pokedex-loading">{{ __('ui.loading_pokedex') }}</div></div>
        <div class="pokedex-pagination"><button type="button" data-pokedex-prev>◀</button><span data-pokedex-page>1 / 1</span><button type="button" data-pokedex-next>▶</button></div>
    </div>
</section>
@endsection
