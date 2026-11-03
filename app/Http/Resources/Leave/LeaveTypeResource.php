<?php

namespace App\Http\Resources\Leave;

use App\Models\Leave\LeaveType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin LeaveType */
class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'default_days'        => $this->default_days,
            'requires_attachment' => $this->requires_attachment,
            'is_paid'             => $this->is_paid,
            'is_active'           => $this->is_active,
        ];
    }
}
