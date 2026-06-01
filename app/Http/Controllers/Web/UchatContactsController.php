<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class UchatContactsController extends Controller
{
    public function index(Request $request): Response
    {
        $page   = max(1, (int) $request->get('page', 1));
        $search = trim($request->get('search', ''));

        $params = ['limit' => 25, 'page' => $page];

        // uChat supports searching by name or phone separately
        if ($search) {
            // Try phone first if it looks like a number, otherwise name
            if (preg_match('/^[\d+\s()-]+$/', $search)) {
                $params['phone'] = $search;
            } else {
                $params['name'] = $search;
            }
        }

        $token    = config('uchat.token');
        $baseUrl  = rtrim(config('uchat.base_url'), '/');

        $subscribers = [];
        $meta        = ['total' => 0, 'last_page' => 1, 'current_page' => 1, 'per_page' => 25];
        $error       = null;

        if ($token) {
            try {
                $response = Http::withToken($token)
                    ->acceptJson()
                    ->get("{$baseUrl}/subscribers", $params);

                if ($response->successful()) {
                    $data        = $response->json();
                    $subscribers = $data['data'] ?? [];
                    $meta        = [
                        'total'        => $data['meta']['total']        ?? 0,
                        'last_page'    => $data['meta']['last_page']    ?? 1,
                        'current_page' => $data['meta']['current_page'] ?? 1,
                        'per_page'     => $data['meta']['per_page']     ?? 25,
                    ];
                } else {
                    $error = 'uChat API returned status ' . $response->status();
                }
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        } else {
            $error = 'UCHAT_API_TOKEN not configured.';
        }

        return Inertia::render('UchatContacts/Index', [
            'subscribers' => $subscribers,
            'meta'        => $meta,
            'filters'     => ['search' => $search],
            'error'       => $error,
        ]);
    }
}
