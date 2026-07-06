<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $range = ["{$date} 00:00:00", "{$date} 23:59:59"];

    $uniqueIdsAll = DB::connection('yeastar')->selectOne(
        "SELECT COUNT(DISTINCT uniqueid) c FROM cdr WHERE datetime BETWEEN ? AND ?", $range
    )->c;

    $uniqueIdsWeb = DB::connection('yeastar')->selectOne(
        "SELECT COUNT(DISTINCT uniqueid) c FROM cdr WHERE datetime BETWEEN ? AND ? AND displayonweb = 1", $range
    )->c;

    $local = App\Models\Call::whereBetween('started_at', $range)->count();

    echo "{$date}: PBX uniqueids(all)={$uniqueIdsAll} displayonweb=1:{$uniqueIdsWeb}  Local calls={$local}\n";
}
