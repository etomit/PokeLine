@extends('layouts.app')
@section('title', __('ui.pokedex').' — '.__('ui.title'))
@section('content')
<section class="pokedex-page" data-pokemon-details-url="{{ url('/api/pokemon') }}" data-pokemon-details-translations='@json([
    "title" => __('ui.pokemon_details'), "stats" => __('ui.statistics'), "moves" => __('ui.moves'),
    "close" => __('ui.close'), "hp" => __('ui.hp'), "attack" => __('ui.attack'), "defense" => __('ui.defense'),
    "special_attack" => __('ui.special_attack'), "special_defense" => __('ui.special_defense'), "speed" => __('ui.speed'),
    "power" => __('ui.power_short'), "accuracy" => __('ui.accuracy_short'), "types" => __('types'),
])'>
    <header class="pokedex-hero">
        <h1>{{ __('ui.pokedex') }}</h1>
        <div class="pokedex-light"><i></i><i></i><i></i></div>
    </header>
    <div class="pokedex-device" data-pokedex-browser data-catalog-url="{{ route('pokedex.catalog') }}">
        <div class="pokedex-toolbar"><input type="search" data-pokedex-search placeholder="{{ __('ui.search_pokemon') }}"><button type="button" class="pixel-button" data-pokedex-submit>{{ __('ui.search') }}</button></div>
        <div class="pokedex-grid" data-pokedex-grid><div class="pokedex-loading">{{ __('ui.loading_pokedex') }}</div></div>
        <div class="pokedex-pagination"><button type="button" data-pokedex-prev>◀</button><span data-pokedex-page>1 / 1</span><button type="button" data-pokedex-next>▶</button></div>
    </div>
    <dialog id="pokemon-details-dialog" class="pokemon-details-dialog" aria-labelledby="pokemon-details-title">
        <button type="button" class="pokemon-details-close" data-pokemon-details-close aria-label="{{ __('ui.close') }}">×</button>
        <div data-pokemon-details-content></div>
    </dialog>
</section>
@endsection
