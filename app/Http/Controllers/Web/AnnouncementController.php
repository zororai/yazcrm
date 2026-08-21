<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AdminAnnouncementNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

class AnnouncementController extends Controller
{
    private function isManager(User $user): bool
    {
        return in_array($user->role, ['admin', 'director'], true);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->isManager($request->user())) {
            abort(403);
        }

        $data = $request->validate([
            'recipient' => 'required|string', // 'all' or a user id
            'message'   => 'required|string|max:2000',
        ]);

        $recipients = $data['recipient'] === 'all'
            ? User::where('is_active', true)->where('id', '!=', $request->user()->id)->get()
            : User::where('id', $data['recipient'])->get();

        if ($recipients->isEmpty()) {
            return back()->with('error', 'No matching recipient found.');
        }

        Notification::send($recipients, new AdminAnnouncementNotification($request->user(), $data['message']));

        return back()->with('success', $data['recipient'] === 'all'
            ? "Notification sent to {$recipients->count()} people."
            : 'Notification sent.');
    }
}
