<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class TicketImportController extends Controller
{
    private const CHUNK = 500;

    public function create(): Response
    {
        return Inertia::render('Tickets/Import');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:20480',
        ]);

        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $path     = $request->file('file')->store('imports', 'local');
        $fullPath = Storage::disk('local')->path($path);

        try {
            $reader = IOFactory::createReaderForFile($fullPath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($fullPath);
        } catch (\Exception $e) {
            Storage::disk('local')->delete($path);
            return back()->with('error', 'Could not read file: ' . $e->getMessage());
        }

        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        unset($spreadsheet); // free memory

        if (count($rows) < 2) {
            Storage::disk('local')->delete($path);
            return back()->with('error', 'The file has no data rows.');
        }

        // Build header → index map
        $headers = array_map(fn($h) => mb_strtolower(trim((string) $h)), array_shift($rows));
        $map     = array_flip($headers);
        $col     = fn(string $name): ?int => $map[$name] ?? null;

        $agentId  = $request->user()->id;
        $now      = now()->toDateTimeString();
        $imported = 0;
        $skipped  = 0;
        $chunk    = [];

        foreach ($rows as $row) {
            $get = fn(string $name): string => ($idx = $col($name)) !== null
                ? trim((string) ($row[$idx] ?? ''))
                : '';

            $callerName = $get('name of caller') ?: $get('contact');
            $purpose    = $get('purpose of call');

            if (!$callerName && !$purpose) {
                $skipped++;
                continue;
            }

            $createdAt = $this->parseDate($get('created on'));

            $chunk[] = [
                'agent_id'                  => $agentId,
                'subject'                   => $callerName ? "Call with {$callerName}" : "Imported call — {$purpose}",
                'description'               => $get("conselloer's notes") ?: $get("counsellor's notes") ?: null,
                'status'                    => $this->mapStatus($get('status')),
                'priority'                  => 'medium',
                'call_source'               => $get('call source') ?: null,
                'call_destination'          => $get('call destination') ?: null,
                'mode_of_communication'     => $this->mapMode($get('mode of communication')),
                'call_validity'             => $this->mapValidity($get('call validity')),
                'purpose_of_call'           => $purpose ?: null,
                'immediate_action_required' => $this->mapBool($get('immediate action required')) ? 1 : 0,
                'caller_age'                => $this->toInt($get('age')),
                'caller_gender'             => $this->mapGender($get('gender')),
                'caller_marital_status'     => $get('marital status') ?: null,
                'key_pops'                  => $get('key pops') ?: null,
                'province'                  => $get('province') ?: null,
                'district'                  => $get('district') ?: null,
                'location'                  => $get('location') ?: null,
                'is_repeat_caller'          => $this->mapRepeat($get('new/repeat')) ? 1 : 0,
                'project'                   => $get('project') ?: null,
                'services_requested_before' => $get('services requested before') ?: null,
                'services_requested'        => $get('services requested') ?: null,
                'second_service_requested'  => $get('second service requested') ?: null,
                'number_of_services'        => $this->toInt($get('no. of services')),
                'referred_to'               => $get('referred to') ?: null,
                'radio_channel'             => $get('radio channel') ?: null,
                'uptake_confirmed'          => $this->mapBool($get('confirming uptake of services')) ? 1 : 0,
                'resolved_at'               => $this->parseDate($get('date resolved'))?->toDateTimeString(),
                'deleted_at'                => null,
                'created_at'                => $createdAt?->toDateTimeString() ?? $now,
                'updated_at'                => $createdAt?->toDateTimeString() ?? $now,
            ];

            $imported++;

            if (count($chunk) >= self::CHUNK) {
                DB::table('tickets')->insert($chunk);
                $chunk = [];
            }
        }

        // Insert remaining rows
        if ($chunk) {
            DB::table('tickets')->insert($chunk);
        }

        unset($rows);
        Storage::disk('local')->delete($path);

        return redirect()->route('tickets.index')
            ->with('success', "Import complete: {$imported} records imported, {$skipped} skipped.");
    }

    // ── Mappers ───────────────────────────────────────────────────────────────

    private function mapStatus(string $raw): string
    {
        return match (strtolower(trim($raw))) {
            'open'                       => 'open',
            'in progress', 'in_progress' => 'in_progress',
            'resolved'                   => 'resolved',
            default                      => 'closed',
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
        return match (strtolower(trim($raw))) {
            'valid'   => 'valid',
            'invalid' => 'invalid',
            default   => null,
        };
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
        return is_numeric(trim($raw)) ? (int) $raw : null;
    }

    private function parseDate(string $raw): ?\Carbon\Carbon
    {
        if ($raw === '') return null;

        // Excel serial number (e.g. 46112.79)
        if (is_numeric($raw)) {
            try {
                return \Carbon\Carbon::instance(ExcelDate::excelToDateTimeObject((float) $raw));
            } catch (\Exception) {}
        }

        try {
            return \Carbon\Carbon::parse($raw);
        } catch (\Exception) {
            return null;
        }
    }
}
