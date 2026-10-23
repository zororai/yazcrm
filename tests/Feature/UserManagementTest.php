<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

// Users page: editing an agent (incl. optional new password) and resetting a password.
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['admin', 'agent', 'programs'] as $name) {
            Role::firstOrCreate(['name' => $name], ['display_name' => ucfirst($name), 'nav_permissions' => $name === 'admin' ? null : ['dashboard'], 'is_system' => true]);
        }
        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => Hash::make('admin-pass'), 'role' => 'admin']);
        $this->agent = User::create(['name' => 'Agent Old', 'email' => 'agent@example.test', 'password' => Hash::make('original-pass'), 'role' => 'agent', 'nav_permissions' => ['dashboard']]);
    }

    // What the Edit form actually sends.
    private function editPayload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Agent New', 'email' => 'agent.new@example.test', 'role' => 'agent', 'supervisor_id' => '',
            'nav_permissions' => ['dashboard', 'tickets'], 'password' => '', 'password_confirmation' => '',
        ];
    }

    public function test_admin_can_edit_a_user_without_touching_their_password(): void
    {
        $this->actingAs($this->admin)->from('/users')->put("/users/{$this->agent->id}", $this->editPayload())
            ->assertSessionHasNoErrors()->assertSessionHas('success', 'User updated.');

        $agent = $this->agent->fresh();
        $this->assertSame('Agent New', $agent->name);
        $this->assertSame('agent.new@example.test', $agent->email);
        $this->assertSame(['dashboard', 'tickets'], $agent->nav_permissions);
        $this->assertTrue(Hash::check('original-pass', $agent->password)); // blank password = unchanged
    }

    public function test_admin_can_set_a_new_password_from_the_edit_form(): void
    {
        $this->actingAs($this->admin)->from('/users')
            ->put("/users/{$this->agent->id}", $this->editPayload(['password' => 'brand-new-1', 'password_confirmation' => 'brand-new-1']))
            ->assertSessionHasNoErrors();

        $agent = $this->agent->fresh();
        $this->assertTrue(Hash::check('brand-new-1', $agent->password));
        $this->assertTrue((bool) $agent->must_change_password);

        $this->actingAs($this->admin)->from('/users')
            ->put("/users/{$this->agent->id}", $this->editPayload(['password' => 'short', 'password_confirmation' => 'short']))
            ->assertSessionHasErrors(['password' => 'The new password must be at least 8 characters.']);
        $this->actingAs($this->admin)->from('/users')
            ->put("/users/{$this->agent->id}", $this->editPayload(['password' => 'brand-new-2', 'password_confirmation' => 'different-2']))
            ->assertSessionHasErrors(['password' => 'The two passwords don\'t match.']);
        $this->assertTrue(Hash::check('brand-new-1', $this->agent->fresh()->password));
    }

    public function test_reset_password_explains_what_is_wrong_and_works_when_valid(): void
    {
        $reset = fn (array $data) => $this->actingAs($this->admin)->from('/users')->post("/users/{$this->agent->id}/reset-password", $data);

        $reset(['password' => '1234', 'password_confirmation' => '1234'])
            ->assertSessionHasErrors(['password' => 'The new password must be at least 8 characters.']);
        $reset(['password' => 'longenough1', 'password_confirmation' => 'longenough2'])
            ->assertSessionHasErrors(['password' => 'The two passwords don\'t match.']);

        $reset(['password' => 'longenough1', 'password_confirmation' => 'longenough1'])
            ->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Password reset for Agent Old'));
        $this->assertTrue(Hash::check('longenough1', $this->agent->fresh()->password));
        $this->assertTrue((bool) $this->agent->fresh()->must_change_password);
    }

    public function test_reset_to_default_works_like_a_new_account(): void
    {
        \Illuminate\Support\Facades\Storage::fake('public');
        \Illuminate\Support\Facades\Storage::disk('public')->put('avatars/old.jpg', 'x');
        $this->agent->update([
            'first_name' => 'Tendai', 'surname' => 'Moyo', 'username' => 'tmoyo', 'phone' => '0771',
            'bio' => 'Counsellor', 'avatar' => 'avatars/old.jpg', 'profile_prompt_dismiss_count' => 3,
            'supervisor_id' => $this->admin->id,
        ]);
        $this->assertTrue($this->agent->fresh()->profile_complete);

        $this->actingAs($this->admin)->from('/users')->post("/users/{$this->agent->id}/reset-default-password")
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'reset to default (password 1234)'));

        $agent = $this->agent->fresh();
        $this->assertTrue(Hash::check('1234', $agent->password));
        $this->assertTrue((bool) $agent->must_change_password);
        // Profile cleared → "Complete Your Profile" shows again, with fresh postpones.
        $this->assertFalse($agent->profile_complete);
        foreach (['first_name', 'surname', 'username', 'phone', 'bio', 'avatar'] as $field) {
            $this->assertNull($agent->{$field}, "{$field} should be cleared");
        }
        $this->assertSame(0, (int) $agent->profile_prompt_dismiss_count);
        \Illuminate\Support\Facades\Storage::disk('public')->assertMissing('avatars/old.jpg');
        // Account identity and setup are kept.
        $this->assertSame('Agent Old', $agent->name);
        $this->assertSame('agent@example.test', $agent->email);
        $this->assertSame('agent', $agent->role);
        $this->assertSame(['dashboard'], $agent->nav_permissions);
        $this->assertSame($this->admin->id, $agent->supervisor_id);
        $this->assertStringContainsString('Reset Agent Old to a new-account state', \App\Models\AuditLog::latest('id')->value('description'));

        // The admin can't reset their own account this way.
        $this->actingAs($this->admin)->from('/users')->post("/users/{$this->admin->id}/reset-default-password")
            ->assertSessionHas('error');
        $this->assertTrue(Hash::check('admin-pass', $this->admin->fresh()->password));

        // First login: sent to choose a new password, then the profile prompt is due.
        $this->post('/logout');
        $this->post('/login', ['email' => 'agent@example.test', 'password' => '1234']);
        $this->get('/dashboard')->assertRedirect('/change-password');
        $this->post('/change-password', ['password' => 'my-own-pass', 'password_confirmation' => 'my-own-pass'])
            ->assertRedirect('/dashboard');
        $this->withoutVite()->get('/dashboard')->assertOk()
            ->assertInertia(fn ($page) => $page->where('auth.user.profile_complete', false));
    }

    public function test_only_admins_can_manage_users(): void
    {
        $this->actingAs($this->agent)->post("/users/{$this->admin->id}/reset-default-password")->assertForbidden();
        $this->actingAs($this->agent)->put("/users/{$this->admin->id}", $this->editPayload(['email' => 'x@example.test']))->assertForbidden();
        $this->actingAs($this->agent)->post("/users/{$this->admin->id}/reset-password", ['password' => 'longenough1', 'password_confirmation' => 'longenough1'])->assertForbidden();
        $this->assertTrue(Hash::check('admin-pass', $this->admin->fresh()->password));
    }
}
