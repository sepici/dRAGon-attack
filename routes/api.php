<?php

use App\Http\Controllers\Api\V1\ClientController;
use App\Http\Controllers\Api\V1\DeliverableController;
use App\Http\Controllers\Api\V1\EmployerController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MilestoneController;
use App\Http\Controllers\Api\V1\OpenApiController;
use App\Http\Controllers\Api\V1\PlanController;
use App\Http\Controllers\Api\V1\PlanItemController;
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
| Ability gating uses Sanctum's built-in `abilities:<name>` middleware
| (alias registered in App\Http\Kernel). Wildcards (read:all, write:all)
| are expanded at token-creation time by ApiTokenController so the
| middleware can match exactly.
|
*/

// Publicly accessible OpenAPI spec — describes endpoints, no user data.
// MCP wrappers and ChatGPT Custom GPTs fetch this on configure.
Route::get('v1/openapi.json', OpenApiController::class)->name('api.v1.openapi');

Route::prefix('v1')
    ->middleware(['auth:sanctum', 'role:user'])
    ->name('api.v1.')
    ->group(function () {
        // Always-allowed: identity ping.
        Route::get('/me', MeController::class)->name('me');

        // -------- Reads under tracker:read --------
        Route::middleware('abilities:tracker:read')->group(function () {
            Route::get('/employers', [EmployerController::class, 'index'])->name('employers.index');
            Route::get('/employers/{employer}', [EmployerController::class, 'show'])->name('employers.show');

            Route::get('/clients', [ClientController::class, 'index'])->name('clients.index');
            Route::get('/clients/{client}', [ClientController::class, 'show'])->name('clients.show');

            Route::get('/projects', [ProjectController::class, 'index'])->name('projects.index');
            Route::get('/projects/{project}', [ProjectController::class, 'show'])->name('projects.show');

            Route::get('/deliverables', [DeliverableController::class, 'index'])->name('deliverables.index');
            Route::get('/deliverables/{deliverable}', [DeliverableController::class, 'show'])->name('deliverables.show');

            Route::get('/milestones', [MilestoneController::class, 'index'])->name('milestones.index');
            Route::get('/milestones/{milestone}', [MilestoneController::class, 'show'])->name('milestones.show');

            Route::get('/plans/weekly', [PlanController::class, 'weekly'])->name('plans.weekly');
            Route::get('/plans/monthly', [PlanController::class, 'monthly'])->name('plans.monthly');
            Route::get('/plans/quarterly', [PlanController::class, 'quarterly'])->name('plans.quarterly');
        });

        // -------- Writes under tracker:write --------
        Route::middleware('abilities:tracker:write')->group(function () {
            Route::post('/employers', [EmployerController::class, 'store'])->name('employers.store');
            Route::put('/employers/{employer}', [EmployerController::class, 'update'])->name('employers.update');
            Route::delete('/employers/{employer}', [EmployerController::class, 'destroy'])->name('employers.destroy');

            Route::post('/clients', [ClientController::class, 'store'])->name('clients.store');
            Route::put('/clients/{client}', [ClientController::class, 'update'])->name('clients.update');
            Route::delete('/clients/{client}', [ClientController::class, 'destroy'])->name('clients.destroy');

            Route::post('/projects', [ProjectController::class, 'store'])->name('projects.store');
            Route::put('/projects/{project}', [ProjectController::class, 'update'])->name('projects.update');
            Route::delete('/projects/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

            Route::post('/deliverables', [DeliverableController::class, 'store'])->name('deliverables.store');
            Route::put('/deliverables/{deliverable}', [DeliverableController::class, 'update'])->name('deliverables.update');
            Route::delete('/deliverables/{deliverable}', [DeliverableController::class, 'destroy'])->name('deliverables.destroy');

            Route::post('/milestones', [MilestoneController::class, 'store'])->name('milestones.store');
            Route::put('/milestones/{milestone}', [MilestoneController::class, 'update'])->name('milestones.update');
            Route::delete('/milestones/{milestone}', [MilestoneController::class, 'destroy'])->name('milestones.destroy');

            Route::post('/plan-items', [PlanItemController::class, 'store'])->name('plan-items.store');
            Route::put('/plan-items/{plan_item}', [PlanItemController::class, 'update'])->name('plan-items.update');
            Route::delete('/plan-items/{plan_item}', [PlanItemController::class, 'destroy'])->name('plan-items.destroy');
        });

        // -------- Reads under time-logs:read --------
        Route::middleware('abilities:time-logs:read')->group(function () {
            Route::get('/time-logs', [TimeLogController::class, 'index'])->name('time-logs.index');
            Route::get('/time-logs/{time_log}', [TimeLogController::class, 'show'])->name('time-logs.show');
        });

        // -------- Writes under time-logs:write --------
        Route::middleware('abilities:time-logs:write')->group(function () {
            Route::post('/time-logs', [TimeLogController::class, 'store'])->name('time-logs.store');
            Route::put('/time-logs/{time_log}', [TimeLogController::class, 'update'])->name('time-logs.update');
            Route::delete('/time-logs/{time_log}', [TimeLogController::class, 'destroy'])->name('time-logs.destroy');
        });
    });
