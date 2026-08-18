<?php

namespace App\Http\Controllers;

use App\Models\Battle;
use App\Models\Item;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('home', [
            'teams' => $user?->teams()->with(['pokemon.heldItem'])->latest()->get() ?? collect(),
            'inventory' => $user?->inventory()->with('item')->where('quantity', '>', 0)->get() ?? collect(),
            'items' => Item::orderBy('name')->get(),
            'waitingBattles' => $user ? Battle::with('host')->where('status', 'waiting')->where('host_id', '!=', $user->id)->latest()->limit(8)->get() : collect(),
        ]);
    }
}
