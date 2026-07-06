<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$today = ['2026-07-06 00:00:00', '2026-07-06 23:59:59'];

$totalCdr = DB::connection('yeastar')->selectOne(
    "SELECT COUNT(*) c FROM cdr WHERE datetime BETWEEN ? AND ?", $today
)->c;

$displayOnWeb = DB::connection('yeastar')->selectOne(
    "SELECT COUNT(*) c FROM cdr WHERE datetime BETWEEN ? AND ? AND displayonweb = 1", $today
)->c;

$uniqueIds = DB::connection('yeastar')->selectOne(
    "SELECT COUNT(DISTINCT uniqueid) c FROM cdr WHERE datetime BETWEEN ? AND ? AND displayonweb = 1", $today
)->c;

$localCallsToday = App\Models\Call::whereBetween('started_at', $today)->count();

echo "Total CDR rows today: {$totalCdr}\n";
echo "displayonweb=1 rows today: {$displayOnWeb}\n";
echo "distinct uniqueids (displayonweb=1) today: {$uniqueIds}\n";
echo "Local Call rows (started_at today): {$localCallsToday}\n";

// distinct uniqueids overall (no displayonweb filter)
$uniqueIdsAll = DB::connection('yeastar')->selectOne(
    "SELECT COUNT(DISTINCT uniqueid) c FROM cdr WHERE datetime BETWEEN ? AND ?", $today
)->c;
echo "distinct uniqueids (ALL, no filter) today: {$uniqueIdsAll}\n";
