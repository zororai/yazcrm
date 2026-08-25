<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private array $validPerms = [
        'dashboard','dialer','calls','recordings','callbacks','tickets',
        'urgent','directory','appraisals','appraisal_reviews','appraisal_archive','activity_reports','work_management','stores','stock_transfers','stocktakes','item_categories','fixed_assets','procurement','data_collection',
        'extensions','analytics','targets','by_project',
        'domains','bot_contacts','users','yeastar','yalep',
        'registry','risk','sbc','roles',
    ];

    public function index(): Response
    {
        $users = User::with('extension')->latest()->get();
        $roles = Role::orderBy('is_system', 'desc')->orderBy('display_name')->get(['id','name','display_name','nav_permissions','is_system']);

        return Inertia::render('Users/Index', ['users' => $users, 'roles' => $roles]);
    }

    public function store(Request $request): RedirectResponse
    {
        $roleNames = Role::pluck('name')->implode(',');

        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'email'              => 'required|email|unique:users,email',
            'role'               => "required|in:{$roleNames}",
            'supervisor_id'      => 'nullable|exists:users,id',
            'nav_permissions'    => 'sometimes|nullable|array',
            'nav_permissions.*'  => 'string|in:' . implode(',', $this->validPerms),
        ]);

        // Use the submitted checkboxes if any were sent, otherwise fall back
        // to the chosen role's default nav_permissions.
        if ($data['role'] === 'admin') {
            $data['nav_permissions'] = null;
        } elseif (! array_key_exists('nav_permissions', $data)) {
            $role = Role::where('name', $data['role'])->first();
            $data['nav_permissions'] = $role?->nav_permissions ?? [];
        }

        // New accounts start with a default password and must set their own on first login.
        User::create([...$data, 'password' => Hash::make('1234'), 'must_change_password' => true]);

        return back()->with('success', 'User created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $roleNames = Role::pluck('name')->implode(',');

        if ($request->supervisor_id === '') {
            $request->merge(['supervisor_id' => null]);
        }

        $data = $request->validate([
            'name'              => 'sometimes|string|max:255',
            'email'             => "sometimes|email|unique:users,email,{$user->id}",
            'role'              => "sometimes|in:{$roleNames}",
            'supervisor_id'     => "sometimes|nullable|exists:users,id|not_in:{$user->id}",
            'nav_permissions'   => 'sometimes|nullable|array',
            'nav_permissions.*' => 'string|in:' . implode(',', $this->validPerms),
        ]);

        // When role changes, auto-apply that role's default permissions
        if (isset($data['role']) && $data['role'] !== $user->role) {
            $role = Role::where('name', $data['role'])->first();
            $data['nav_permissions'] = $role?->name === 'admin' ? null : ($role?->nav_permissions ?? []);
        }

        // Admin always has full access
        if (($data['role'] ?? $user->role) === 'admin') {
            $data['nav_permissions'] = null;
        }

        $user->update($data);

        return back()->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $user->delete();

        return back()->with('success', 'User deleted.');
    }

    public function toggleActive(User $user): RedirectResponse
    {
        $user->update(['is_active' => !$user->is_active]);

        return back()->with('success', $user->is_active ? 'User activated.' : 'User deactivated.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $request->validate(['password' => 'required|string|min:8|confirmed']);

        $user->update(['password' => Hash::make($request->password), 'must_change_password' => true]);

        return back()->with('success', 'Password reset.');
    }
}
