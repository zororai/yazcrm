<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    // Temporary password for new accounts and "Reset to default". Always
    // paired with must_change_password, so it only works for the next login.
    public const DEFAULT_PASSWORD = '1234';

    private array $validPerms = [
        'dashboard','helpline_dashboard','dialer','calls','recordings','records_management','callbacks','tickets','ticket_management',
        'urgent','directory','appraisals','appraisal_reviews','appraisal_archive','activity_reports','work_management','stores','stock_transfers','stocktakes','item_categories','fixed_assets','procurement','data_collection',
        'extensions','analytics','targets','by_project',
        'domains','bot_contacts','users','yeastar','yalep',
        'registry','risk','sbc','roles',
        'timetable','progress_reports','success_stories',
        'my_work','audit_trail','it_asset_categories','transcription_test','counsellor_profiles','team_reports','programmes_dashboard','lsjp',
        'school','school_admin','data_api',
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
        User::create([...$data, 'password' => Hash::make(self::DEFAULT_PASSWORD), 'must_change_password' => true]);

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
            // Optional: set a new password while editing (blank = keep current).
            'password'          => 'nullable|string|min:8|confirmed',
        ], [
            'password.min'       => 'The new password must be at least 8 characters.',
            'password.confirmed' => 'The two passwords don\'t match.',
            'nav_permissions.*.in' => 'One of the selected permissions is not recognised — untick it and save again.',
        ]);

        // A password set by an admin is temporary: the user picks their own at next login.
        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
            $data['must_change_password'] = true;
        } else {
            unset($data['password']);
        }

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

        return back()->with('success', isset($data['password'])
            ? 'User updated and password changed — they will be asked to choose their own at next login.'
            : 'User updated.');
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
        $request->validate(['password' => 'required|string|min:8|confirmed'], [
            'password.required'  => 'Enter a new password.',
            'password.min'       => 'The new password must be at least 8 characters.',
            'password.confirmed' => 'The two passwords don\'t match.',
        ]);

        $user->update(['password' => Hash::make($request->password), 'must_change_password' => true]);

        return back()->with('success', "Password reset for {$user->name} — they will be asked to choose their own at next login.");
    }

    // One click: put the account back to how a newly created account starts
    // (e.g. an agent who is away / has forgotten their login). The default
    // password must be changed at next login, and the profile details are
    // cleared so the "Complete Your Profile" step runs again like a new user.
    // Name, email, role, permissions and all their work stay as they are.
    public function resetToDefault(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You can\'t reset your own account to default here — use Reset password instead.');
        }

        if ($user->avatar) {
            Storage::disk('public')->delete($user->avatar);
        }

        $user->update([
            'password'                     => Hash::make(self::DEFAULT_PASSWORD),
            'must_change_password'         => true,
            'first_name'                   => null,
            'surname'                      => null,
            'username'                     => null,
            'phone'                        => null,
            'bio'                          => null,
            'avatar'                       => null,
            'profile_prompt_dismiss_count' => 0,
        ]);

        $request->attributes->set('audit_description',
            "Reset {$user->name} to a new-account state: default password (must change at next login), profile details cleared");

        return back()->with('success', "{$user->name} has been reset to default (password ".self::DEFAULT_PASSWORD."). At next login they'll set a new password and fill in their profile, like a new account.");
    }

    // Lets an admin log in as another user's account (mainly for helping
    // agents troubleshoot) without knowing their password. The admin's own
    // id is stashed in the session so "Return to my account" can restore it.
    public function impersonate(Request $request, User $user): RedirectResponse
    {
        $admin = $request->user();

        if ($request->session()->has('impersonator_id')) {
            return back()->with('error', 'Already impersonating a user — return to your account first.');
        }

        if ($user->id === $admin->id) {
            return back()->with('error', 'You are already logged in as this account.');
        }

        if ($user->role === 'admin') {
            return back()->with('error', 'Cannot log in as another administrator account.');
        }

        if (!$user->is_active) {
            return back()->with('error', 'Cannot log in as a disabled account.');
        }

        // Deliberately NOT regenerating the session id here: this app polls
        // several endpoints in the background (notifications, pending-ticket
        // queue), and swapping the session id mid-flight can let a stale
        // in-flight request silently overwrite the browser's cookie with a
        // fresh anonymous session a moment later, logging the admin straight
        // back out. Keeping the same session id avoids that race.
        $request->session()->put('impersonator_id', $admin->id);
        Auth::login($user);

        return redirect()->route('dashboard')->with('success', "Now logged in as {$user->name}.");
    }

    public function stopImpersonating(Request $request): RedirectResponse
    {
        $adminId = $request->session()->pull('impersonator_id');

        if (!$adminId) {
            return redirect()->route('dashboard');
        }

        Auth::loginUsingId($adminId);

        return redirect()->route('users.index')->with('success', 'Returned to your account.');
    }
}
