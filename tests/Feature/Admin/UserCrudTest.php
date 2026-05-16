<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Coverage for the admin user-management screens.
 *
 *   Listing      — only admins see the table.
 *   Create       — validation, persistence, password hashing.
 *   Update       — fields update; password is optional on edit.
 *   Delete       — works for other users, blocked for self & last admin.
 *   Last admin   — cannot be demoted to user/viewer.
 */
class UserCrudTest extends TestCase
{
    use RefreshDatabase;

    // ---------- Access -------------------------------------------------------

    public function test_admin_sees_users_index(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->get('/admin/users');

        $response->assertOk();
        $response->assertSee($admin->email);
    }

    public function test_non_admin_cannot_see_users_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/admin/users');

        $response->assertRedirect(route('dashboard'));
    }

    // ---------- Create -------------------------------------------------------

    public function test_admin_can_create_a_user(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post('/admin/users', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'super-secret-123',
            'password_confirmation' => 'super-secret-123',
            'role' => 'user',
            'weekly_capacity_days' => 5.0,
            'monthly_capacity_days' => 20.0,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertSame('Jane Doe', $created->name);
        $this->assertSame(UserRole::User, $created->role);
        $this->assertTrue(Hash::check('super-secret-123', $created->password));
    }

    public function test_create_requires_unique_email(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->actingAs($admin)
            ->from('/admin/users/create')
            ->post('/admin/users', [
                'name' => 'Whoever',
                'email' => 'taken@example.com',
                'password' => 'super-secret-123',
                'password_confirmation' => 'super-secret-123',
                'role' => 'user',
                'weekly_capacity_days' => 5.0,
                'monthly_capacity_days' => 20.0,
            ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_create_requires_password(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->from('/admin/users/create')
            ->post('/admin/users', [
                'name' => 'No Password',
                'email' => 'np@example.com',
                'role' => 'user',
                'weekly_capacity_days' => 5.0,
                'monthly_capacity_days' => 20.0,
            ]);

        $response->assertSessionHasErrors('password');
    }

    // ---------- Update -------------------------------------------------------

    public function test_admin_can_update_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Old Name']);

        $response = $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => 'New Name',
            'email' => $user->email,
            'role' => 'user',
            'weekly_capacity_days' => 4.0,
            'monthly_capacity_days' => 18.0,
        ]);

        $response->assertRedirect(route('admin.users.index'));

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('4.0', (string) $user->weekly_capacity_days);
    }

    public function test_update_does_not_change_password_when_blank(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $originalPasswordHash = $user->password;

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'user',
            'weekly_capacity_days' => 5.0,
            'monthly_capacity_days' => 20.0,
            // password left blank
        ]);

        $user->refresh();
        $this->assertSame($originalPasswordHash, $user->password);
    }

    public function test_update_changes_password_when_provided(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->actingAs($admin)->put("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => 'user',
            'weekly_capacity_days' => 5.0,
            'monthly_capacity_days' => 20.0,
            'password' => 'brand-new-pass-123',
            'password_confirmation' => 'brand-new-pass-123',
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-pass-123', $user->password));
    }

    // ---------- Delete -------------------------------------------------------

    public function test_admin_can_delete_another_user(): void
    {
        $admin = User::factory()->admin()->create();
        $victim = User::factory()->create();

        $response = $this->actingAs($admin)->delete("/admin/users/{$victim->id}");

        $response->assertRedirect(route('admin.users.index'));
        $this->assertModelMissing($victim);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->from("/admin/users/{$admin->id}/edit")
            ->delete("/admin/users/{$admin->id}");

        $response->assertSessionHasErrors('delete');
        $this->assertModelExists($admin);
    }

    // ---------- Last admin protection ---------------------------------------

    public function test_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->admin()->create();
        // No other admins exist.

        $response = $this->actingAs($admin)
            ->from("/admin/users/{$admin->id}/edit")
            ->put("/admin/users/{$admin->id}", [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'user', // demotion attempt
                'weekly_capacity_days' => 5.0,
                'monthly_capacity_days' => 20.0,
            ]);

        $response->assertSessionHasErrors('role');
        $admin->refresh();
        $this->assertSame(UserRole::Admin, $admin->role);
    }

    public function test_admin_can_delete_another_admin_when_more_than_one_exists(): void
    {
        // Two admins → one can delete the other.
        $a1 = User::factory()->admin()->create();
        $a2 = User::factory()->admin()->create();

        $this->actingAs($a1)->delete("/admin/users/{$a2->id}");

        $this->assertModelMissing($a2);
        $this->assertSame(1, User::where('role', UserRole::Admin)->count());
    }

    // Note: there is no integration test for "can't delete the LAST admin"
    // because the only actor able to reach the destroy route is an admin,
    // and the self-delete guard fires first. The controller still has a
    // defensive `adminCount() <= 1` check in destroy() as belt-and-braces
    // in case a future change introduces another path to that method.

    public function test_promoting_a_second_admin_unlocks_demotion(): void
    {
        $a1 = User::factory()->admin()->create();
        $u = User::factory()->create();

        // Promote $u to admin.
        $this->actingAs($a1)->put("/admin/users/{$u->id}", [
            'name' => $u->name,
            'email' => $u->email,
            'role' => 'admin',
            'weekly_capacity_days' => 5.0,
            'monthly_capacity_days' => 20.0,
        ]);

        // Now $a1 can be demoted (someone else is admin).
        $response = $this->actingAs($a1)->put("/admin/users/{$a1->id}", [
            'name' => $a1->name,
            'email' => $a1->email,
            'role' => 'user',
            'weekly_capacity_days' => 5.0,
            'monthly_capacity_days' => 20.0,
        ]);

        $response->assertSessionHasNoErrors();
        $a1->refresh();
        $this->assertSame(UserRole::User, $a1->role);
    }
}
