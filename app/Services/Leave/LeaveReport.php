<?php

namespace App\Services\Leave;

use App\Models\Leave\LeaveRequest;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

// Year report for leave admins: every active person's entitlement, used,
// pending and remaining days per leave type, plus request counts by status.
// Shared by the API (/leave/reports) and the CRM page.
class LeaveReport
{
    public static function data(int $year): array
    {
        $users = User::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $rows  = LeaveSummary::build($users, $year);

        $byType = $rows->groupBy(fn ($r) => $r['leave_type']['name'])->map(fn ($group, $name) => [
            'leave_type'     => $name,
            'entitled_days'  => round($group->sum('entitled_days'), 1),
            'used_days'      => round($group->sum('used_days'), 1),
            'pending_days'   => round($group->sum('pending_days'), 1),
            'remaining_days' => round($group->sum('remaining_days'), 1),
        ])->values();

        $byStatus = LeaveRequest::whereYear('start_date', $year)
            ->selectRaw('status, COUNT(*) as requests, SUM(days) as days')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->status => ['requests' => (int) $r->requests, 'days' => (float) $r->days]]);

        return [
            'year'      => $year,
            'by_type'   => $byType,
            'by_status' => $byStatus,
            'rows'      => $rows->values(),
        ];
    }

    public static function csv(int $year): StreamedResponse
    {
        $rows = self::data($year)['rows'];

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Staff member', 'Leave type', 'Year', 'Entitled', 'Used', 'Pending', 'Remaining']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r['user']['name'], $r['leave_type']['name'], $r['year'],
                    $r['entitled_days'], $r['used_days'], $r['pending_days'], $r['remaining_days'],
                ]);
            }
            fclose($out);
        }, "leave-report-{$year}.csv", ['Content-Type' => 'text/csv']);
    }
}
