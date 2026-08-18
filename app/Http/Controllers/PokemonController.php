<?php

namespace App\Http\Controllers;

use App\Services\PokeApiService;
use Illuminate\Http\Request;
use Throwable;

class PokemonController extends Controller
{
    public function index()
    {
        return view('pokedex.index');
    }

    public function catalog(Request $request, PokeApiService $pokeApi)
    {
        $data = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'search' => ['nullable', 'string', 'max:50'],
        ]);

        return response()->json($pokeApi->catalog((int) ($data['page'] ?? 1), $data['search'] ?? ''));
    }

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
