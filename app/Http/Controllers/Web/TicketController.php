<?php

namespace App\Http\Controllers\Web;

use Anthropic\Laravel\Facades\Anthropic;
use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\Client;
use App\Models\LookupItem;
use App\Models\Ticket;
use App\Models\UrgentCase;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Ticket::with(['client', 'agent', 'call.recording:id,call_id'])->latest();

        // Agents only see their own tickets
        if ($request->user()->role !== 'admin') {
            $query->where('agent_id', $request->user()->id);
        }

        if ($request->filled('status'))         { $query->where('status',            $request->status); }
        if ($request->filled('priority'))        { $query->where('priority',           $request->priority); }
        if ($request->filled('agent_id'))        { $query->where('agent_id',           $request->agent_id); }
        if ($request->filled('gender'))          { $query->where('caller_gender',      $request->gender); }
        if ($request->filled('service'))         { $query->where('services_requested', $request->service); }
        if ($request->filled('project'))         { $query->where('project',            $request->project); }
        if ($request->filled('province'))        { $query->where('province',           $request->province); }
        if ($request->filled('district'))        { $query->where('district',            $request->district); }
        if ($request->filled('location'))        { $query->where('location',            'like', "%{$request->location}%"); }
        if ($request->filled('referred_to'))     { $query->where('referred_to',         $request->referred_to); }
        if ($request->filled('repeat_caller'))   { $query->where('is_repeat_caller',   $request->repeat_caller); }
        if ($request->filled('date_from'))       { $query->whereDate('created_at', '>=', $request->date_from); }
        if ($request->filled('date_to'))         { $query->whereDate('created_at', '<=', $request->date_to); }
        if ($request->filled('call_direction'))  {
            // inbound/outbound mapped via mode_of_communication or joined calls table
            if ($request->call_direction === 'inbound') {
                $query->whereHas('call', fn ($q) => $q->where('direction', 'inbound'));
            } elseif ($request->call_direction === 'outbound') {
                $query->whereHas('call', fn ($q) => $q->where('direction', 'outbound'));
            }
        }
        if ($request->filled('age_group')) {
            match ($request->age_group) {
                'under_18'  => $query->whereBetween('caller_age', [1,  17]),
                '18_24'     => $query->whereBetween('caller_age', [18, 24]),
                '25_34'     => $query->whereBetween('caller_age', [25, 34]),
                '35_44'     => $query->whereBetween('caller_age', [35, 44]),
                '45_plus'   => $query->where('caller_age', '>=', 45),
                default     => null,
            };
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('subject', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%")
                  ->orWhere('contact_number', 'like', "%{$request->search}%");
            });
        }

        $tickets = $query->paginate(20)->withQueryString();
        $clients = Client::select('id', 'name', 'phone')->orderBy('name')->get();
        $agents  = User::where('is_active', true)->select('id', 'name')->orderBy('name')->get();

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'clients' => $clients,
            'agents'  => $agents,
            'filters' => $request->only([
                'status', 'priority', 'search',
                'agent_id', 'gender', 'service', 'project',
                'province', 'district', 'location', 'referred_to',
                'repeat_caller', 'call_direction', 'age_group',
                'date_from', 'date_to',
            ]),
            'keyPops'             => LookupItem::where('type', 'key_pops')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'modesOfCommunication' => LookupItem::where('type', 'mode_of_communication')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'projects'             => LookupItem::where('type', 'project')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'servicesRequested'        => LookupItem::where('type', 'service_requested')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'secondServicesRequested'  => LookupItem::where('type', 'service_requested')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'servicesRequestedBefore'  => LookupItem::where('type', 'service_requested')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'referredTo'               => LookupItem::where('type', 'referred_to')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'serviceCategories'        => LookupItem::where('type', 'service_requested')->where('is_active', true)->get(['name', 'classification_categories'])->mapWithKeys(fn ($i) => [$i->name => $i->classification_categories ?? []]),
        ]);
    }

    public function export(Request $request): HttpResponse
    {
        $query = Ticket::latest();

        if ($request->user()->role !== 'admin') {
            $query->where('agent_id', $request->user()->id);
        }

        if ($request->filled('status'))        { $query->where('status',            $request->status); }
        if ($request->filled('priority'))       { $query->where('priority',           $request->priority); }
        if ($request->filled('agent_id'))       { $query->where('agent_id',           $request->agent_id); }
        if ($request->filled('gender'))         { $query->where('caller_gender',      $request->gender); }
        if ($request->filled('service'))        { $query->where('services_requested', $request->service); }
        if ($request->filled('project'))        { $query->where('project',            $request->project); }
        if ($request->filled('province'))       { $query->where('province',           $request->province); }
        if ($request->filled('district'))       { $query->where('district',           $request->district); }
        if ($request->filled('location'))       { $query->where('location',           'like', "%{$request->location}%"); }
        if ($request->filled('referred_to'))    { $query->where('referred_to',        $request->referred_to); }
        if ($request->filled('repeat_caller'))  { $query->where('is_repeat_caller',   $request->repeat_caller); }
        if ($request->filled('date_from'))      { $query->whereDate('created_at', '>=', $request->date_from); }
        if ($request->filled('date_to'))        { $query->whereDate('created_at', '<=', $request->date_to); }
        if ($request->filled('age_group')) {
            match ($request->age_group) {
                'under_18' => $query->whereBetween('caller_age', [1,  17]),
                '18_24'    => $query->whereBetween('caller_age', [18, 24]),
                '25_34'    => $query->whereBetween('caller_age', [25, 34]),
                '35_44'    => $query->whereBetween('caller_age', [35, 44]),
                '45_plus'  => $query->where('caller_age', '>=', 45),
                default    => null,
            };
        }
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('subject', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%")
                  ->orWhere('contact_number', 'like', "%{$request->search}%");
            });
        }

        $csvHeaders = [
            'ID', 'Date', 'Agent', 'Client', 'Contact Number', 'Subject',
            'Purpose of Call', 'Mode of Communication', 'Status', 'Priority',
            'Gender', 'Age', 'Marital Status', 'Key Pops',
            'Province', 'District', 'Location',
            'Services Requested', 'Second Service', 'Services Requested Before',
            'Number of Services', 'Referred To', 'Uptake Confirmed', 'Referral Uptake Date',
            'Repeat Caller', 'Project', 'Call Validity', 'Classification',
            'Psychosocial Type', 'Follow Up Date', 'Resolved At',
        ];

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $csvHeaders);

        $query->with(['client', 'agent'])->chunk(200, function ($chunk) use ($handle) {
            foreach ($chunk as $t) {
                fputcsv($handle, [
                    $t->id,
                    $t->created_at?->format('Y-m-d H:i'),
                    $t->agent?->name ?? '',
                    $t->client?->name ?? '',
                    $t->contact_number ?? '',
                    $t->subject ?? '',
                    $t->purpose_of_call ?? '',
                    $t->mode_of_communication ?? '',
                    $t->status ?? '',
                    $t->priority ?? '',
                    $t->caller_gender ?? '',
                    $t->caller_age ?? '',
                    $t->caller_marital_status ?? '',
                    $t->key_pops ?? '',
                    $t->province ?? '',
                    $t->district ?? '',
                    $t->location ?? '',
                    $t->services_requested ?? '',
                    $t->second_service_requested ?? '',
                    $t->services_requested_before ?? '',
                    $t->number_of_services ?? '',
                    $t->referred_to ?? '',
                    $t->uptake_confirmed ? 'Yes' : 'No',
                    $t->referral_uptake_date ?? '',
                    $t->is_repeat_caller ? 'Yes' : 'No',
                    $t->project ?? '',
                    $t->call_validity ?? '',
                    is_array($t->classification) ? implode(', ', array_map(fn($v) => is_array($v) ? implode('|', $v) : (string)$v, $t->classification)) : ($t->classification ?? ''),
                    $t->psychosocial_type ?? '',
                    $t->follow_up_date ?? '',
                    $t->resolved_at?->format('Y-m-d H:i') ?? '',
                ]);
            }
        });

        rewind($handle);
        $csv      = stream_get_contents($handle);
        fclose($handle);

        $filename = 'tickets-export-' . now()->format('Y-m-d') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store',
        ]);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        if ($request->user()->role !== 'admin' && $ticket->agent_id !== $request->user()->id) {
            abort(403);
        }

        $ticket->load(['client', 'agent', 'call']);

        return Inertia::render('Tickets/Show', [
            'ticket'                  => $ticket,
            'keyPops'                 => LookupItem::where('type', 'key_pops')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'modesOfCommunication'    => LookupItem::where('type', 'mode_of_communication')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'projects'                => LookupItem::where('type', 'project')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'servicesRequested'       => LookupItem::where('type', 'service_requested')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'secondServicesRequested' => LookupItem::where('type', 'service_requested')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'referredTo'              => LookupItem::where('type', 'referred_to')->where('is_active', true)->orderBy('sort_order')->orderBy('name')->pluck('name'),
            'serviceCategories'       => LookupItem::where('type', 'service_requested')->where('is_active', true)->get(['name', 'classification_categories'])->mapWithKeys(fn ($i) => [$i->name => $i->classification_categories ?? []]),
        ]);
    }

    public function searchNumbers(Request $request): JsonResponse
    {
        $q = trim($request->get('q', ''));
        if (strlen($q) < 2) return response()->json([]);

        $numbers = Call::where(function ($query) use ($q) {
                $query->where('caller', 'like', "%{$q}%")
                      ->orWhere('callee', 'like', "%{$q}%");
            })
            ->orderByDesc('started_at')
            ->limit(100)
            ->get(['caller', 'callee'])
            ->flatMap(fn($c) => [$c->caller, $c->callee])
            ->filter(fn($n) => str_contains($n, $q) && $n !== '')
            ->unique()
            ->values()
            ->take(15);

        return response()->json($numbers);
    }

    private array $crmRules = [
        'psychosocial_type'        => 'nullable|in:Awareness Raising,Helpline Marketing,Counselling',
        'contact_number'           => 'nullable|string|max:50',
        'sisters_number'           => 'nullable|string|max:50',
        'follow_up_date'           => 'nullable|date',
        'mode_of_communication'    => 'nullable|string|max:100',
        'call_validity'            => 'nullable|in:valid,invalid',
        'purpose_of_call'          => 'nullable|string|max:255',
        'immediate_action_required' => 'nullable|boolean',
        'action_status'            => 'nullable|in:yes,ongoing,pending,no',
        'caller_age'               => 'nullable|integer|min:1|max:120',
        'caller_gender'            => 'nullable|in:male,female,other,prefer_not_to_say',
        'caller_marital_status'    => 'nullable|string|max:100',
        'key_pops'                 => 'nullable|string|max:255',
        'province'                 => 'nullable|string|max:100',
        'district'                 => 'nullable|string|max:100',
        'location'                 => 'nullable|string|max:255',
        'is_repeat_caller'         => 'nullable|boolean',
        'project'                  => 'nullable|string|max:255',
        'services_requested_before' => 'nullable|string',
        'services_requested'        => 'nullable|string',
        'second_service_requested'  => 'nullable|string|max:255',
        'number_of_services'       => 'nullable|integer|min:0|max:255',
        'referred_to'              => 'nullable|string|max:255',
        'uptake_confirmed'         => 'nullable|boolean',
        'referral_uptake_date'    => 'nullable|date',
    ];

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject'        => 'required|string|max:255',
            'description'    => 'nullable|string',
            'client_id'      => 'nullable|exists:clients,id',
            'call_id'        => 'nullable|exists:calls,id',
            'priority'       => 'in:low,medium,high,urgent',
            'status'         => 'nullable|in:open,in_progress,closed,resolved,ongoing',
            'classification' => 'nullable|array',
            ...$this->crmRules,
            'contact_number' => 'required|string|max:50',
        ]);

        // Coerce boolean fields so null from an unselected dropdown becomes false
        $data['immediate_action_required'] = !empty($data['immediate_action_required']);
        $data['is_repeat_caller']          = !empty($data['is_repeat_caller']);
        $data['uptake_confirmed']          = !empty($data['uptake_confirmed']);

        $ticket = Ticket::create([
            ...$data,
            'agent_id' => $request->user()->id,
            'priority' => $data['priority'] ?? 'medium',
            'status'   => $data['status'] ?? 'open',
        ]);

        // Auto-create an urgent case so all agents can follow up
        if (!empty($data['immediate_action_required'])) {
            UrgentCase::create([
                'agent_id'        => $request->user()->id,
                'subject'         => $ticket->subject,
                'contact_number'  => $ticket->contact_number ?? null,
                'description'     => $ticket->description ?? null,
                'status'          => 'open',
                'source_ticket_id' => $ticket->id,
            ]);
        }

        return redirect()->route('tickets.index')->with('success', 'Ticket created.');
    }

    public function update(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'subject'        => 'sometimes|string|max:255',
            'description'    => 'nullable|string',
            'status'         => 'nullable|in:open,in_progress,resolved,closed,ongoing',
            'priority'       => 'in:low,medium,high,urgent',
            'agent_id'       => 'nullable|exists:users,id',
            'classification' => 'nullable|array',
            ...$this->crmRules,
        ]);

        if (isset($data['status']) && $data['status'] === 'resolved' && !$ticket->resolved_at) {
            $data['resolved_at'] = now();
        }

        $ticket->update($data);

        return back()->with('success', 'Ticket updated.');
    }

    public function draftNotes(Request $request): JsonResponse
    {
        $data = $request->only([
            'subject', 'purpose_of_call', 'services_requested', 'second_service_requested',
            'caller_age', 'caller_gender', 'caller_marital_status', 'key_pops',
            'province', 'district', 'location', 'mode_of_communication',
            'priority', 'action_status', 'referred_to', 'is_repeat_caller',
            'call_validity', 'project', 'psychosocial_type', 'classification',
        ]);

        $lines = [];
        if (!empty($data['subject']))                $lines[] = "Case name: {$data['subject']}";
        if (!empty($data['purpose_of_call']))         $lines[] = "Purpose of call: {$data['purpose_of_call']}";
        if (!empty($data['services_requested']))      $lines[] = "Service requested: {$data['services_requested']}";
        if (!empty($data['second_service_requested'])) $lines[] = "Second service: {$data['second_service_requested']}";
        if (!empty($data['caller_age']))              $lines[] = "Caller age: {$data['caller_age']}";
        if (!empty($data['caller_gender']))           $lines[] = "Gender: {$data['caller_gender']}";
        if (!empty($data['caller_marital_status']))   $lines[] = "Marital status: {$data['caller_marital_status']}";
        if (!empty($data['key_pops']))                $lines[] = "Key population: {$data['key_pops']}";
        if (!empty($data['province']))                $lines[] = "Province: {$data['province']}";
        if (!empty($data['district']))                $lines[] = "District: {$data['district']}";
        if (!empty($data['location']))                $lines[] = "Location: {$data['location']}";
        if (!empty($data['mode_of_communication']))   $lines[] = "Mode of communication: {$data['mode_of_communication']}";
        if (!empty($data['priority']))                $lines[] = "Priority: {$data['priority']}";
        if (!empty($data['action_status']))           $lines[] = "Action status: {$data['action_status']}";
        if (!empty($data['referred_to']))             $lines[] = "Referred to: {$data['referred_to']}";
        if (!empty($data['is_repeat_caller']))        $lines[] = "Repeat caller: yes";
        if (!empty($data['psychosocial_type']))       $lines[] = "Psychosocial type: {$data['psychosocial_type']}";
        if (!empty($data['classification']) && is_array($data['classification'])) {
            $flat = collect($data['classification'])->flatten()->filter()->implode(', ');
            if ($flat) $lines[] = "Classification: {$flat}";
        }

        $context = implode("\n", $lines);

        $message = Anthropic::messages()->create([
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 400,
            'system'     => 'You are assisting a counsellor at a helpline. Write a concise, professional counsellor session note in 3-5 sentences based on the intake data provided. Use neutral, non-judgmental clinical language. Do not invent details not present in the data.',
            'messages'   => [
                ['role' => 'user', 'content' => "Intake data:\n{$context}\n\nDraft a counsellor note."],
            ],
        ]);

        return response()->json(['note' => $message->content[0]->text]);
    }

    public function destroy(Request $request, Ticket $ticket): RedirectResponse
    {
        if ($request->user()->role !== 'admin') {
            abort(403, 'Only administrators can delete tickets.');
        }

        $ticket->delete();

        return redirect()->route('tickets.index')->with('success', 'Ticket deleted.');
    }
}
