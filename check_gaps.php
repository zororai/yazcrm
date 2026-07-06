<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Overall date range available on PBX vs locally
$pbxRange = DB::connection('yeastar')->selectOne("SELECT MIN(datetime) mn, MAX(datetime) mx, COUNT(DISTINCT uniqueid) c FROM cdr");
echo "PBX CDR range: {$pbxRange->mn} to {$pbxRange->mx}, distinct calls: {$pbxRange->c}\n";

$localRange = App\Models\Call::selectRaw('MIN(started_at) mn, MAX(started_at) mx, COUNT(*) c')->first();
echo "Local Call range: {$localRange->mn} to {$localRange->mx}, count: {$localRange->c}\n\n";

// Per-day comparison over full history in the PBX table
$days = DB::connection('yeastar')->select("
    SELECT DATE(datetime) d, COUNT(DISTINCT uniqueid) c
    FROM cdr
    GROUP BY DATE(datetime)
    ORDER BY d
");

foreach ($days as $day) {
    $local = App\Models\Call::whereDate('started_at', $day->d)->count();
    $diff = $day->c - $local;
    if (abs($diff) > 2) {
        echo "{$day->d}: PBX={$day->c} Local={$local}  DIFF={$diff}\n";
    }
}
echo "Done scanning.\n";
