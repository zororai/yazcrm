<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BeneficiaryProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

// Youth Enterprise Graduate Model: Beneficiary Profiling Tool, captured
// offline in the beneficiary app and bulk-synced here.
#[OA\Tag(name: 'Beneficiary Profiles', description: 'Offline-capture beneficiary profiling forms')]
class BeneficiaryProfileController extends Controller
{
    private const VULNERABILITY = [
        'adolescent_girl', 'disability', 'rural_underserved',
        'orphan_single_parent_child_headed', 'early_marriage_gbv_risk', 'minority_group',
    ];

    private const SKILLS = [
        'tailoring', 'ict_digital', 'agriculture', 'handicrafts', 'hair_dressing',
        'cosmetology', 'mechanics', 'building', 'carpentry', 'food_processing',
        'nurse_aide', 'teaching', 'hotel_catering', 'baking', 'welding', 'graphic_design',
    ];

    #[OA\Get(
        path: '/api/beneficiary-profiles/search',
        tags: ['Beneficiary Profiles'],
        summary: 'Search beneficiary profiles by phone number',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'phone', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Matching profiles, most recent first'),
        ],
    )]
    public function search(Request $request): JsonResponse
    {
        $phone = trim((string) $request->query('phone', ''));
        if ($phone === '') {
            return response()->json(['results' => []]);
        }

        $results = BeneficiaryProfile::where('phone_number', $phone)
            ->latest('captured_at')
            ->limit(20)
            ->get(['id', 'full_name', 'gender', 'age', 'location', 'phone_number', 'captured_at']);

        return response()->json(['results' => $results]);
    }

    // Idempotent via client_uuid — a retry of the same batch skips records
    // that already made it through.
    #[OA\Post(
        path: '/api/beneficiary-profiles/sync',
        tags: ['Beneficiary Profiles'],
        summary: 'Bulk-upload offline-captured beneficiary profiles',
        description: 'Idempotent via client_uuid — safe to retry the same batch.',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Sync complete, with created/skipped counts'),
            new OA\Response(response: 422, description: 'Validation error'),
        ],
    )]
    public function sync(Request $request): JsonResponse
    {
        $lmh  = Rule::in(['low', 'medium', 'high']);
        $data = $request->validate([
            'profiles'                               => 'required|array|min:1',
            'profiles.*.client_uuid'                 => 'required|uuid',
            'profiles.*.consent_given'               => 'nullable|boolean',
            'profiles.*.full_name'                   => 'required|string|max:255',
            'profiles.*.age'                         => 'nullable|integer|min:0|max:120',
            'profiles.*.gender'                      => ['nullable', Rule::in(['F', 'M', 'Other'])],
            'profiles.*.location'                    => 'nullable|string|max:255',
            'profiles.*.phone_number'                => 'nullable|string|max:50',
            'profiles.*.alternative_contact'         => 'nullable|string|max:255',
            'profiles.*.caregiver_name'              => 'nullable|string|max:255',
            'profiles.*.caregiver_contact'           => 'nullable|string|max:255',
            'profiles.*.education_level'             => ['nullable', Rule::in(['none', 'primary', 'secondary', 'tertiary', 'other'])],
            'profiles.*.current_status'              => ['nullable', Rule::in(['in_school', 'out_of_school', 'unemployed', 'self_employed', 'employed'])],
            'profiles.*.household_head'              => 'nullable|boolean',
            'profiles.*.dependents'                  => 'nullable|integer|min:0|max:100',
            'profiles.*.vulnerability'               => 'nullable|array',
            'profiles.*.vulnerability.*'             => Rule::in(self::VULNERABILITY),
            'profiles.*.current_income_source'       => 'nullable|string|max:255',
            'profiles.*.average_monthly_income'      => 'nullable|numeric|min:0',
            'profiles.*.skill_interests'             => 'nullable|array',
            'profiles.*.skill_interests.*'           => Rule::in(self::SKILLS),
            'profiles.*.entrepreneurship_experience' => ['nullable', Rule::in(['none', 'small_business', 'informal_trade', 'other'])],
            'profiles.*.business_idea'               => 'nullable|string|max:255',
            'profiles.*.financial_services_access'   => 'nullable|array',
            'profiles.*.financial_services_access.*' => Rule::in(['bank', 'mobile_money', 'savings_group', 'none']),
            'profiles.*.financial_literacy'          => ['nullable', $lmh],
            'profiles.*.needs_startup_capital'       => 'nullable|boolean',
            'profiles.*.interested_in_savings'       => 'nullable|boolean',
            'profiles.*.decision_making_confidence'  => ['nullable', $lmh],
            'profiles.*.leadership_experience'       => ['nullable', Rule::in(['none', 'community', 'school', 'other'])],
            'profiles.*.has_mentorship_access'       => 'nullable|boolean',
            'profiles.*.interested_in_stem'          => 'nullable|boolean',
            'profiles.*.needs_safe_spaces'           => 'nullable|boolean',
            'profiles.*.has_market_access'           => 'nullable|boolean',
            'profiles.*.member_of_youth_group'       => 'nullable|boolean',
            'profiles.*.interested_in_incubation'    => 'nullable|boolean',
            'profiles.*.scaling_potential'           => ['nullable', $lmh],
            'profiles.*.consent_to_participate'      => 'nullable|boolean',
            'profiles.*.preferred_communication'     => ['nullable', Rule::in(['sms', 'call', 'whatsapp'])],
            'profiles.*.signed_date'                 => 'nullable|date',
            'profiles.*.captured_at'                 => 'nullable|date',
            'profiles.*.signature_base64'            => 'nullable|string',
        ]);

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $request, &$created, &$skipped) {
            foreach ($data['profiles'] as $profileData) {
                if (BeneficiaryProfile::where('client_uuid', $profileData['client_uuid'])->exists()) {
                    $skipped++;
                    continue;
                }

                $signaturePath = null;
                if (!empty($profileData['signature_base64'])) {
                    $signaturePath = $this->storeSignature($profileData['client_uuid'], $profileData['signature_base64']);
                }
                unset($profileData['signature_base64']);

                BeneficiaryProfile::create([
                    ...$profileData,
                    'signature_path'     => $signaturePath,
                    'captured_at'        => $profileData['captured_at'] ?? now(),
                    'created_by_user_id' => $request->user()->id,
                ]);
                $created++;
            }
        });

        return response()->json([
            'message' => 'Sync complete.',
            'created' => $created,
            'skipped' => $skipped, // already-synced records from a previous attempt
        ]);
    }

    private function storeSignature(string $clientUuid, string $base64): string
    {
        $bytes = base64_decode($base64, true) ?: '';
        $path  = "signatures/profiles/{$clientUuid}.png";
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    #[OA\Get(
        path: '/api/beneficiary-profiles',
        tags: ['Beneficiary Profiles'],
        summary: 'List beneficiary profiles (paginated)',
        security: [['sanctum' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Paginated list of profiles'),
        ],
    )]
    public function index(Request $request): JsonResponse
    {
        return response()->json(BeneficiaryProfile::latest('captured_at')->paginate(20));
    }
}
