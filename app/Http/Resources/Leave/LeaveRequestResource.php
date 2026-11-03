<?php

namespace App\Http\Resources\Leave;

use App\Models\Leave\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeaveRequest */
class LeaveRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id'                => $this->id,
            'user'              => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'leave_type'        => $this->whenLoaded('leaveType', fn () => new LeaveTypeResource($this->leaveType)),
            'start_date'        => $this->start_date->toDateString(),
            'end_date'          => $this->end_date->toDateString(),
            'days'              => $this->days,
            'reason'            => $this->reason,
            'has_attachment'    => $this->attachment_path !== null,
            'status'            => $this->status,
            'approver'          => $this->whenLoaded('approver', fn () => $this->approver ? ['id' => $this->approver->id, 'name' => $this->approver->name] : null),
            'approver_comment'  => $this->approver_comment,
            'created_at'        => $this->created_at?->toIso8601String(),
            'decided_at'        => $this->decided_at?->toIso8601String(),
            'cancelled_at'      => $this->cancelled_at?->toIso8601String(),
            'corrected_at'      => $this->corrected_at?->toIso8601String(),
            'correction_reason' => $this->correction_reason,
            // What the server will allow this viewer to do with the request,
            // so the app never has to work it out.
            'can'               => $viewer ? [
                'cancel' => $viewer->can('cancel', $this->resource),
                'decide' => $viewer->can('decide', $this->resource),
            ] : null,
        ];
    }
}
