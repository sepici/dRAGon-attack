<?php

use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\DeliverableController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\ProjectController;
use App\Http\Controllers\Api\V1\TimeLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Versioned under /api/v1. Authentication is Sanctum personal-access tokens
| issued from /profile, restricted to user-role accounts.
|
| Ability gating uses Sanctum's built-in `abilities:<name>` middleware.
| Wildcards (read:all, write:all) are expanded at token-creation time by
| ApiTokenController::store(), so the middleware can match exactly.
|
*/

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'role:user'])
    ->name('api.v1.')
    ->group(function () {
        // Always-allowed: identity ping.
        Route::get('/me', MeController::class)->name('me');

        // -------- Reads under tracker:read --------
        Route::middleware('abilities:tracker:read')->group(function () {
            Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
            Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');

            Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
            Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

            Route::get('/deliverables', [DeliverableController::class, 'index'])->name('deliverables.index');
            Route::get('/deliverables/{deliverable}', [DeliverableController::class, 'show'])->name('deliverables.show');

            Route::get('/plans/weekly', [PlanController::class, 'weekly'])->name('plans.weekly');
            Route::get('/plans/monthly', [PlanController::class, 'monthly'])->name('plans.monthly');
            Route::get('/plans/quarterly', [PlanController::class, 'quarterly'])->name('plans.quarterly');
        });

        // -------- Reads under time-logs:read --------
        Route::middleware('abilities:time-logs:read')->group(function () {
            Route::get('/time-logs', [TimeLogController::class, 'index'])->name('time-logs.index');
            Route::get('/time-logs/{time_log}', [TimeLogController::class, 'show'])->name('time-logs.show');
        });
    });
