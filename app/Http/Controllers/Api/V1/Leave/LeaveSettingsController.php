<?php

namespace App\Http\Controllers\Api\V1\Leave;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\LeaveEntitlementRequest;
use App\Http\Requests\Leave\LeaveTypeRequest;
use App\Http\Requests\Leave\PublicHolidayRequest;
use App\Http\Resources\Leave\LeaveBalanceResource;
use App\Http\Resources\Leave\LeaveTypeResource;
use App\Http\Resources\Leave\PublicHolidayResource;
use App\Models\Leave\LeaveBalance;
use App\Models\Leave\LeaveType;
use App\Models\Leave\PublicHoliday;
use App\Support\Leave\LeaveAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

// Leave types and public holidays are readable by everyone (the apply form
// needs them); changing them, and anything about entitlements, is for leave
// admins. Writes are guarded by the LeaveAdminRequest form requests.
class LeaveSettingsController extends Controller
{
    // ── Leave types ──────────────────────────────────────────────────────────
    public function types(Request $request): AnonymousResourceCollection
    {
        $all = $request->boolean('all') && LeaveAccess::isAdmin($request->user());

        return LeaveTypeResource::collection(
            LeaveType::when(! $all, fn ($q) => $q->where('is_active', true))->orderBy('name')->get()
        );
    }

    public function storeType(LeaveTypeRequest $request): JsonResponse
    {
        return (new LeaveTypeResource(LeaveType::create($request->validated())))->response()->setStatusCode(201);
    }

    public function updateType(LeaveTypeRequest $request, LeaveType $leaveType): LeaveTypeResource
    {
        $leaveType->update($request->validated());

        return new LeaveTypeResource($leaveType);
    }

    // A type that has been used is switched off rather than deleted, so old
    // requests keep their type.
    public function destroyType(Request $request, LeaveType $leaveType): JsonResponse
    {
        $this->ensureAdmin($request);

        if ($leaveType->requests()->exists()) {
            $leaveType->update(['is_active' => false]);

            return response()->json(['message' => "{$leaveType->name} has requests, so it was switched off instead of deleted."]);
        }

        $leaveType->delete();

        return response()->json(['message' => 'Leave type deleted.']);
    }

    // ── Public holidays ──────────────────────────────────────────────────────
    public function holidays(Request $request): AnonymousResourceCollection
    {
        $year = (int) ($request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']])['year'] ?? 0);

        return PublicHolidayResource::collection(
            PublicHoliday::when($year, fn ($q) => $q->whereYear('date', $year))->orderBy('date')->get()
        );
    }

    public function storeHoliday(PublicHolidayRequest $request): JsonResponse
    {
        return (new PublicHolidayResource(PublicHoliday::create($request->validated())))->response()->setStatusCode(201);
    }

    public function updateHoliday(PublicHolidayRequest $request, PublicHoliday $publicHoliday): PublicHolidayResource
    {
        $publicHoliday->update($request->validated());

        return new PublicHolidayResource($publicHoliday);
    }

    public function destroyHoliday(Request $request, PublicHoliday $publicHoliday): JsonResponse
    {
        $this->ensureAdmin($request);
        $publicHoliday->delete();

        return response()->json(['message' => 'Public holiday deleted.']);
    }

    // ── Entitlements ─────────────────────────────────────────────────────────
    public function entitlements(Request $request): AnonymousResourceCollection
    {
        $this->ensureAdmin($request);
        $filters = $request->validate([
            'year'    => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'user_id' => ['nullable', 'integer'],
        ]);

        return LeaveBalanceResource::collection(
            LeaveBalance::with('user', 'leaveType')
                ->where('year', $filters['year'] ?? now()->year)
                ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
                ->orderBy('user_id')->orderBy('leave_type_id')
                ->get()
        );
    }

    public function storeEntitlement(LeaveEntitlementRequest $request): LeaveBalanceResource
    {
        $data = $request->validated();
        $balance = LeaveBalance::updateOrCreate(
            ['user_id' => $data['user_id'], 'leave_type_id' => $data['leave_type_id'], 'year' => $data['year']],
            ['entitled_days' => $data['entitled_days']],
        );

        return new LeaveBalanceResource($balance->load('user', 'leaveType'));
    }

    public function destroyEntitlement(Request $request, LeaveBalance $leaveBalance): JsonResponse
    {
        $this->ensureAdmin($request);

        if ($leaveBalance->used_days > 0) {
            return response()->json(['message' => 'This entitlement already has approved leave against it. Set it to a different number of days instead.'], 422);
        }

        $leaveBalance->delete();

        return response()->json(['message' => 'Entitlement removed. The leave type default applies again.']);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(LeaveAccess::isAdmin($request->user()), 403, 'Only leave admins can change leave settings.');
    }
}
