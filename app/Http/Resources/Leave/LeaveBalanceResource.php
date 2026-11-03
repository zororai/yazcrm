<?php

namespace App\Http\Resources\Leave;

use App\Models\Leave\LeaveBalance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// An entitlement row as the admin screens see it (person, type, year).
/** @mixin LeaveBalance */
class LeaveBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'user'           => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'leave_type'     => $this->whenLoaded('leaveType', fn () => new LeaveTypeResource($this->leaveType)),
            'year'           => $this->year,
            'entitled_days'  => $this->entitled_days,
            'used_days'      => $this->used_days,
            'remaining_days' => $this->remaining_days,
        ];
    }
}
