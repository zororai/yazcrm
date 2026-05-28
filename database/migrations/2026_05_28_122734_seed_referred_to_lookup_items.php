<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $items = [
            ['name' => 'Clinic / Health Facility', 'sort_order' => 1],
            ['name' => 'Police Station',            'sort_order' => 2],
            ['name' => 'CeSHHAR / STI Clinic',     'sort_order' => 3],
            ['name' => 'School Headmaster',         'sort_order' => 4],
            ['name' => 'DSD / Social Welfare',      'sort_order' => 5],
            ['name' => 'Civil Court',               'sort_order' => 6],
            ['name' => 'VFU (Victim Support)',      'sort_order' => 7],
            ['name' => 'YALEP Programme',           'sort_order' => 8],
        ];

        foreach ($items as $item) {
            DB::table('lookup_items')->insertOrIgnore([
                'type'       => 'referred_to',
                'name'       => $item['name'],
                'sort_order' => $item['sort_order'],
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('lookup_items')->where('type', 'referred_to')->delete();
    }
};
