<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BeneficiaryActivity;
use App\Models\BeneficiaryAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BeneficiaryAttendanceController extends Controller
{
    // Look up whatever phone number the field agent just typed into the app,
    // so the app can show "already captured today" / show past attendance
    // for that beneficiary instead of capturing a duplicate blind.
    public function search(Request $request): JsonResponse
    {
        $phone = trim((string) $request->query('phone', ''));
        if ($phone === '') {
            return response()->json(['results' => []]);
        }

        $results = BeneficiaryAttendance::where('phone_number', $phone)
            ->with('activity')
            ->latest('captured_at')
            ->limit(20)
            ->get()
            ->map(fn (BeneficiaryAttendance $a) => [
                'id'            => $a->id,
                'full_name'     => $a->full_name,
                'sex'           => $a->sex,
                'age'           => $a->age,
                'district'      => $a->district,
                'phone_number'  => $a->phone_number,
                'activity_name' => $a->activity?->activity_name,
                'activity_date' => $a->activity?->activity_date?->toDateString(),
                'captured_at'   => $a->captured_at?->toIso8601String(),
            ]);

        return response()->json(['results' => $results]);
    }

    // Bulk sync endpoint the Android app calls once it regains connectivity.
    // Each activity/attendance carries a client-generated UUID, so resyncing
    // the same batch twice (e.g. a retry after a dropped connection) is a
    // no-op rather than creating duplicates.
    public function sync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'activities'                          => 'required|array|min:1',
            'activities.*.client_uuid'             => 'required|uuid',
            'activities.*.activity_name'           => 'required|string|max:255',
            'activities.*.activity_date'           => 'required|date',
            'activities.*.compiled_by'             => 'nullable|string|max:255',
            'activities.*.reviewed_by'             => 'nullable|string|max:255',
            'activities.*.attendances'             => 'required|array|min:1',
            'activities.*.attendances.*.client_uuid'       => 'required|uuid',
            'activities.*.attendances.*.full_name'         => 'required|string|max:255',
            'activities.*.attendances.*.sex'               => ['required', Rule::in(['M', 'F'])],
            'activities.*.attendances.*.age'               => 'nullable|integer|min:0|max:120',
            'activities.*.attendances.*.district'          => 'nullable|string|max:255',
            'activities.*.attendances.*.phone_number'      => 'nullable|string|max:50',
            'activities.*.attendances.*.id_number_or_dob'  => 'nullable|string|max:100',
            'activities.*.attendances.*.captured_at'       => 'nullable|date',
        ]);

        $created = ['activities' => 0, 'attendances' => 0];
        $skipped = ['activities' => 0, 'attendances' => 0];

        DB::transaction(function () use ($data, $request, &$created, &$skipped) {
            foreach ($data['activities'] as $activityData) {
                $activity = BeneficiaryActivity::where('client_uuid', $activityData['client_uuid'])->first();

                if ($activity) {
                    $skipped['activities']++;
                } else {
                    $activity = BeneficiaryActivity::create([
                        'client_uuid'         => $activityData['client_uuid'],
                        'activity_name'       => $activityData['activity_name'],
                        'activity_date'       => $activityData['activity_date'],
                        'compiled_by'         => $activityData['compiled_by'] ?? null,
                        'reviewed_by'         => $activityData['reviewed_by'] ?? null,
                        'created_by_user_id'  => $request->user()->id,
                    ]);
                    $created['activities']++;
                }

                foreach ($activityData['attendances'] as $attendanceData) {
                    $exists = BeneficiaryAttendance::where('client_uuid', $attendanceData['client_uuid'])->exists();

                    if ($exists) {
                        $skipped['attendances']++;
                        continue;
                    }

                    BeneficiaryAttendance::create([
                        'client_uuid'              => $attendanceData['client_uuid'],
                        'beneficiary_activity_id'  => $activity->id,
                        'full_name'                => $attendanceData['full_name'],
                        'sex'                      => $attendanceData['sex'],
                        'age'                      => $attendanceData['age'] ?? null,
                        'district'                 => $attendanceData['district'] ?? null,
                        'phone_number'             => $attendanceData['phone_number'] ?? null,
                        'id_number_or_dob'         => $attendanceData['id_number_or_dob'] ?? null,
                        'captured_at'              => $attendanceData['captured_at'] ?? now(),
                    ]);
                    $created['attendances']++;
                }
            }
        });

        return response()->json([
            'message' => 'Sync complete.',
            'created' => $created,
            'skipped' => $skipped, // already-synced records from a previous attempt
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $activities = BeneficiaryActivity::with('attendances')
            ->latest('activity_date')
            ->paginate(20);

        return response()->json($activities);
    }
}
