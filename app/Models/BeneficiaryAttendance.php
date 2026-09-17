<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BeneficiaryAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_uuid', 'beneficiary_activity_id', 'full_name', 'sex', 'age',
        'district', 'phone_number', 'id_number_or_dob', 'signature_path', 'captured_at',
    ];

    protected $casts = [
        'captured_at' => 'datetime',
    ];

    public function activity(): BelongsTo
    {
        return $this->belongsTo(BeneficiaryActivity::class, 'beneficiary_activity_id');
    }
}
