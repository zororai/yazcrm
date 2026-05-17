<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class TicketImportController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Tickets/Import');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        // Allow enough memory and time for large files
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $path     = $request->file('file')->store('imports', 'local');
        $fullPath = storage_path('app/' . $path);

        try {
            // Use chunk reader to keep memory low on large sheets
            $reader = IOFactory::createReaderForFile($fullPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($fullPath);
        } catch (\Exception $e) {
            @unlink($fullPath);
            return back()->with('error', 'Could not read file: ' . $e->getMessage());
        }

        $sheet = $spreadsheet->getActiveSheet();
        $rows  = $sheet->toArray(null, true, true, false);

        if (count($rows) < 2) {
            @unlink($fullPath);
            return back()->with('error', 'The file has no data rows.');
        }

        // Build header → index map (case-insensitive, trim whitespace)
        $headers = array_map(fn($h) => mb_strtolower(trim((string) $h)), $rows[0]);
        $map     = array_flip($headers);
        $col     = fn(string $name) => $map[$name] ?? null;

        $imported = 0;
        $skipped  = 0;
        $agentId  = $request->user()->id;

        // Process in chunks to avoid peak memory
        $dataRows = array_slice($rows, 1);
        unset($rows, $spreadsheet); // free memory early

        foreach ($dataRows as $row) {
            $get = function (string $name) use ($row, $col): string {
                $idx = $col($name);
                return $idx !== null ? trim((string) ($row[$idx] ?? '')) : '';
            };

            $callerName = $get('name of caller') ?: $get('contact');
            $purpose    = $get('purpose of call');

            if (!$callerName && !$purpose) {
                $skipped++;
                continue;
            }

            $subject = $callerName
                ? "Call with {$callerName}"
                : "Imported call — {$purpose}";

            Ticket::create([
                'agent_id'                 => $agentId,
                'subject'                  => $subject,
                'description'              => $get("conselloer's notes") ?: $get("counsellor's notes"),
                'status'                   => $this->mapStatus($get('status')),
                'priority'                 => 'medium',
                'mode_of_communication'    => $this->mapMode($get('mode of communication')) ?: null,
                'call_validity'            => $this->mapValidity($get('call validity')) ?: null,
                'purpose_of_call'          => $purpose ?: null,
                'immediate_action_required' => $this->mapBool($get('immediate action required')),
                'caller_age'               => $this->toInt($get('age')),
                'caller_gender'            => $this->mapGender($get('gender')),
                'caller_marital_status'    => $get('marital status') ?: null,
                'key_pops'                 => $get('key pops') ?: null,
                'province'                 => $get('province') ?: null,
                'district'                 => $get('district') ?: null,
                'location'                 => $get('location') ?: null,
                'is_repeat_caller'         => $this->mapRepeat($get('new/repeat')),
                'project'                  => $get('project') ?: null,
                'services_requested'       => $get('services requested') ?: null,
                'second_service_requested' => $get('second service requested') ?: null,
                'number_of_services'       => $this->toInt($get('no. of services')),
                'referred_to'              => $get('referred to') ?: null,
                'uptake_confirmed'         => $this->mapBool($get('confirming uptake of services')),
                'resolved_at'              => $this->parseDate($get('date resolved')),
                'created_at'               => $this->parseDate($get('created on')) ?? now(),
                'updated_at'               => $this->parseDate($get('created on')) ?? now(),
            ]);

            $imported++;
        }

        @unlink($fullPath);

        return redirect()->route('tickets.index')
            ->with('success', "Import complete: {$imported} records imported, {$skipped} skipped.");
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function mapStatus(string $raw): string
    {
        return match (strtolower(trim($raw))) {
            'open'        => 'open',
            'in progress', 'in_progress' => 'in_progress',
            'resolved'    => 'resolved',
            'closed'      => 'closed',
            // Old CRM used YES = call handled/closed
            'yes', '1', 'true' => 'closed',
            default            => 'closed',
        };
    }

    private function mapMode(string $raw): ?string
    {
        return match (strtolower(trim($raw))) {
            'phone', 'phone call', 'call', 'telephone' => 'phone',
            'whatsapp', 'whats app'                    => 'whatsapp',
            'walk in', 'walk-in', 'walkin'             => 'walk_in',
            'email'                                    => 'email',
            'sms'                                      => 'sms',
            default                                    => strtolower($raw) ?: null,
        };
    }

    private function mapValidity(string $raw): ?string
    {
        $v = strtolower(trim($raw));
        if ($v === 'valid')   return 'valid';
        if ($v === 'invalid') return 'invalid';
        return null;
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

    private function mapBool(string $raw): bool
    {
        return in_array(strtolower(trim($raw)), ['yes', '1', 'true', 'y']);
    }

    private function mapRepeat(string $raw): bool
    {
        return strtolower(trim($raw)) === 'repeat';
    }

    private function toInt(string $raw): ?int
    {
        $v = trim($raw);
        return is_numeric($v) ? (int) $v : null;
    }

    private function parseDate(string $raw): ?\Carbon\Carbon
    {
        if (!$raw) return null;

        // Excel stores dates as float serial numbers (e.g. 46112.79)
        if (is_numeric($raw)) {
            try {
                $phpDate = ExcelDate::excelToDateTimeObject((float) $raw);
                return \Carbon\Carbon::instance($phpDate);
            } catch (\Exception) {}
        }

        try {
            return \Carbon\Carbon::parse($raw);
        } catch (\Exception) {
            return null;
        }
    }
}
