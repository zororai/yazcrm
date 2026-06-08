<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PriorityAction;
use App\Models\Risk;
use App\Support\RiskScorer;
use Illuminate\Http\Response;

class RiskReportController extends Controller
{
    public function export(): Response
    {
        $risks   = Risk::with(['asset', 'controls', 'priorityAction'])->orderBy('residual_score', 'desc')->get();
        $actions = PriorityAction::with(['risk.asset'])->orderByRaw("FIELD(priority,'critical','high','medium','low')")->get();

        $risks->each(function ($risk) {
            $risk->band = RiskScorer::band((int) ($risk->residual_score ?? $risk->inherent_score));
        });

        return response()->view('risk.report', compact('risks', 'actions'))
            ->header('Content-Type', 'text/html');
    }
}
