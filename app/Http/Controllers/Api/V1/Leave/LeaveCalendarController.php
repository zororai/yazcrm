<?php

namespace App\Http\Controllers\Api\V1\Leave;

use App\Http\Controllers\Controller;
use App\Http\Resources\Leave\LeaveRequestResource;
use App\Http\Resources\Leave\PublicHolidayResource;
use App\Models\Leave\LeaveRequest;
use App\Models\Leave\PublicHoliday;
use App\Support\Leave\LeaveAccess;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Who is off when: approved and pending leave for an approver's direct
// reports (and their own), or for everyone if they are a leave admin.
class LeaveCalendarController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! LeaveAccess::isAdmin($user) && ! LeaveAccess::isApprover($user)) {
            return response()->json([
                'message' => 'The team calendar is for people who approve leave. Ask your administrator if you need it.',
            ], 403);
        }

        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to'   => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = CarbonImmutable::parse($data['from'] ?? now()->startOfMonth());
        $to   = CarbonImmutable::parse($data['to'] ?? $from->endOfMonth());

        $requests = LeaveAccess::calendarScope($user)
            ->with('leaveType', 'user', 'approver')
            ->active()
            ->overlapping($from->toDateString(), $to->toDateString())
            ->orderBy('start_date')
            ->get();

        return response()->json([
            'from'     => $from->toDateString(),
            'to'       => $to->toDateString(),
            'requests' => LeaveRequestResource::collection($requests),
            'holidays' => PublicHolidayResource::collection(
                PublicHoliday::whereDate('date', '>=', $from->toDateString())
                    ->whereDate('date', '<=', $to->toDateString())
                    ->orderBy('date')->get()
            ),
        ]);
    }
}
