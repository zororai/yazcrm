<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('Profile/Show', [
            'profileUser' => $request->user()->load(['supervisor:id,name', 'extension']),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => 'nullable|string|max:100',
            'surname'    => 'nullable|string|max:100',
            'username'   => 'nullable|string|max:100',
            'phone'      => 'nullable|string|max:50',
            'bio'        => 'nullable|string|max:1000',
            'avatar'     => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('avatar')) {
            if ($request->user()->avatar) {
                Storage::disk('public')->delete($request->user()->avatar);
            }
            $data['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        // /users, /counsellor-profiles, /timetable, etc. all display the
        // `name` column, not first_name/surname — keep it in sync whenever
        // either changes, so a self-service profile edit actually shows up
        // everywhere else in the CRM.
        if (array_key_exists('first_name', $data) || array_key_exists('surname', $data)) {
            $firstName = $data['first_name'] ?? $request->user()->first_name;
            $surname   = $data['surname'] ?? $request->user()->surname;
            $fullName  = trim("$firstName $surname");
            if ($fullName !== '') {
                $data['name'] = $fullName;
            }
        }

        $request->user()->update($data);

        return back()->with('success', 'Profile updated.');
    }

    // Records that the user dismissed the complete-your-profile popup, so the
    // count persists across sessions — after 3 dismissals the popup stops
    // offering "Remind me later" and must be completed.
    public function dismissPrompt(Request $request): RedirectResponse
    {
        $request->user()->increment('profile_prompt_dismiss_count');

        return back();
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => 'required|current_password',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $request->user()->update([
            'password' => Hash::make($data['password']),
        ]);

        return back()->with('success', 'Password updated.');
    }
}
