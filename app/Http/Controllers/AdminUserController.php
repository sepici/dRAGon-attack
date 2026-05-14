<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin user management.
 *
 * Routes (all under /admin/users, protected by role:admin middleware):
 *     GET    /admin/users               index
 *     GET    /admin/users/create        create form
 *     POST   /admin/users               store
 *     GET    /admin/users/{user}/edit   edit form
 *     PUT    /admin/users/{user}        update
 *     DELETE /admin/users/{user}        destroy
 *
 * Safety rules:
 *  - The last remaining admin cannot be demoted or deleted.
 *  - An admin cannot delete their own account.
 */
class AdminUserController extends Controller
{
    public function index(): View
    {
        $users = User::query()
            ->orderByRaw("FIELD(role, 'admin', 'user', 'viewer')")
            ->orderBy('name')
            ->get();

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        $user = new User(['role' => UserRole::User]);

        return view('admin.users.create', compact('user'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        User::create($request->validated());

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        // Block demoting the last admin
        if ($user->isAdmin()
            && ($data['role'] ?? null) !== UserRole::Admin->value
            && $this->adminCount() <= 1
        ) {
            throw ValidationException::withMessages([
                'role' => 'Cannot demote the last admin. Promote another user to admin first.',
            ]);
        }

        // Only update password if a new one was supplied
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        // No deleting yourself
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['delete' => 'You cannot delete your own account.']);
        }

        // No deleting the last admin
        if ($user->isAdmin() && $this->adminCount() <= 1) {
            return back()->withErrors(['delete' => 'Cannot delete the last admin.']);
        }

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('status', 'User deleted.');
    }

    private function adminCount(): int
    {
        return User::where('role', UserRole::Admin)->count();
    }
}
