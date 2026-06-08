<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\PriorityAction;
use App\Models\Risk;
use App\Support\RiskScorer;
use Inertia\Inertia;
use Inertia\Response;

class RiskDashboardController extends Controller
{
    public function index(): Response
    {
        $risks = Risk::with(['asset', 'controls', 'priorityAction'])->get();

        // Heat map: 5x5 grid, count risks per likelihood/impact cell
        $heatMap = [];
        for ($l = 1; $l <= 5; $l++) {
            for ($i = 1; $i <= 5; $i++) {
                $heatMap[$l][$i] = 0;
            }
        }
        foreach ($risks as $risk) {
            $l = min(max((int) $risk->likelihood, 1), 5);
            $i = min(max((int) $risk->impact, 1), 5);
            $heatMap[$l][$i]++;
        }

        // Summary by band
        $bandCounts = ['red' => 0, 'amber' => 0, 'green' => 0];
        foreach ($risks as $risk) {
            $band = RiskScorer::band((int) ($risk->residual_score ?? $risk->inherent_score));
            $bandCounts[$band]++;
        }

        // By category
        $byCategory = $risks->groupBy('category')->map->count();

        // Top 5 residual risks
        $topRisks = $risks->sortByDesc('residual_score')->take(5)->values();

        // Actions summary
        $actions        = PriorityAction::with(['risk.asset'])->get();
        $actionSummary  = $actions->groupBy('status')->map->count();
        $overdueActions = $actions->filter(fn ($a) => $a->status !== 'done' && $a->target_date && $a->target_date->isPast())->count();

        $assetsNoRisks = Asset::doesntHave('risks')->count();

        return Inertia::render('Risk/Dashboard', [
            'risks'          => $risks,
            'heatMap'        => $heatMap,
            'bandCounts'     => $bandCounts,
            'byCategory'     => $byCategory,
            'topRisks'       => $topRisks,
            'actionSummary'  => $actionSummary,
            'overdueActions' => $overdueActions,
            'assetsNoRisks'  => $assetsNoRisks,
            'totalActions'   => $actions->count(),
        ]);
    }
}
