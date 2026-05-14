<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContactPersonController;
use App\Http\Controllers\DeliverableController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlanItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Routes are grouped by role. The `role:*` middleware (App\Http\Middleware\
| EnsureRole) enforces strict separation — if a user hits a route that's
| not for their role, they're sent back to their own landing route.
|
*/

Route::get('/', function () {
    return view('welcome');
});

// ---------------------------------------------------------------------------
// USER role — the main tracker experience.
// More routes (clients, projects, deliverables, plans, review, reports) land
// here in M2+.
// ---------------------------------------------------------------------------
Route::middleware(['auth', 'role:user'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Clients + nested contact persons
    Route::resource('clients', ClientController::class);
    Route::resource('clients.contacts', ContactPersonController::class)
        ->parameters(['contacts' => 'contact'])
        ->only(['create', 'store', 'edit', 'update', 'destroy']);

    // Projects (flat resource — projects.client_id picks the parent client)
    Route::resource('projects', ProjectController::class);

    // Deliverables — the master tracker table. project_id picks the parent.
    Route::resource('deliverables', DeliverableController::class);

    // Plans (calendar-aligned: weekly = Mon-Sun, monthly = calendar month,
    // quarterly = current month → end of month+2). Auto-created on first visit.
    Route::prefix('plans')->name('plans.')->group(function () {
        Route::get('weekly', [PlanController::class, 'weekly'])->name('weekly');
        Route::get('monthly', [PlanController::class, 'monthly'])->name('monthly');
        Route::get('quarterly', [PlanController::class, 'quarterly'])->name('quarterly');
    });

    // Individual allocation rows on those plans. Flat routes (plan_item id
    // is unique across all periods/kinds).
    Route::post('plan-items', [PlanItemController::class, 'store'])->name('plan-items.store');
    Route::put('plan-items/{plan_item}', [PlanItemController::class, 'update'])->name('plan-items.update');
    Route::delete('plan-items/{plan_item}', [PlanItemController::class, 'destroy'])->name('plan-items.destroy');
});

// ---------------------------------------------------------------------------
// ADMIN role — user management only.
// ---------------------------------------------------------------------------
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::resource('users', AdminUserController::class);
    });

// ---------------------------------------------------------------------------
// VIEWER role — read-only access to all users' tracker data.
// Read-only views land in M2+.
// ---------------------------------------------------------------------------
Route::middleware(['auth', 'role:viewer'])
    ->prefix('viewer')
    ->name('viewer.')
    ->group(function () {
        Route::get('/dashboard', function () {
            return view('viewer.dashboard');
        })->name('dashboard');
    });

// ---------------------------------------------------------------------------
// Profile — any authenticated user (admin, user, viewer) manages their own.
// ---------------------------------------------------------------------------
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
