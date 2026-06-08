<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlockedNumberRequest;
use App\Services\YeastarService;
use Illuminate\Http\Request;

class BlockedNumberRequestController extends Controller
{
    public function __construct(private YeastarService $yeastar) {}

    // Any authenticated user — list their own requests + admins see all
    public function index(Request $request)
    {
        $user  = $request->user();
        $query = BlockedNumberRequest::with(['requestedBy:id,name', 'reviewedBy:id,name'])
            ->orderByRaw("FIELD(status,'pending','rejected','approved')")
            ->orderByDesc('created_at');

        if ($user->role !== 'admin') {
            $query->where('requested_by', $user->id);
        }

        return response()->json($query->get());
    }

    // Any authenticated user submits a request
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'numbers'    => 'required|string',
            'limit_type' => 'in:inbound,outbound,both',
        ]);

        $req = BlockedNumberRequest::create([
            ...$data,
            'limit_type'   => $data['limit_type'] ?? 'inbound',
            'requested_by' => $request->user()->id,
            'status'       => 'pending',
        ]);

        return response()->json($req->load('requestedBy:id,name'), 201);
    }

    // Admin only — approve: push to Yeastar then mark approved
    public function approve(Request $request, BlockedNumberRequest $blockedNumberRequest)
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        if ($blockedNumberRequest->status !== 'pending') {
            return response()->json(['ok' => false, 'message' => 'Request is not pending.'], 422);
        }

        $result = $this->yeastar->addBlockedNumber(
            $blockedNumberRequest->name,
            $blockedNumberRequest->numbers,
            $blockedNumberRequest->limit_type,
        );

        if (! $result['ok']) {
            return response()->json(['ok' => false, 'message' => $result['message']], 422);
        }

        $blockedNumberRequest->update([
            'status'      => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    // Admin only — reject with optional reason
    public function reject(Request $request, BlockedNumberRequest $blockedNumberRequest)
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        if ($blockedNumberRequest->status !== 'pending') {
            return response()->json(['ok' => false, 'message' => 'Request is not pending.'], 422);
        }

        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $blockedNumberRequest->update([
            'status'           => 'rejected',
            'reviewed_by'      => $request->user()->id,
            'reviewed_at'      => now(),
            'rejection_reason' => $data['reason'] ?? null,
        ]);

        return response()->json(['ok' => true]);
    }

    // Admin only — delete a request
    public function destroy(Request $request, BlockedNumberRequest $blockedNumberRequest)
    {
        if ($request->user()->role !== 'admin') {
            abort(403);
        }

        $blockedNumberRequest->delete();

        return response()->json(['ok' => true]);
    }
}
