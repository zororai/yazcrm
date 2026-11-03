<?php

namespace App\Http\Requests\Leave;

use App\Support\Leave\LeaveAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

// Base for the leave settings forms (types, entitlements, public holidays):
// leave admins only, with a refusal the app can show as-is.
abstract class LeaveAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return LeaveAccess::isAdmin($this->user());
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('Only leave admins can change leave settings.');
    }
}
