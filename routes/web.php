<?php

use App\Http\Controllers\ProfileController;
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
});

// ---------------------------------------------------------------------------
// ADMIN role — user management only.
// Real CRUD lands in M1d.
// ---------------------------------------------------------------------------
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/users', function () {
            return view('admin.users.index');
        })->name('users.index');
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
