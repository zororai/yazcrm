<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;

class TicketImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Tickets/Import');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $path = $request->file('file')->store('imports', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            $spreadsheet = IOFactory::load($fullPath);
        } catch (\Exception $e) {
            return back()->with('error', 'Could not read file: ' . $e->getMessage());
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, false);

        if (count($rows) < 2) {
            return back()->with('error', 'The file has no data rows.');
        }

        // Build header → column-index map from row 0
        $headers = array_map(fn($h) => mb_strtolower(trim((string) $h)), $rows[0]);
        $map = array_flip($headers);

        $col = fn(string $name) => $map[$name] ?? null;

        $imported = 0;
        $skipped  = 0;
        $agentId  = $request->user()->id;

        foreach (array_slice($rows, 1) as $row) {
            $get = function (string $name) use ($row, $col) {
                $idx = $col($name);
                return $idx !== null ? trim((string) ($row[$idx] ?? '')) : '';
            };

            // Require at least a caller name or purpose to be meaningful
            $callerName = $get('name of caller') ?: $get('contact');
            $purpose    = $get('purpose of call');

            if (!$callerName && !$purpose) {
                $skipped++;
                continue;
            }

            $subject = $callerName
                ? "Call with {$callerName}"
                : "Imported call — {$purpose}";

            // Map New/Repeat → boolean
            $repeatRaw = strtolower($get('new/repeat'));
            $isRepeat  = in_array($repeatRaw, ['repeat', 'yes', '1', 'true']);

            // Map Uptake → boolean
            $uptakeRaw = strtolower($get('confirming uptake of services'));
            $uptake    = in_array($uptakeRaw, ['yes', '1', 'true', 'confirmed']);

            // Map Immediate Action
            $actionRaw = strtolower($get('immediate action required'));
            $action    = in_array($actionRaw, ['yes', '1', 'true']);

            // Map status
            $statusMap = [
                'resolved' => 'resolved',
                'closed'   => 'closed',
                'open'     => 'open',
                'in progress' => 'in_progress',
                'in_progress' => 'in_progress',
            ];
            $rawStatus = strtolower($get('status'));
            $status    = $statusMap[$rawStatus] ?? 'closed';

            // Dates
            $createdAt  = $this->parseDate($get('created on'));
            $resolvedAt = $this->parseDate($get('date resolved'));

            $age = $get('age');
            $numServices = $get('no. of services');

            Ticket::create([
                'agent_id'                 => $agentId,
                'subject'                  => $subject,
                'description'              => $get('conselloer\'s notes') ?: $get('counsellor\'s notes'),
                'status'                   => $status,
                'priority'                 => 'medium',
                'mode_of_communication'    => $get('mode of communication') ?: null,
                'call_validity'            => $get('call validity') ?: null,
                'purpose_of_call'          => $purpose ?: null,
                'immediate_action_required' => $action,
                'caller_age'               => is_numeric($age) ? (int) $age : null,
                'caller_gender'            => $this->mapGender($get('gender')),
                'caller_marital_status'    => $get('marital status') ?: null,
                'key_pops'                 => $get('key pops') ?: null,
                'province'                 => $get('province') ?: null,
                'district'                 => $get('district') ?: null,
                'location'                 => $get('location') ?: null,
                'is_repeat_caller'         => $isRepeat,
                'project'                  => $get('project') ?: null,
                'services_requested'       => $get('services requested') ?: null,
                'second_service_requested' => $get('second service requested') ?: null,
                'number_of_services'       => is_numeric($numServices) ? (int) $numServices : null,
                'referred_to'              => $get('referred to') ?: null,
                'uptake_confirmed'         => $uptake,
                'resolved_at'              => $resolvedAt,
                'created_at'               => $createdAt ?? now(),
                'updated_at'               => $createdAt ?? now(),
            ]);

            $imported++;
        }

        @unlink($fullPath);

        return redirect()->route('tickets.index')
            ->with('success', "Import complete: {$imported} records imported, {$skipped} skipped.");
    }

    private function parseDate(string $raw): ?\Carbon\Carbon
    {
        if (!$raw) return null;
        try {
            return \Carbon\Carbon::parse($raw);
        } catch (\Exception) {
            return null;
        }
    }

    private function mapGender(string $raw): ?string
    {
        return match (strtolower(trim($raw))) {
            'male', 'm'   => 'male',
            'female', 'f' => 'female',
            'other'       => 'other',
            default       => null,
        };
    }
}
