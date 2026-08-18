<?php

namespace App\Http\Controllers;

use App\Services\PokeApiService;
use Throwable;

class PokemonController extends Controller
{
    public function show(string $pokemon, PokeApiService $pokeApi)
    {
        try {
            return response()->json($pokeApi->pokemon($pokemon));
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(['message' => $exception->getMessage()], 404);
        }
    }
}
