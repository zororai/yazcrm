<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DataCollectionSubmission;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use Throwable;

class DataCollectionSyncController extends Controller
{
    #[OA\Get(
        path: '/api/v1/sync/status',
        tags: ['Data Collection v1'],
        summary: 'Server time, current form versions, and (when asked) which submissions the server holds',
        description: 'accepted_client_uuids is included only when since and/or client_uuids is given, and only lists the caller\'s own submissions.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'since', in: 'query', required: false, description: 'ISO 8601: submissions received at or after this time', schema: new OA\Schema(type: 'string', format: 'date-time')),
            new OA\Parameter(name: 'client_uuids', in: 'query', required: false, description: 'Comma-separated (or client_uuids[]) — which of these were accepted. Max 500.', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Status', content: new OA\JsonContent(example: [
                'server_time' => '2026-10-30T09:20:00+02:00',
                'forms' => [['id' => 12, 'version' => 3, 'checksum' => '9f2c…']],
                'accepted_client_uuids' => ['3b1f4c1e-8a7d-4f53-9a4e-0f6f2b1c9d10'],
            ])),
        ],
    )]
    public function status(Request $request): JsonResponse
    {
        $body = [
            'server_time' => now()->toIso8601String(),
            'forms'       => DataCollectionFormController::collectable($request)->map(fn ($form) => [
                'id'       => $form->id,
                'version'  => $form->currentVersion?->version_number,
                'checksum' => $form->currentVersion?->checksum,
            ])->values(),
        ];

        $uuids = $request->query('client_uuids');
        if (is_string($uuids)) {
            $uuids = explode(',', $uuids);
        }
        $uuids = is_array($uuids) ? array_slice(array_filter(array_map('trim', $uuids), 'is_string'), 0, 500) : null;

        $since = null;
        if ($request->filled('since')) {
            try {
                $since = CarbonImmutable::parse((string) $request->query('since'))->setTimezone(config('app.timezone'));
            } catch (Throwable) {
                return response()->json(['message' => 'since must be an ISO 8601 date-time.', 'errors' => ['since' => ['Invalid date.']]], 422);
            }
        }

        if ($uuids || $since) {
            // Soft-deleted submissions were still received: the device must
            // not send them again.
            $body['accepted_client_uuids'] = DataCollectionSubmission::withTrashed()
                ->where('submitted_by', $request->user()->id)
                ->when($uuids, fn ($q) => $q->whereIn('client_uuid', $uuids))
                ->when($since, fn ($q) => $q->where('received_at', '>=', $since))
                ->limit(5000)
                ->pluck('client_uuid')
                ->values();
        }

        return response()->json($body);
    }
}
