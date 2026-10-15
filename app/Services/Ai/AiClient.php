<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Provider-agnostic AI client (Gemma via Ollama, any OpenAI-compatible API, or none).
 *
 * Rules for callers:
 *  - Never send personal data (names, emails, phones, tokens tied to people). Send only
 *    the minimum task content.
 *  - Always validate output with json() + a schema check before using it.
 *  - Always have a local fallback: every method returns null when AI is off or fails.
 */
class AiClient
{
    public function enabled(): bool
    {
        return in_array(config('ai.provider'), ['ollama', 'openai_compatible'], true);
    }

    public function providerLabel(): string
    {
        return $this->enabled() ? config('ai.provider').' · '.config('ai.model') : 'none (local fallback)';
    }

    /** Plain-text completion. Returns null when disabled or on any failure. */
    public function complete(string $system, string $prompt, int $maxTokens = 400): ?string
    {
        if (! $this->enabled()) {
            return null;
        }
        try {
            $base = rtrim((string) config('ai.base_url'), '/');
            $http = Http::timeout(config('ai.timeout'))->acceptJson();
            if ($key = config('ai.api_key')) {
                $http = $http->withToken($key);
            }

            if (config('ai.provider') === 'ollama') {
                $res = $http->post($base.'/api/chat', [
                    'model' => config('ai.model'),
                    'stream' => false,
                    'options' => ['temperature' => config('ai.temperature'), 'num_predict' => $maxTokens],
                    'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]],
                ]);

                return $res->successful() ? trim((string) $res->json('message.content')) : null;
            }

            $res = $http->post($base.'/chat/completions', [
                'model' => config('ai.model'),
                'temperature' => config('ai.temperature'),
                'max_tokens' => $maxTokens,
                'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]],
            ]);

            return $res->successful() ? trim((string) $res->json('choices.0.message.content')) : null;
        } catch (Throwable $e) {
            Log::warning('AI provider unavailable: '.$e->getMessage());

            return null;
        }
    }

    /**
     * JSON completion validated by $validator (returns true if the decoded value is acceptable).
     * Returns the decoded array, or null on disable/failure/invalid output.
     */
    public function json(string $system, string $prompt, callable $validator, int $maxTokens = 600): ?array
    {
        $raw = $this->complete($system."\nRespond with valid JSON only, no prose, no code fences.", $prompt, $maxTokens);
        if ($raw === null) {
            return null;
        }
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $raw);
        $data = json_decode((string) $raw, true);

        return is_array($data) && $validator($data) ? $data : null;
    }
}
