<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DataCollection\AppFormResource;
use App\Models\DataCollectionForm;
use App\Models\DataCollectionFormVersion;
use App\Support\DataCollection\FormAccess;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Data Collection v1', description: 'Offline data collection from the beneficiary app (Bearer token from /api/auth/login)')]
class DataCollectionFormController extends Controller
{
    #[OA\Get(
        path: '/api/v1/forms',
        tags: ['Data Collection v1'],
        summary: 'Deployed forms the logged-in user collects for',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Forms', content: new OA\JsonContent(example: [
                'forms' => [['id' => 12, 'title' => 'Household Survey', 'version' => 3, 'checksum' => '9f2c…', 'updated_at' => '2026-10-30T09:00:00+02:00']],
            ])),
            new OA\Response(response: 401, description: '{"code":"token_expired"|"unauthenticated"}'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        return response()->json(['forms' => AppFormResource::collection(self::collectable($request))]);
    }

    #[OA\Get(
        path: '/api/v1/forms/{id}/schema',
        tags: ['Data Collection v1'],
        summary: 'The form schema JSON (questions, groups, repeats, choices)',
        description: 'Defaults to the latest deployed version. An older version is served if it still exists.',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'version', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Schema', content: new OA\JsonContent(example: [
                'form_id' => 12, 'title' => 'Household Survey', 'version' => 3, 'checksum' => '9f2c…',
                'children' => [
                    ['type' => 'text', 'name' => 'respondent_name', 'label' => "Respondent's full name", 'required' => 'yes'],
                    ['type' => 'select_one', 'name' => 'ward', 'label' => 'Ward', 'list_name' => 'ward', 'choices' => [['name' => 'ward_1', 'label' => 'Ward 1']]],
                    ['type' => 'repeat', 'name' => 'members', 'label' => 'Household member', 'children' => [
                        ['type' => 'integer', 'name' => 'member_age', 'label' => 'Age', 'constraint' => '. >= 0 and . <= 120'],
                    ]],
                ],
                'choices' => ['ward' => [['name' => 'ward_1', 'label' => 'Ward 1']]],
            ])),
            new OA\Response(response: 403, description: 'Not assigned to this form'),
            new OA\Response(response: 404, description: 'Form or version not found'),
        ],
    )]
    public function schema(Request $request, int $id): JsonResponse
    {
        $form = DataCollectionForm::find($id);
        if (! $form) {
            return response()->json(['code' => 'not_found', 'message' => 'This form does not exist.'], 404);
        }
        if (! FormAccess::can($request->user(), $form, 'collect')) {
            return response()->json(['code' => 'forbidden', 'message' => 'You are not assigned to this form.'], 403);
        }

        $version = $request->filled('version')
            ? DataCollectionFormVersion::where('form_id', $form->id)
                ->where('version_number', (int) $request->query('version'))
                ->whereIn('status', ['deployed', 'archived'])
                ->first()
            : $form->currentVersion;

        if (! $version || ! $version->acceptsSubmissions()) {
            return response()->json(['code' => 'not_found', 'message' => 'This form version does not exist.'], 404);
        }

        return response()->json([
            'form_id'  => $form->id,
            'title'    => $version->schema['title'] ?? $form->name,
            'version'  => $version->version_number,
            'checksum' => $version->checksum,
        ] + $version->schema);
    }

    // Deployed (not archived) forms the user may collect for.
    public static function collectable(Request $request): Collection
    {
        $ids = FormAccess::formIds($request->user(), 'collect');

        return DataCollectionForm::with('currentVersion:id,form_id,version_number,checksum,deployed_at')
            ->where('status', 'deployed')
            ->whereNotNull('current_version_id')
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('name')
            ->get();
    }
}
