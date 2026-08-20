<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Departments/Index', [
            'departments' => Department::with('manager:id,name')->orderBy('name')->get(),
            'users'       => User::orderBy('name')->get(['id', 'name']),
            'isManager'   => $this->isManager($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'code'       => 'required|string|max:50|unique:departments,code',
            'name'       => 'required|string|max:255',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        Department::create($data);

        return back()->with('success', 'Department created.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'code'       => "required|string|max:50|unique:departments,code,{$department->id}",
            'name'       => 'required|string|max:255',
            'manager_id' => 'nullable|exists:users,id',
            'is_active'  => 'sometimes|boolean',
        ]);

        $department->update($data);

        return back()->with('success', 'Saved.');
    }

    public function destroy(Request $request, Department $department): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $department->delete();

        return back()->with('success', 'Department deleted.');
    }
}
