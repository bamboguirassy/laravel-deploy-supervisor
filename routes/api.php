<?php

use Bamboguirassy\DeploySupervisor\Http\Controllers\DeploiementController;
use Illuminate\Support\Facades\Route;

Route::post('/', [DeploiementController::class, 'trigger']);
Route::post('search', [DeploiementController::class, 'search']);
Route::get('environnements', [DeploiementController::class, 'environnements']);
Route::get('{uid}', [DeploiementController::class, 'show']);
Route::delete('{uid}', [DeploiementController::class, 'destroy']);
