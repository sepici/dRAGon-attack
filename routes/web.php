<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AgentDocsController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\ContactPersonController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliverableController;
use App\Http\Controllers\JournalController;
use App\Http\Controllers\MilestoneController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlanItemController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Profile\ApiTokenController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\TimesheetController;
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
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Clients + nested contact persons
    Route::resource('clients', ClientController::class);
    Route::resource('clients.contacts', ContactPersonController::class)
        ->parameters(['contacts' => 'contact'])
        ->only(['create', 'store', 'edit', 'update', 'destroy']);

    // Projects (flat resource — projects.client_id picks the parent client)
    Route::resource('projects', ProjectController::class);

    // Milestones — optional grouping layer between Project and Deliverable.
    // Small projects skip this; big ones use it to phase work.
    Route::resource('milestones', MilestoneController::class);

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

    // Daily journal — log hours per (date, deliverable). Feeds the
    // derived hours_spent aggregates and the monthly timesheet PDF.
    Route::get('journal', [JournalController::class, 'today'])->name('journal.today');
    Route::get('journal/{date}', [JournalController::class, 'show'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('journal.show');
    Route::post('journal/{date}', [JournalController::class, 'store'])
        ->where('date', '\d{4}-\d{2}-\d{2}')
        ->name('journal.store');

    // End-of-week review
    Route::get('review', [ReviewController::class, 'show'])->name('review.show');
    Route::post('review', [ReviewController::class, 'store'])->name('review.store');
    Route::post('review/roll-forward', [ReviewController::class, 'rollForward'])->name('review.roll-forward');

    // Weekly PDF reports
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    Route::get('reports/{report}/download', [ReportController::class, 'download'])->name('reports.download');

    // Monthly timesheet PDFs (month-grid format)
    Route::get('timesheets', [TimesheetController::class, 'index'])->name('timesheets.index');
    Route::post('timesheets/generate', [TimesheetController::class, 'generate'])->name('timesheets.generate');
    Route::get('timesheets/{timesheet}/download', [TimesheetController::class, 'download'])->name('timesheets.download');

    // "Connect your AI" landing — copy-paste guides for ChatGPT / Claude /
    // generic HTTP clients pointed at the /api/v1 surface.
    Route::get('agent', [AgentDocsController::class, 'show'])->name('agent.show');
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

    // Personal-access token management (Sanctum). Issued only to user-role
    // accounts; the controller enforces that.
    Route::post('/profile/api-tokens', [ApiTokenController::class, 'store'])
        ->name('profile.api-tokens.store');
    Route::delete('/profile/api-tokens/{token}', [ApiTokenController::class, 'destroy'])
        ->name('profile.api-tokens.destroy');
});

require __DIR__.'/auth.php';
