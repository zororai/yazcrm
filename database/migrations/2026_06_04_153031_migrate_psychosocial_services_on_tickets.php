<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Original service name → psychosocial_type mapping
    private array $map = [
        'Helpline information' => 'Awareness Raising',
        'Helpline Marketing'   => 'Helpline Marketing',
        'Counselling'          => 'Counselling',
    ];

    public function up(): void
    {
        foreach ($this->map as $oldService => $psychoType) {
            DB::table('tickets')
                ->where('services_requested', $oldService)
                ->update([
                    'services_requested' => 'Psycho-social support',
                    'psychosocial_type'  => $psychoType,
                ]);
        }

        // Also handle second_service_requested
        foreach ($this->map as $oldService => $psychoType) {
            DB::table('tickets')
                ->where('second_service_requested', $oldService)
                ->update([
                    'second_service_requested' => 'Psycho-social support',
                ]);
        }
    }

    public function down(): void
    {
        // Reverse: restore original service names from psychosocial_type
        foreach ($this->map as $oldService => $psychoType) {
            DB::table('tickets')
                ->where('services_requested', 'Psycho-social support')
                ->where('psychosocial_type', $psychoType)
                ->update([
                    'services_requested' => $oldService,
                    'psychosocial_type'  => null,
                ]);
        }
    }
};
