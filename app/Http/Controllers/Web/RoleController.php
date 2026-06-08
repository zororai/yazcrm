<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    private array $validPerms = [
        'extensions','analytics','targets','by_project',
        'domains','bot_contacts','users','yeastar','yalep',
        'registry','risk',
    ];

    public function index(): Response
    {
        return Inertia::render('Roles/Index', [
            'roles'      => Role::orderBy('is_system', 'desc')->orderBy('display_name')->get(),
            'validPerms' => $this->validPerms,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'display_name'    => 'required|string|max:100',
            'nav_permissions' => 'present|array',
            'nav_permissions.*' => 'string|in:' . implode(',', $this->validPerms),
        ]);

        // Generate slug from display name
        $name = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($data['display_name'])));
        $name = rtrim($name, '_');

        if (Role::where('name', $name)->exists()) {
            return back()->withErrors(['display_name' => 'A role with that name already exists.']);
        }

        Role::create([
            'name'            => $name,
            'display_name'    => $data['display_name'],
            'nav_permissions' => $data['nav_permissions'],
            'is_system'       => false,
        ]);

        return back()->with('success', 'Role created.');
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate([
            'display_name'    => 'required|string|max:100',
            'nav_permissions' => 'present|array',
            'nav_permissions.*' => 'string|in:' . implode(',', $this->validPerms),
        ]);

        $role->update([
            'display_name'    => $data['display_name'],
            'nav_permissions' => $role->is_system && $role->name === 'admin'
                ? null                             // admin keeps full access
                : $data['nav_permissions'],
        ]);

        return back()->with('success', 'Role updated.');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_system) {
            return back()->withErrors(['role' => 'System roles cannot be deleted.']);
        }

        $role->delete();

        return back()->with('success', 'Role deleted.');
    }
}
