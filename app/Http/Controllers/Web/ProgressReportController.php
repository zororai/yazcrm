<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ProgressReport;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// Individual Monthly Progress Report — mirrors the org's paper template
// (name/job title/supervisor/date submitted, overall progress narrative,
// a workplan-activities table). Each user files one per calendar month.
class ProgressReportController extends Controller
{
    private const STATUSES = ['pending', 'reviewed', 'approved', 'needs_revision'];

    private function isManager(Request $request): bool
    {
        return in_array($request->user()->role, ['admin', 'director', 'helpline_manager'], true);
    }

    public function index(Request $request): Response
    {
        $user      = $request->user();
        $isManager = $this->isManager($request);
        $month     = Carbon::parse($request->input('month', now()->startOfMonth()->toDateString()))->startOfMonth()->toDateString();

        $mine = ProgressReport::where('user_id', $user->id)
            ->orderByDesc('month')
            ->get(['id', 'month', 'job_title', 'supervisor', 'date_submitted', 'overall_progress', 'kpis', 'provinces', 'male_clients', 'female_clients', 'services', 'activities', 'success_stories', 'status', 'review_notes']);

        $current = $mine->first(fn (ProgressReport $r) => $r->month->toDateString() === $month);

        return Inertia::render('ProgressReports/Index', [
            'month'    => $month,
            'current'  => $current ? [
                'id'                => $current->id,
                'job_title'         => $current->job_title,
                'supervisor'        => $current->supervisor,
                'date_submitted'    => $current->date_submitted?->toDateString(),
                'overall_progress'  => $current->overall_progress,
                'kpis'              => $current->kpis ?? [],
                'provinces'         => $current->provinces ?? [],
                'male_clients'      => $current->male_clients,
                'female_clients'    => $current->female_clients,
                'services'          => $current->services ?? [],
                'activities'        => $current->activities ?? [],
                'success_stories'   => $current->success_stories ?? [],
                'status'            => $current->status,
                'review_notes'      => $current->review_notes,
            ] : null,
            'history' => $mine->map(fn (ProgressReport $r) => [
                'id'    => $r->id,
                'month' => $r->month->toDateString(),
                'submitted' => (bool) $r->date_submitted,
                'status'    => $r->status,
            ])->values(),
            'isManager'    => $isManager,
            // For the Supervisor dropdown — every real user, available to
            // all roles filing a report (not just managers).
            'supervisorOptions' => User::where('role', '!=', 'admin')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    // Manager-only: every submitted report for a given month, across the
    // whole team — its own page, not embedded in the personal report view.
    public function team(Request $request): Response
    {
        abort_unless($this->isManager($request), 403);

        $month = Carbon::parse($request->input('month', now()->startOfMonth()->toDateString()))->startOfMonth()->toDateString();

        $reports = ProgressReport::with('user:id,name,username')
            ->whereDate('month', $month)
            ->orderBy('user_id')
            ->get(['id', 'user_id', 'month', 'job_title', 'supervisor', 'date_submitted', 'overall_progress', 'status'])
            ->map(fn (ProgressReport $r) => [
                'id'         => $r->id,
                'user'       => $r->user,
                'job_title'  => $r->job_title,
                'supervisor' => $r->supervisor,
                'submitted'  => $r->date_submitted?->toDateString(),
                'status'     => $r->status,
            ])->values();

        $submittedUserIds = $reports->pluck('user.id');
        $notSubmitted = User::where('role', '!=', 'admin')
            ->whereNotIn('id', $submittedUserIds)
            ->orderBy('name')
            ->get(['id', 'name', 'username']);

        return Inertia::render('ProgressReports/Team', [
            'month'         => $month,
            'reports'       => $reports,
            'notSubmitted'  => $notSubmitted,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'month'             => 'required|date',
            'job_title'         => 'nullable|string|max:255',
            'supervisor'        => 'nullable|string|max:255',
            'date_submitted'    => 'nullable|date',
            'overall_progress'  => 'nullable|string',
            'kpis'                    => 'nullable|array',
            'kpis.*.title'            => 'nullable|string|max:255',
            'kpis.*.description'      => 'nullable|string',
            'provinces'               => 'nullable|array',
            'provinces.*.province'    => 'nullable|string|max:100',
            'provinces.*.clients'     => 'nullable|integer|min:0',
            'male_clients'            => 'nullable|integer|min:0',
            'female_clients'          => 'nullable|integer|min:0',
            'services'                => 'nullable|array',
            'services.*.service'      => 'nullable|string|max:255',
            'services.*.clients'      => 'nullable|integer|min:0',
            'activities'                    => 'nullable|array',
            'activities.*.activity'         => 'nullable|string|max:500',
            'activities.*.completed'        => 'nullable|string|max:255',
            'activities.*.details'          => 'nullable|string|max:1000',
            'success_stories'                => 'nullable|array',
            'success_stories.*.challenge'    => 'nullable|string',
            'success_stories.*.solution'     => 'nullable|string',
        ]);

        // Always the authenticated user's own report — a report can't be
        // filed on someone else's behalf, even by a manager. Any edit
        // (including editing a previously reviewed/approved report) puts
        // it back to "pending" — it needs a fresh look from a reviewer.
        ProgressReport::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'month'   => Carbon::parse($validated['month'])->startOfMonth()->toDateString(),
            ],
            [
                'job_title'        => $validated['job_title'] ?? null,
                'supervisor'       => $validated['supervisor'] ?? null,
                'date_submitted'   => $validated['date_submitted'] ?? null,
                'overall_progress' => $validated['overall_progress'] ?? null,
                'kpis'             => array_values(array_filter(
                    $validated['kpis'] ?? [],
                    fn ($k) => filled($k['title'] ?? null) || filled($k['description'] ?? null)
                )),
                'provinces'        => array_values(array_filter(
                    $validated['provinces'] ?? [],
                    fn ($p) => filled($p['province'] ?? null)
                )),
                'male_clients'     => $validated['male_clients'] ?? null,
                'female_clients'   => $validated['female_clients'] ?? null,
                'services'         => array_values(array_filter(
                    $validated['services'] ?? [],
                    fn ($s) => filled($s['service'] ?? null)
                )),
                'activities'       => array_values(array_filter(
                    $validated['activities'] ?? [],
                    fn ($a) => filled($a['activity'] ?? null) || filled($a['completed'] ?? null) || filled($a['details'] ?? null)
                )),
                'success_stories'  => array_values(array_filter(
                    $validated['success_stories'] ?? [],
                    fn ($s) => filled($s['challenge'] ?? null) || filled($s['solution'] ?? null)
                )),
                'status'       => 'pending',
                'reviewed_by'  => null,
                'reviewed_at'  => null,
                'review_notes' => null,
            ],
        );

        return back()->with('success', 'Progress report saved.');
    }

    // Manager viewing one agent's report for a given month.
    public function show(Request $request, ProgressReport $report): Response
    {
        abort_unless($report->user_id === $request->user()->id || $this->isManager($request), 403);

        return Inertia::render('ProgressReports/Show', [
            'report' => [
                'id'                => $report->id,
                'user'              => $report->user()->first(['id', 'name', 'username']),
                'month'             => $report->month->toDateString(),
                'job_title'         => $report->job_title,
                'supervisor'        => $report->supervisor,
                'date_submitted'    => $report->date_submitted?->toDateString(),
                'overall_progress'  => $report->overall_progress,
                'kpis'              => $report->kpis ?? [],
                'provinces'         => $report->provinces ?? [],
                'male_clients'      => $report->male_clients,
                'female_clients'    => $report->female_clients,
                'services'          => $report->services ?? [],
                'activities'        => $report->activities ?? [],
                'success_stories'   => $report->success_stories ?? [],
                'status'            => $report->status,
                'reviewer'          => $report->reviewer()->first(['id', 'name']),
                'reviewed_at'       => $report->reviewed_at?->toDateTimeString(),
                'review_notes'      => $report->review_notes,
            ],
            'isManager' => $this->isManager($request),
            'statuses'  => self::STATUSES,
        ]);
    }

    public function exportPdf(Request $request, ProgressReport $report): \Illuminate\Http\Response
    {
        abort_unless($report->user_id === $request->user()->id || $this->isManager($request), 403);

        $report->load('user:id,name');

        $pdf = new \FPDF('P', 'mm', 'A4');
        $pdf->SetMargins(15, 15, 15);
        $pdf->AddPage();
        $usableWidth = 210 - 30;

        $pdf->SetFont('Arial', 'B', 15);
        $pdf->Cell(0, 8, 'INDIVIDUAL MONTHLY PROGRESS REPORT', 0, 1);
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->SetTextColor(110, 110, 110);
        $pdf->MultiCell($usableWidth, 5, "SUMMARY OF PORTFOLIO DETAILS: (PLEASE INSERT YOUR KPI's AS PER CONTRACT)");
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);

        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell($usableWidth / 2, 6, 'NAME: ' . $this->ascii($report->user->name));
        $pdf->Cell($usableWidth / 2, 6, 'JOB TITLE: ' . $this->ascii($report->job_title ?? ''));
        $pdf->Ln();
        $pdf->Cell($usableWidth / 2, 6, 'MONTH: ' . $report->month->format('F Y'));
        $pdf->Cell($usableWidth / 2, 6, 'SUPERVISOR: ' . $this->ascii($report->supervisor ?? ''));
        $pdf->Ln();
        $pdf->Cell($usableWidth, 6, 'DATE SUBMITTED: ' . ($report->date_submitted?->format('d/m/Y') ?? ''));
        $pdf->Ln(8);

        // KPI sections
        if (! empty($report->kpis)) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 7, 'MONTHLY OVERALL PROGRESS', 0, 1);
            $pdf->SetFont('Arial', '', 10);
            foreach ($report->kpis as $kpi) {
                $pdf->SetFont('Arial', 'BU', 10);
                $pdf->MultiCell($usableWidth, 6, $this->ascii($kpi['title'] ?? ''));
                $pdf->SetFont('Arial', '', 10);
                $pdf->MultiCell($usableWidth, 5, $this->ascii($kpi['description'] ?? ''));
                $pdf->Ln(3);
            }
        } elseif ($report->overall_progress) {
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 7, 'MONTHLY OVERALL PROGRESS', 0, 1);
            $pdf->SetFont('Arial', '', 10);
            $pdf->MultiCell($usableWidth, 5, $this->ascii($report->overall_progress));
            $pdf->Ln(3);
        }

        // Province table
        if (! empty($report->provinces)) {
            $this->pdfTable($pdf, 'Province', 'Number of Clients Reached', $report->provinces, 'province', 'clients', $usableWidth);
        }

        // Gender
        if ($report->male_clients !== null || $report->female_clients !== null) {
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'Clients reached: ' . ($report->male_clients ?? 0) . ' males, ' . ($report->female_clients ?? 0) . ' females', 0, 1);
            $pdf->Ln(3);
        }

        // Services table
        if (! empty($report->services)) {
            $this->pdfTable($pdf, 'Services', 'Number of Clients Reached', $report->services, 'service', 'clients', $usableWidth);
        }

        // Workplan activities
        if (! empty($report->activities)) {
            if ($pdf->GetY() > 240) $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->Cell(0, 7, 'WORKPLAN ACTIVITIES', 0, 1);
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->SetFillColor(230, 230, 230);
            $colA = $usableWidth * 0.35; $colB = $usableWidth * 0.15; $colC = $usableWidth * 0.5;
            $pdf->Cell($colA, 7, 'Activity', 1, 0, 'L', true);
            $pdf->Cell($colB, 7, 'Completed', 1, 0, 'L', true);
            $pdf->Cell($colC, 7, 'Progress Details', 1, 1, 'L', true);
            $pdf->SetFont('Arial', '', 9);
            foreach ($report->activities as $a) {
                $y0 = $pdf->GetY(); $x0 = $pdf->GetX();
                $pdf->MultiCell($colA, 6, $this->ascii($a['activity'] ?? ''), 1);
                $h1 = $pdf->GetY() - $y0;
                $pdf->SetXY($x0 + $colA, $y0);
                $pdf->MultiCell($colB, 6, $this->ascii($a['completed'] ?? ''), 1);
                $h2 = $pdf->GetY() - $y0;
                $pdf->SetXY($x0 + $colA + $colB, $y0);
                $pdf->MultiCell($colC, 6, $this->ascii($a['details'] ?? ''), 1);
                $h3 = $pdf->GetY() - $y0;
                $pdf->SetY($y0 + max($h1, $h2, $h3));
            }
            $pdf->Ln(4);
        }

        // Success stories
        if (! empty($report->success_stories)) {
            if ($pdf->GetY() > 230) $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 8, 'SUCCESS STORIES', 0, 1, 'C');
            $pdf->Ln(2);
            foreach ($report->success_stories as $i => $s) {
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(0, 6, ($i + 1) . '. Challenge:', 0, 1);
                $pdf->SetFont('Arial', '', 10);
                $pdf->MultiCell($usableWidth, 5, $this->ascii($s['challenge'] ?? ''));
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(0, 6, 'Solution:', 0, 1);
                $pdf->SetFont('Arial', '', 10);
                $pdf->MultiCell($usableWidth, 5, $this->ascii($s['solution'] ?? ''));
                $pdf->Ln(3);
            }
        }

        $filename = 'progress-report-' . $report->id . '.pdf';

        return response($pdf->Output('S'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function pdfTable(\FPDF $pdf, string $col1, string $col2, array $rows, string $key1, string $key2, float $usableWidth): void
    {
        if ($pdf->GetY() > 250) $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(230, 230, 230);
        $colA = $usableWidth * 0.6; $colB = $usableWidth * 0.4;
        $pdf->Cell($colA, 7, $col1, 1, 0, 'L', true);
        $pdf->Cell($colB, 7, $col2, 1, 1, 'L', true);
        $pdf->SetFont('Arial', '', 9);
        foreach ($rows as $row) {
            $pdf->Cell($colA, 6, $this->ascii($row[$key1] ?? ''), 1);
            $pdf->Cell($colB, 6, (string) ($row[$key2] ?? ''), 1, 1);
        }
        $pdf->Ln(4);
    }

    // FPDF's core fonts are Latin-1 only — strip anything outside that to
    // avoid mojibake (matches the same guard used for the Timetable/Success
    // Story PDFs).
    private function ascii(string $text): string
    {
        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }

    // The responsible authority (manager/supervisor) reviews a report and
    // sets its status — the agent sees this reflected on their own view.
    public function updateStatus(Request $request, ProgressReport $report): RedirectResponse
    {
        abort_unless($this->isManager($request), 403);

        $validated = $request->validate([
            'status'        => 'required|in:' . implode(',', self::STATUSES),
            'review_notes'  => 'nullable|string|max:1000',
        ]);

        $report->update([
            'status'       => $validated['status'],
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by'  => $request->user()->id,
            'reviewed_at'  => now(),
        ]);

        return back()->with('success', 'Report status updated.');
    }
}
