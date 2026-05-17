<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DistressDomainSeeder extends Seeder
{
    public function run(): void
    {
        $domains = [
            'Mental Health / Psychosocial Support',
            'Sexual & Reproductive Health (SRHR)',
            'Gender-Based Violence (GBV)',
            'HIV/AIDS Counselling',
            'Substance Abuse',
            'Child Abuse / Protection',
            'Suicide / Self-Harm',
            'Pregnancy / Family Planning',
            'Relationships / Family Issues',
            'Legal / Justice Support',
            'Socioeconomic Issues',
            'Education / School Issues',
        ];

        foreach ($domains as $i => $name) {
            DB::table('distress_domains')->insertOrIgnore([
                'name'       => $name,
                'sort_order' => $i + 1,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
