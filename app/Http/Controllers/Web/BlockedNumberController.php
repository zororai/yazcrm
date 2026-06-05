<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\YeastarService;
use Illuminate\Http\Request;

class BlockedNumberController extends Controller
{
    public function __construct(private YeastarService $yeastar) {}

    public function index(Request $request)
    {
        $search = $request->query('search', '');
        $page   = (int) $request->query('page', 1);

        if ($search) {
            $result = $this->yeastar->searchBlockedNumbers($search, $page);
        } else {
            $result = $this->yeastar->listBlockedNumbers($page);
        }

        return response()->json($result);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'numbers'    => 'required|string',
            'limit_type' => 'in:inbound,outbound,both',
        ]);

        $result = $this->yeastar->addBlockedNumber(
            $data['name'],
            $data['numbers'],
            $data['limit_type'] ?? 'inbound',
        );

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'name'       => 'sometimes|string|max:255',
            'numbers'    => 'sometimes|string',
            'limit_type' => 'sometimes|in:inbound,outbound,both',
        ]);

        $fields = array_filter([
            'name'        => $data['name']        ?? null,
            'number_list' => $data['numbers']      ?? null,
            'limit_type'  => $data['limit_type']   ?? null,
        ], fn($v) => $v !== null);

        $result = $this->yeastar->updateBlockedNumber($id, $fields);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function destroy(int $id)
    {
        $result = $this->yeastar->deleteBlockedNumber($id);
        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
