<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SbcSignup;
use App\Services\CertificateService;
use App\Services\GoogleSheetsService;
use App\Services\UchatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SbcController extends Controller
{
    public function __construct(private GoogleSheetsService $sheets) {}

    public function index(Request $request): Response
    {
        $search = trim($request->get('search', ''));
        $sheet  = $request->get('sheet', 'SBC Signups');

        $query = SbcSignup::where('sheet', $sheet)->orderByDesc('date')->orderBy('first_name');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name',   'like', "%{$search}%")
                  ->orWhere('surname',     'like', "%{$search}%")
                  ->orWhere('phone_number','like', "%{$search}%")
                  ->orWhere('location',    'like', "%{$search}%");
            });
        }

        $records = $query->paginate(50)->withQueryString();

        // Counts per sheet for tab badges
        $counts = [
            'SBC Signups'             => SbcSignup::where('sheet', 'SBC Signups')->count(),
            'Certificates To Process' => SbcSignup::where('sheet', 'Certificates To Process')->count(),
        ];

        // Last sync time
        $lastSync = SbcSignup::where('sheet', $sheet)->max('synced_at');

        return Inertia::render('Sbc/Index', [
            'records'         => $records,
            'counts'          => $counts,
            'sheet'           => $sheet,
            'filters'         => ['search' => $search],
            'lastSync'        => $lastSync,
            'hasTemplate'     => file_exists(storage_path('app/private/certificates/template.pdf')),
        ]);
    }

    public function certificate(SbcSignup $signup): HttpResponse
    {
        try {
            $pdf = app(CertificateService::class)->generate($signup);

            $signup->update([
                'certificate_status'          => 'downloaded',
                'certificate_downloaded_at'   => now(),
            ]);

            $filename = 'Certificate_' . str_replace(' ', '_', strtoupper(trim($signup->first_name . '_' . $signup->surname))) . '.pdf';

            return response($pdf, 200, [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => "inline; filename=\"{$filename}\"",
            ]);
        } catch (\RuntimeException $e) {
            return response()->view('certificates.sbc', ['signup' => $signup]);
        }
    }

    public function sendWhatsapp(SbcSignup $signup): RedirectResponse
    {
        if (! $signup->phone_number) {
            return back()->with('error', 'This record has no phone number.');
        }

        try {
            $pdf = app(CertificateService::class)->generate($signup);
        } catch (\RuntimeException $e) {
            return back()->with('error', 'Certificate template not uploaded yet. Please upload the template first.');
        }

        // Save PDF to public storage so uChat can fetch it by URL
        $filename  = 'cert_' . $signup->id . '_' . time() . '.pdf';
        $storagePath = 'certificates/' . $filename;
        Storage::disk('public')->put($storagePath, $pdf);
        $fileUrl = Storage::disk('public')->url($storagePath);

        // Ensure absolute URL
        if (! str_starts_with($fileUrl, 'http')) {
            $fileUrl = config('app.url') . $fileUrl;
        }

        $name    = trim($signup->first_name . ' ' . $signup->surname);
        $caption = "🎓 Dear {$name}, please find your YALeP Certificate of Completion attached.";

        $sent = app(UchatService::class)->sendFile($signup->phone_number, $fileUrl, $caption);

        if ($sent) {
            $signup->update([
                'certificate_status'        => 'sent',
                'whatsapp_sent_at'          => now(),
            ]);
            return back()->with('success', "Certificate sent to {$signup->phone_number} on WhatsApp.");
        }

        return back()->with('error', 'Failed to send via WhatsApp. Check the uChat API token and that the phone number is subscribed to the bot.');
    }

    public function uploadTemplate(Request $request): RedirectResponse
    {
        $request->validate(['template' => 'required|file|mimes:pdf|max:10240']);

        $dir = storage_path('app/private/certificates');
        if (! is_dir($dir)) { mkdir($dir, 0755, true); }

        $request->file('template')->move($dir, 'template.pdf');

        return back()->with('success', 'Certificate template uploaded successfully.');
    }

    public function sync(): RedirectResponse
    {
        $id      = config('google.sbc_spreadsheet_id');
        $results = [];

        foreach (['SBC Signups', 'Certificates To Process'] as $sheetName) {
            $results[] = $this->sheets->syncSheetToDb($id, $sheetName);
        }

        $totalInserted = array_sum(array_column($results, 'inserted'));
        $totalUpdated  = array_sum(array_column($results, 'updated'));
        $errors        = array_filter(array_column($results, 'error'));

        if ($errors) {
            return back()->with('error', 'Sync error: ' . implode('; ', $errors));
        }

        return back()->with('success', "Sync complete — {$totalInserted} new, {$totalUpdated} updated.");
    }
}
