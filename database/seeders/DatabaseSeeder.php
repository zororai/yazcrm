<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Admin account ────────────────────────────────────────────────────
        User::firstOrCreate(
            ['email' => 'admin@callcenter.com'],
            [
                'name'      => 'System Admin',
                'password'  => Hash::make('admin123'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        // ── Sample agents ────────────────────────────────────────────────────
        $agents = [
            ['name' => 'Alice Mwangi',   'email' => 'alice@callcenter.com'],
            ['name' => 'Bob Kariuki',    'email' => 'bob@callcenter.com'],
            ['name' => 'Carol Njoroge', 'email' => 'carol@callcenter.com'],
            ['name' => 'David Odhiambo','email' => 'david@callcenter.com'],
        ];

        foreach ($agents as $agent) {
            User::firstOrCreate(
                ['email' => $agent['email']],
                [
                    'name'      => $agent['name'],
                    'password'  => Hash::make('agent123'),
                    'role'      => 'agent',
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Seeded default accounts:');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Admin', 'admin@callcenter.com', 'admin123'],
                ['Agent', 'alice@callcenter.com', 'agent123'],
                ['Agent', 'bob@callcenter.com',   'agent123'],
                ['Agent', 'carol@callcenter.com', 'agent123'],
                ['Agent', 'david@callcenter.com', 'agent123'],
            ]
        );
    }
}
