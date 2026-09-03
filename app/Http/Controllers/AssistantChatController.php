<?php

namespace App\Http\Controllers;

use App\Models\AssistantChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;

// A general-purpose chat assistant behind the floating chat button — no CRM
// data access, just conversational Q&A over OpenAI. Every message is saved
// against the logged-in agent so their conversation persists across reloads.
class AssistantChatController extends Controller
{
    private const MODEL = 'gpt-4o-mini';
    private const HISTORY_LIMIT = 40; // messages of context sent to OpenAI

    public function history(Request $request): JsonResponse
    {
        $messages = AssistantChatMessage::where('user_id', $request->user()->id)
            ->orderBy('created_at')
            ->limit(200)
            ->get(['role', 'content', 'created_at']);

        return response()->json(['messages' => $messages]);
    }

    public function reply(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:4000',
        ]);

        $user = $request->user();

        AssistantChatMessage::create([
            'user_id' => $user->id,
            'role'    => 'user',
            'content' => $validated['message'],
        ]);

        $history = AssistantChatMessage::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(self::HISTORY_LIMIT)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();

        $messages = array_merge(
            [['role' => 'system', 'content' => $this->systemPrompt()]],
            $history,
        );

        try {
            $response = OpenAI::chat()->create([
                'model'      => self::MODEL,
                'max_tokens' => 600,
                'messages'   => $messages,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'The assistant is unavailable right now. Try again shortly.'], 503);
        }

        $reply = trim($response->choices[0]->message->content ?? '');

        if ($reply === '') {
            return response()->json(['error' => 'No response from the assistant.'], 502);
        }

        AssistantChatMessage::create([
            'user_id' => $user->id,
            'role'    => 'assistant',
            'content' => $reply,
        ]);

        return response()->json(['reply' => $reply]);
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
            You are a helpful general-purpose assistant embedded in Youth Advocates'
            internal CRM. You do not have access to any CRM data (tickets, calls,
            recordings, users) — you cannot look anything up. If asked about
            specific records or live data, say you can't access that and suggest
            where in the CRM they might find it. Otherwise, answer normally and
            concisely.
            PROMPT;
    }
}
