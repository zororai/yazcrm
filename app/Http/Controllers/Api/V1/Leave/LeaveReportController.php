<?php

namespace App\Http\Controllers\Api\V1\Leave;

use App\Http\Controllers\Controller;
use App\Services\Leave\LeaveReport;
use App\Support\Leave\LeaveAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveReportController extends Controller
{
    public function index(Request $request): JsonResponse|StreamedResponse
    {
        abort_unless(LeaveAccess::isAdmin($request->user()), 403, 'Leave reports are for leave admins.');

        $data = $request->validate([
            'year'   => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'format' => ['nullable', 'in:json,csv'],
        ]);
        $year = (int) ($data['year'] ?? now()->year);

        if (($data['format'] ?? 'json') === 'csv') {
            return LeaveReport::csv($year);
        }

        return response()->json(LeaveReport::data($year));
    }
}
