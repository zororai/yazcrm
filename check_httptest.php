<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::firstOrCreate(
    ['email' => 'diag-test@internal.local'],
    ['name' => 'Diag Test', 'password' => bcrypt('diagtest12345'), 'role' => 'admin', 'is_active' => true]
);
echo "User id: {$user->id}\n";
