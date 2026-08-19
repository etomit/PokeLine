<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    public function index()
    {
        return view('home');
    }

    public function presence(): JsonResponse
    {
        $activeSessions = DB::table(config('session.table', 'sessions'))
            ->where('last_activity', '>=', now()->subMinutes(2)->timestamp);
        $guestPlayers = (clone $activeSessions)->whereNull('user_id')->count();
        $connectedAccounts = (clone $activeSessions)
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');

        return response()->json([
            'active_players' => $guestPlayers + $connectedAccounts,
            'connected_accounts' => $connectedAccounts,
        ]);
    }
}
