<?php

use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Versioned under /api/v1 so we can iterate without breaking external
| consumers (MCP wrapper, ChatGPT custom GPTs, scripts).
|
| Auth: Sanctum personal-access tokens. Issued only to user-role accounts
| (admins and viewers don't get tokens). The EnsureRole middleware
| returns JSON 403 for API requests, not the web's redirect.
|
*/

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'role:user'])
    ->name('api.v1.')
    ->group(function () {
        // Who am I? First call any agent makes after wiring up a token.
        Route::get('/me', MeController::class)->name('me');
    });
