<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\CallTargetController;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// One place to see every counsellor's profile (photo/phone/bio), their call
// target progress, and how many tickets they've logged today.
class CounsellorProfileController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $counsellors = User::where('role', 'agent')
            ->when($search, fn ($q) => $q->where(function ($q2) use ($search) {
                $q2->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->with('callTarget')
            ->orderBy('name')
            ->get();

        $today = Carbon::today();

        $ticketsToday = Ticket::whereDate('created_at', $today)
            ->whereNotNull('agent_id')
            ->selectRaw('agent_id, count(*) as cnt')
            ->groupBy('agent_id')
            ->pluck('cnt', 'agent_id');

        $rows = $counsellors->map(function (User $counsellor) use ($ticketsToday) {
            $target = CallTargetController::summaryForAgent($counsellor->id);

            return [
                'id'              => $counsellor->id,
                'name'            => $counsellor->name,
                'first_name'      => $counsellor->first_name,
                'surname'         => $counsellor->surname,
                'username'        => $counsellor->username,
                'email'           => $counsellor->email,
                'phone'           => $counsellor->phone,
                'bio'             => $counsellor->bio,
                'avatar'          => $counsellor->avatar,
                'is_active'       => $counsellor->is_active,
                'call_target'     => $target,
                'tickets_today'   => (int) ($ticketsToday[$counsellor->id] ?? 0),
            ];
        })->values();

        return Inertia::render('Counsellors/Index', [
            'counsellors' => $rows,
            'filters'     => ['search' => $search],
        ]);
    }
}
