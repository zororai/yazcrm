<?php

namespace App\Services;

use App\Models\LookupItem;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

class CaseIntelligenceService
{
    private const MODEL = 'gpt-4o-mini';
    private const VALID_PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    /**
     * Analyze a call transcript and return a normalized, validated result.
     * Never invents a category outside the CRM's controlled list (spec §18) —
     * an out-of-list or missing category comes back as null, not guessed.
     *
     * @throws RuntimeException on API failure or unparseable output.
     */
    public function analyze(string $transcript): array
    {
        $categories = LookupItem::forType('service_requested')->pluck('name')->all();

        $response = OpenAI::chat()->create([
            'model'           => self::MODEL,
            'max_tokens'      => 500,
            'response_format' => ['type' => 'json_object'],
            'messages'        => [
                ['role' => 'system', 'content' => $this->systemPrompt($categories)],
                ['role' => 'user', 'content' => "Call transcript:\n\n{$transcript}\n\nAnalyze this call."],
            ],
        ]);

        $raw = $response->choices[0]->message->content ?? '';

        return $this->parseAndValidate($raw, $categories) + ['model' => self::MODEL];
    }

    private function systemPrompt(array $categories): string
    {
        $categoryList = implode(', ', $categories);

        return <<<PROMPT
            You are assisting a helpline case worker by summarizing a call transcript.
            This is an assistant recommendation only — you do not make case decisions,
            close cases, diagnose conditions, or determine legal outcomes.

            Respond with ONLY a JSON object, no other text, in exactly this shape:
            {
              "summary": "2-4 sentence neutral, professional summary of the call",
              "category": "one value from this exact list, or null if none fit: [{$categoryList}]",
              "priority": "one of: low, medium, high, urgent",
              "follow_up_required": true or false,
              "referral_required": true or false
            }

            The "category" value MUST be copied exactly from the provided list, or null.
            Do not invent a category that is not in the list. Use neutral, non-judgmental
            clinical language. Do not invent details not present in the transcript.
            PROMPT;
    }

    /**
     * @throws RuntimeException if the response isn't valid JSON.
     */
    public function parseAndValidate(string $raw, array $allowedCategories): array
    {
        $json = $this->extractJson($raw);
        $data = json_decode($json, true);

        if (! is_array($data)) {
            throw new RuntimeException('AI response was not valid JSON.');
        }

        $category = $data['category'] ?? null;
        if (! is_string($category) || ! in_array($category, $allowedCategories, true)) {
            $category = null; // Never trust a category outside the controlled list.
        }

        $priority = $data['priority'] ?? null;
        if (! in_array($priority, self::VALID_PRIORITIES, true)) {
            $priority = 'medium';
        }

        return [
            'summary'             => is_string($data['summary'] ?? null) ? trim($data['summary']) : '',
            'category'            => $category,
            'priority'            => $priority,
            'follow_up_required'  => (bool) ($data['follow_up_required'] ?? false),
            'referral_required'   => (bool) ($data['referral_required'] ?? false),
        ];
    }

    private function extractJson(string $raw): string
    {
        // json_object response_format should make this unnecessary, but keep
        // it as a defensive fallback in case the model wraps output in prose.
        if (preg_match('/\{.*\}/s', $raw, $m)) {
            return $m[0];
        }

        return $raw;
    }
}
