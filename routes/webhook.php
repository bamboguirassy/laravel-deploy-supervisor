<?php

use Bamboguirassy\DeploySupervisor\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('{provider}', [WebhookController::class, 'handle']);
