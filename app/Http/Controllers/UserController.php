<?php

namespace App\Http\Controllers;

use App\Models\Extension;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::with('extension')->withCount('calls');

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%");
            });
        }

        return response()->json($query->latest()->get());
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user->load('extension')->loadCount('calls'));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:8',
            'role'         => 'in:admin,agent',
            'extension_id' => 'nullable|exists:extensions,id',
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $request->role ?? 'agent',
        ]);

        if ($request->filled('extension_id')) {
            Extension::where('id', $request->extension_id)
                ->update(['user_id' => $user->id]);
        }

        return response()->json($user->load('extension'), 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'name'         => 'sometimes|string|max:255',
            'email'        => ['sometimes', 'email', Rule::unique('users')->ignore($user->id)],
            'password'     => 'sometimes|string|min:8',
            'role'         => 'in:admin,agent',
            'is_active'    => 'boolean',
            'extension_id' => 'nullable|exists:extensions,id',
        ]);

        $data = $request->only(['name', 'email', 'role', 'is_active']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        // Reassign extension
        if ($request->has('extension_id')) {
            // Unlink previous
            Extension::where('user_id', $user->id)->update(['user_id' => null]);
            // Link new
            if ($request->extension_id) {
                Extension::where('id', $request->extension_id)
                    ->update(['user_id' => $user->id]);
            }
        }

        return response()->json($user->fresh()->load('extension'));
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'Cannot delete yourself.'], 422);
        }

        // Unlink extension
        Extension::where('user_id', $user->id)->update(['user_id' => null]);
        $user->delete();

        return response()->json(['message' => 'Agent deleted.']);
    }

    public function toggleActive(User $user): JsonResponse
    {
        if ($user->id === request()->user()->id) {
            return response()->json(['message' => 'Cannot deactivate yourself.'], 422);
        }

        $user->update(['is_active' => !$user->is_active]);
        return response()->json($user->fresh());
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $request->validate(['password' => 'required|string|min:8|confirmed']);

        $user->update(['password' => Hash::make($request->password)]);

        // Revoke all tokens so the user must log in again
        $user->tokens()->delete();

        return response()->json(['message' => 'Password reset. All sessions revoked.']);
    }

    public function agentStats(): JsonResponse
    {
        $agents = User::where('role', 'agent')
            ->with('extension:id,extension_number,status')
            ->withCount([
                'calls as total_calls',
                'calls as answered_calls' => fn($q) => $q->where('status', 'answered'),
                'calls as missed_calls'   => fn($q) => $q->where('status', 'missed'),
            ])
            ->get()
            ->map(function ($agent) {
                $agent->answer_rate = $agent->total_calls > 0
                    ? round($agent->answered_calls / $agent->total_calls * 100, 1)
                    : 0;
                return $agent;
            });

        return response()->json($agents);
    }
}
