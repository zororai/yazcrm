<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\DataCollection\SubmitBatchRequest;
use App\Models\DataCollectionSubmission;
use App\Services\DataCollection\SubmissionIngestService;
use App\Services\DataCollection\SubmissionMediaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;
use Throwable;

class DataCollectionSubmissionController extends Controller
{
    #[OA\Post(
        path: '/api/v1/submissions/batch',
        tags: ['Data Collection v1'],
        summary: 'Upload up to 50 submissions collected offline',
        description: 'Idempotent per client_uuid: a resend returns "duplicate" with the original server_id. One bad item never fails the batch. 422 only for a malformed envelope.',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: [
            'submissions' => [[
                'client_uuid'  => '3b1f4c1e-8a7d-4f53-9a4e-0f6f2b1c9d10',
                'form_id'      => 12,
                'form_version' => 3,
                'collected_at' => '2026-10-30T09:15:00+02:00',
                'device_id'    => 'a1b2c3',
                'app_version'  => '1.4.0',
                'lat'          => -17.82,
                'lng'          => 31.05,
                'data'         => [
                    'respondent_name' => 'Rudo Moyo',
                    'respondent_age'  => 19,
                    'ward'            => 'ward_2',
                    'needs'           => ['food', 'skills'],
                    'interview_date'  => '2026-10-30',
                    'household_location' => ['lat' => -17.82, 'lng' => 31.05, 'accuracy' => 6.5],
                    'house_photo'     => 'abc.jpg',
                    'members'         => [['member_name' => 'Tariro', 'member_age' => 12, 'member_gender' => 'female', 'in_school' => 'yes']],
                ],
                'media' => [['field_name' => 'house_photo', 'filename' => 'abc.jpg']],
            ]],
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Per-item results', content: new OA\JsonContent(example: [
                'results' => [
                    ['client_uuid' => '3b1f4c1e-8a7d-4f53-9a4e-0f6f2b1c9d10', 'status' => 'accepted', 'server_id' => 481, 'reason' => null, 'errors' => []],
                    ['client_uuid' => '7c9e6679-7425-40de-944b-e07fc1f90ae7', 'status' => 'rejected', 'server_id' => null,
                        'reason' => 'Some answers do not match the form.', 'errors' => [['field' => 'respondent_age', 'message' => 'Age must be between 10 and 100.']]],
                ],
            ])),
            new OA\Response(response: 401, description: '{"code":"token_expired"|"unauthenticated"}'),
            new OA\Response(response: 422, description: 'Malformed envelope (not an array, or more than 50 items)'),
            new OA\Response(response: 429, description: 'Too many requests; see Retry-After'),
        ],
    )]
    public function batch(SubmitBatchRequest $request, SubmissionIngestService $ingest): JsonResponse
    {
        $user = $request->user();
        $results = [];

        foreach ($request->input('submissions') as $item) {
            try {
                $results[] = $ingest->ingest($item, $user);
            } catch (QueryException $e) {
                throw $e; // the database is down or failing: a real 5xx, the app retries
            } catch (Throwable $e) {
                report($e);
                $results[] = [
                    'client_uuid' => is_array($item) ? ($item['client_uuid'] ?? null) : null,
                    'status'      => 'rejected',
                    'server_id'   => null,
                    'reason'      => 'The server could not process this submission.',
                    'errors'      => [],
                ];
            }
        }

        return response()->json(['results' => $results]);
    }

    #[OA\Post(
        path: '/api/v1/submissions/{client_uuid}/media',
        tags: ['Data Collection v1'],
        summary: 'Upload a photo for an accepted submission',
        description: 'multipart/form-data with field_name (or field), file, and optionally filename (defaults to the uploaded file name). Idempotent per (submission, field_name, filename): a repeat returns 200 with status "duplicate".',
        security: [['sanctum' => []]],
        parameters: [new OA\Parameter(name: 'client_uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(
            required: ['field_name', 'file'],
            properties: [
                new OA\Property(property: 'field_name', type: 'string', example: 'house_photo'),
                new OA\Property(property: 'filename', type: 'string', example: 'abc.jpg'),
                new OA\Property(property: 'file', type: 'string', format: 'binary'),
            ],
        ))),
        responses: [
            new OA\Response(response: 201, description: 'Stored', content: new OA\JsonContent(example: [
                'status' => 'stored', 'id' => 77, 'field_name' => 'house_photo', 'filename' => 'abc.jpg', 'size' => 182233, 'checksum' => 'e3b0…',
            ])),
            new OA\Response(response: 200, description: 'Already stored (duplicate)'),
            new OA\Response(response: 404, description: 'No accepted submission with this client_uuid (yet)'),
            new OA\Response(response: 422, description: 'Not a photo field, wrong file type, or too large'),
        ],
    )]
    public function media(Request $request, string $clientUuid, SubmissionMediaService $media): JsonResponse
    {
        $submission = DataCollectionSubmission::with('formVersion')
            ->where('client_uuid', $clientUuid)
            ->where('submitted_by', $request->user()->id)
            ->first();
        if (! $submission) {
            return response()->json([
                'code'    => 'submission_not_found',
                'message' => 'No accepted submission with this client_uuid. Send the submission first, then its media.',
            ], 404);
        }

        $input = $request->only(['field_name', 'filename']) + ['field_name' => $request->input('field')];
        $validator = Validator::make($input + ['file' => $request->file('file')], [
            'field_name' => 'required|string|max:255',
            'filename'   => 'nullable|string|max:255',
            'file'       => 'required|file|max:'.config('data_collection.media_max_kb'),
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        [$status, $record, $error] = $media->store(
            $submission,
            $input['field_name'],
            $input['filename'] ?? $request->file('file')->getClientOriginalName(),
            $request->file('file'),
        );
        if ($error) {
            return response()->json(['message' => $error, 'errors' => ['file' => [$error]]], 422);
        }

        return response()->json([
            'status'     => $status,
            'id'         => $record->id,
            'field_name' => $record->field_name,
            'filename'   => $record->original_name,
            'size'       => $record->size,
            'checksum'   => $record->checksum,
        ], $status === 'stored' ? 201 : 200);
    }
}
