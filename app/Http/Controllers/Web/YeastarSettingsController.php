<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\YeastarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class YeastarSettingsController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/YeastarSettings', [
            'settings' => [
                'base_url'   => Setting::get('yeastar_base_url', config('yeastar.base_url')),
                'app_id'     => Setting::get('yeastar_app_id', config('yeastar.app_id')),
                'app_secret' => Setting::get('yeastar_app_secret', config('yeastar.app_secret')),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'base_url'   => 'required|url',
            'app_id'     => 'required|string|max:255',
            'app_secret' => 'required|string|max:255',
        ]);

        Setting::set('yeastar_base_url',   $data['base_url']);
        Setting::set('yeastar_app_id',     $data['app_id']);
        Setting::set('yeastar_app_secret', $data['app_secret']);

        // Force a fresh token on next API call
        Cache::forget('yeastar_access_token');

        return back()->with('success', 'Yeastar settings saved.');
    }

    public function testConnection(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'base_url'   => 'required|string',
            'app_id'     => 'required|string',
            'app_secret' => 'required|string',
        ]);

        $result = YeastarService::testCredentials($data['base_url'], $data['app_id'], $data['app_secret']);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
