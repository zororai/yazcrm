<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        $users = User::with('extension')->latest()->get();

        return Inertia::render('Users/Index', ['users' => $users]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role'     => 'required|in:admin,agent,supervisor',
        ]);

        User::create([...$data, 'password' => Hash::make($data['password'])]);

        return back()->with('success', 'User created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => "sometimes|email|unique:users,email,{$user->id}",
            'role'  => 'sometimes|in:admin,agent,supervisor',
        ]);

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

        $user->update(['password' => Hash::make($request->password)]);

        return back()->with('success', 'Password reset.');
    }
}
