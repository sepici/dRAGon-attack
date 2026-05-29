<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployerRequest;
use App\Http\Requests\UpdateEmployerRequest;
use App\Models\Employer;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Tracker → Employers — user-facing CRUD for the entity that owns a user's
 * clients. Every user has a Self employer (auto-created on registration)
 * plus 0..N additional employers they add here.
 *
 * Special-cased on the Self row:
 *   • Self always lists first (sort_order is ignored for it).
 *   • Edit form shows the name field as readonly.
 *   • Delete button is hidden / refused at the policy level.
 */
class EmployerController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Employer::class, 'employer');
    }

    public function index(): View
    {
        $employers = auth()->user()
            ->employers()
            ->withCount(['clients'])
            // Self pinned to the top, then by sort_order, then by name.
            ->orderByDesc('is_self')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('employers.index', compact('employers'));
    }

    public function create(): View
    {
        $employer = new Employer(['sort_order' => 0]);

        return view('employers.create', compact('employer'));
    }

    public function store(StoreEmployerRequest $request): RedirectResponse
    {
        $employer = auth()->user()->employers()->create($request->validated());

        return redirect()
            ->route('employers.show', $employer)
            ->with('status', 'Employer added.');
    }

    public function show(Employer $employer): View
    {
        $employer->load(['clients' => fn ($q) => $q->orderBy('legal_name')]);

        return view('employers.show', compact('employer'));
    }

    public function edit(Employer $employer): View
    {
        return view('employers.edit', compact('employer'));
    }

    public function update(UpdateEmployerRequest $request, Employer $employer): RedirectResponse
    {
        $employer->update($request->validated());

        return redirect()
            ->route('employers.show', $employer)
            ->with('status', 'Employer updated.');
    }

    public function destroy(Employer $employer): RedirectResponse
    {
        // Belt-and-braces. The policy already refuses Self; the model's
        // deleting() guard refuses Self too, plus refuses any employer with
        // clients. Map both into friendlier form errors here.
        if ($employer->is_self) {
            return back()->withErrors([
                'delete' => 'The Self employer cannot be deleted.',
            ]);
        }
        if ($employer->clients()->exists()) {
            return back()->withErrors([
                'delete' => 'Cannot delete this employer — it still has clients. Move or delete them first.',
            ]);
        }

        $employer->delete();

        return redirect()
            ->route('employers.index')
            ->with('status', 'Employer deleted.');
    }
}
