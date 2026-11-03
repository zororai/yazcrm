<?php

namespace App\Services\Leave;

use App\Models\Leave\LeaveBalance;
use App\Models\Leave\LeaveRequest;
use App\Models\Leave\LeaveType;
use App\Models\User;
use Illuminate\Support\Collection;

// Read-only balance figures (the app's balance cards and the CRM report).
// A person with no balance row yet shows the type's default entitlement;
// nothing is written until they actually apply.
class LeaveSummary
{
    /** @return list<array<string, mixed>> one entry per active leave type */
    public static function forUser(User $user, int $year): array
    {
        return self::build(collect([$user]), $year)->values()->all();
    }

    /** @return Collection<int, array<string, mixed>> one entry per user and active leave type */
    public static function build(Collection $users, int $year): Collection
    {
        $types = LeaveType::where('is_active', true)->orderBy('name')->get();
        $ids   = $users->pluck('id');

        $balances = LeaveBalance::whereIn('user_id', $ids)->where('year', $year)->get()
            ->keyBy(fn ($b) => $b->user_id.':'.$b->leave_type_id);

        $pending = LeaveRequest::whereIn('user_id', $ids)
            ->where('status', LeaveRequest::PENDING)
            ->whereYear('start_date', $year)
            ->selectRaw('user_id, leave_type_id, SUM(days) as days')
            ->groupBy('user_id', 'leave_type_id')
            ->get()
            ->keyBy(fn ($r) => $r->user_id.':'.$r->leave_type_id);

        return $users->flatMap(fn (User $user) => $types->map(function (LeaveType $type) use ($user, $year, $balances, $pending) {
            $key       = $user->id.':'.$type->id;
            $entitled  = (float) ($balances[$key]->entitled_days ?? $type->default_days);
            $used      = (float) ($balances[$key]->used_days ?? 0);
            $pend      = (float) ($pending[$key]->days ?? 0);
            $remaining = round($entitled - $used, 1);

            return [
                'user'           => ['id' => $user->id, 'name' => $user->name],
                'leave_type'     => ['id' => $type->id, 'name' => $type->name, 'is_paid' => $type->is_paid, 'requires_attachment' => $type->requires_attachment],
                'year'           => $year,
                'entitled_days'  => $entitled,
                'used_days'      => $used,
                'pending_days'   => $pend,
                'remaining_days' => $remaining,
                // What a new request can still use. Unpaid leave has no cap.
                'available_days' => $type->is_paid ? max(0, round($remaining - $pend, 1)) : null,
            ];
        }));
    }
}
