<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DepartmentController extends Controller
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director', 'stores', 'accounting_dep'], true);
    }

    private function generateCode(string $name): string
    {
        $base = Str::upper(Str::slug($name, '_'));
        $code = $base;
        $i = 1;

        while (Department::where('code', $code)->exists()) {
            $code = "{$base}_{$i}";
            $i++;
        }

        return $code;
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
            'name'       => 'required|string|max:255',
            'manager_id' => 'nullable|exists:users,id',
        ]);

        $data['code'] = $this->generateCode($data['name']);

        Department::create($data);

        return back()->with('success', 'Department created.');
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
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
