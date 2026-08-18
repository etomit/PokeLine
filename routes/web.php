<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BattleController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PokemonController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::post('/language', [AuthController::class, 'locale'])->name('locale');
Route::get('/pokedex', [PokemonController::class, 'index'])->name('pokedex');
Route::get('/api/pokedex', [PokemonController::class, 'catalog'])->middleware('throttle:90,1')->name('pokedex.catalog');
Route::get('/api/pokemon/{pokemon}', [PokemonController::class, 'show'])->where('pokemon', '[A-Za-z0-9-]+')->name('pokemon.show');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::get('/play/{mode}', [BattleController::class, 'setup'])->whereIn('mode', ['solo', 'local'])->name('battle.setup');
Route::post('/play/{mode}', [BattleController::class, 'startSession'])->whereIn('mode', ['solo', 'local'])->name('battle.session.start');
Route::get('/battle', [BattleController::class, 'sessionShow'])->name('battle.session.show');
Route::get('/battle/state', [BattleController::class, 'sessionState'])->name('battle.session.state');
Route::post('/battle/action', [BattleController::class, 'sessionAction'])->name('battle.session.action');

Route::middleware('auth')->group(function () {
    Route::get('/online/teams', [TeamController::class, 'index'])->name('teams.index');
    Route::post('/teams', [TeamController::class, 'store'])->name('teams.store');
    Route::delete('/teams/{team}', [TeamController::class, 'destroy'])->name('teams.destroy');
    Route::get('/online', [BattleController::class, 'lobby'])->name('battle.lobby');
    Route::post('/online/create', [BattleController::class, 'createOnline'])->name('battle.online.create');
    Route::post('/online/join', [BattleController::class, 'joinOnline'])->name('battle.online.join-code');
    Route::post('/online/{battle}/join', [BattleController::class, 'joinOnline'])->name('battle.online.join');
    Route::get('/online/{battle}/watch', [BattleController::class, 'spectate'])->name('battle.spectate');
    Route::get('/online/{battle}/watch/state', [BattleController::class, 'spectatorState'])->name('battle.spectate.state');
    Route::delete('/online/{battle}/cancel', [BattleController::class, 'cancelOnline'])->name('battle.online.cancel');
    Route::post('/online/{battle}/forfeit', [BattleController::class, 'forfeitOnline'])->name('battle.online.forfeit');
    Route::get('/online/{battle}', [BattleController::class, 'onlineShow'])->name('battle.online.show');
    Route::get('/online/{battle}/state', [BattleController::class, 'onlineState'])->name('battle.online.state');
    Route::post('/online/{battle}/action', [BattleController::class, 'onlineAction'])->name('battle.online.action');
});
