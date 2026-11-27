<?php

namespace App\Services\Content\Providers;

use App\Contracts\AiDraftProvider;
use App\Models\Article;
use App\Models\ContentEvent;
use App\Services\Content\AiBudgetManager;
use App\Support\SafeError;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Native Google Gemini provider (Generative Language API).
 * Default model: gemini-3.5-flash-lite. Override with CONTENT_AI_MODEL.
 */
class GeminiDraftProvider implements AiDraftProvider
{
    public function __construct(
        private readonly TemplateDraftProvider $fallback,
        private readonly AiBudgetManager $budget,
    ) {}

    public function name(): string
    {
        return 'gemini';
    }

    public function model(): string
    {
        return (string) config('content.ai.model', 'gemini-3.5-flash-lite');
    }

    public function generate(Article $article, ContentEvent $event): array
    {
        $apiKey = (string) config('content.ai.api_key');
        if ($apiKey === '') {
            Log::warning('content.ai.gemini: API key missing — using template fallback');
            $this->budget->record('fallback', $article, $event, provider: $this->name(), meta: ['reason' => 'api_key_missing']);

            return $this->withFallbackFlag($this->fallback->generate($article, $event), 'api_key_missing');
        }

        $promptVersion = config('content.ai.prompt_version', 'v1');
        $tone = config('content.event_types.'.$event->type.'.tone', 'clear');
        $system = <<<SYS
You are an editorial assistant for Fuel Prices PK (Pakistan fuel prices PWA).
Write factual, concise HTML articles for Pakistani drivers. No hype, no politics.
Return ONLY valid JSON with keys: title, excerpt, body, tags (string[]), faq ({question,answer}[]).
Body must use <p>, <h2>, <ul><li>, optional <table>. No markdown. Prompt version {$promptVersion}. Tone: {$tone}.
SYS;

        $user = json_encode([
            'event_type' => $event->type,
            'severity' => $event->severity,
            'working_title' => $article->title,
            'payload' => $event->payload,
            'constraints' => [
                'locale' => 'en-PK',
                'currency' => 'PKR',
                'mention_calculators' => true,
                'mention_city_pages' => true,
            ],
        ], JSON_THROW_ON_ERROR);

        $base = rtrim((string) config('content.ai.gemini_base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $model = $this->model();
        $url = $base.'/models/'.rawurlencode($model).':generateContent';

        try {
            $response = Http::timeout((int) config('content.ai.timeout', 60))
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->acceptJson()
                ->post($url, [
                    'systemInstruction' => [
                        'parts' => [['text' => $system]],
                    ],
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [['text' => $user]],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'responseMimeType' => 'application/json',
                        'maxOutputTokens' => 2048,
                    ],
                ]);

            if (! $response->successful()) {
                SafeError::log(
                    new RuntimeException('Gemini HTTP '.$response->status()),
                    'content.ai.gemini HTTP error',
                    ['status' => $response->status(), 'body' => Str::limit($response->body(), 500)],
                );
                throw new RuntimeException('AI provider request failed.');
            }

            $json = $response->json();
            $content = '';
            foreach (data_get($json, 'candidates.0.content.parts', []) ?: [] as $part) {
                if (is_array($part) && filled($part['text'] ?? null)) {
                    $content .= (string) $part['text'];
                }
            }
            if (trim($content) === '') {
                throw new RuntimeException('Empty Gemini content (finish='.(string) data_get($json, 'candidates.0.finishReason', '').')');
            }

            try {
                $decoded = $this->decodeJsonObject($content);
            } catch (\Throwable $parseError) {
                Log::warning('content.ai.gemini: JSON parse failed', [
                    'error' => $parseError->getMessage(),
                    'raw' => Str::limit($content, 500),
                ]);
                throw $parseError;
            }

            $promptTokens = (int) data_get($json, 'usageMetadata.promptTokenCount', 0);
            $completionTokens = (int) data_get($json, 'usageMetadata.candidatesTokenCount', 0);
            $this->budget->record(
                status: 'success',
                article: $article,
                event: $event,
                provider: $this->name(),
                model: $model,
                promptTokens: $promptTokens,
                completionTokens: $completionTokens,
            );

            return [
                'title' => (string) ($decoded['title'] ?? $article->title),
                'excerpt' => Str::limit((string) ($decoded['excerpt'] ?? ''), 480),
                'body' => (string) ($decoded['body'] ?? ''),
                'tags' => array_values(array_filter(array_map('strval', $decoded['tags'] ?? []))),
                'faq' => $this->normalizeFaq($decoded['faq'] ?? []),
                'meta' => [
                    'prompt_version' => $promptVersion,
                    'provider' => $this->name(),
                    'model' => $model,
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                ],
            ];
        } catch (\Throwable $e) {
            SafeError::log($e, 'content.ai.gemini: generation failed, using template', [
                'article_id' => $article->id,
                'provider' => $this->name(),
            ]);
            $this->budget->record(
                status: 'fallback',
                article: $article,
                event: $event,
                provider: $this->name(),
                meta: ['reason' => 'ai_generation_failed'],
            );

            return $this->withFallbackFlag($this->fallback->generate($article, $event), 'ai_generation_failed');
        }
    }

    /**
     * Lightweight connectivity check (no article / budget write).
     *
     * @return array{ok: bool, model: string, message: string, preview?: string}
     */
    public function healthCheck(): array
    {
        $apiKey = (string) config('content.ai.api_key');
        $model = $this->model();
        if ($apiKey === '') {
            return ['ok' => false, 'model' => $model, 'message' => 'CONTENT_AI_API_KEY is empty'];
        }

        $base = rtrim((string) config('content.ai.gemini_base_url', 'https://generativelanguage.googleapis.com/v1beta'), '/');
        $url = $base.'/models/'.rawurlencode($model).':generateContent';

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->acceptJson()
                ->post($url, [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [['text' => 'Return JSON object: {"ok":true}']],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0,
                        'maxOutputTokens' => 256,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'model' => $model,
                    'message' => 'HTTP '.$response->status().': '.Str::limit($response->body(), 240),
                ];
            }

            $json = $response->json();
            $text = '';
            foreach (data_get($json, 'candidates.0.content.parts', []) ?: [] as $part) {
                if (is_array($part) && filled($part['text'] ?? null)) {
                    $text .= (string) $part['text'];
                }
            }

            $finish = (string) data_get($json, 'candidates.0.finishReason', '');
            $parsedOk = false;
            if ($text !== '') {
                try {
                    $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
                    $parsedOk = (bool) data_get($decoded, 'ok', false);
                } catch (\Throwable) {
                    $parsedOk = str_contains(Str::lower($text), 'true');
                }
            }

            return [
                'ok' => $parsedOk || ($text !== '' && $finish === 'STOP'),
                'model' => $model,
                'message' => ($parsedOk || $text !== '')
                    ? 'Gemini accepted the API key and returned a response.'
                    : 'Empty response body (finish='.$finish.')',
                'preview' => Str::limit(trim($text !== '' ? $text : $finish), 80),
            ];
        } catch (\Throwable $e) {
            SafeError::log($e, 'content.ai.gemini health check failed', ['model' => $model]);

            return ['ok' => false, 'model' => $model, 'message' => 'Gemini health check failed. See server logs for details.'];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $content): array
    {
        $content = trim($content);
        if (str_starts_with($content, '```')) {
            $content = (string) preg_replace('/^```(?:json)?\s*/i', '', $content);
            $content = (string) preg_replace('/\s*```$/', '', $content);
            $content = trim($content);
        }

        foreach ($this->jsonCandidates($content) as $candidate) {
            try {
                /** @var array<string, mixed> $decoded */
                $decoded = json_decode($candidate, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    return $decoded;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        throw new RuntimeException('Invalid JSON from Gemini: '.Str::limit($content, 280));
    }

    /**
     * @return list<string>
     */
    private function jsonCandidates(string $content): array
    {
        $candidates = [$content];
        if (preg_match('/\{.*\}/s', $content, $m) === 1) {
            $candidates[] = $m[0];
        }

        // Trailing commas are a common model mistake.
        foreach ([$content, $candidates[1] ?? null] as $raw) {
            if (! is_string($raw) || $raw === '') {
                continue;
            }
            $noTrailing = (string) preg_replace('/,\s*([}\]])/', '$1', $raw);
            if ($noTrailing !== $raw) {
                $candidates[] = $noTrailing;
            }
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @param  array{title:string,excerpt:string,body:string,tags:list<string>,faq:list<array{question:string,answer:string}>,meta?:array<string,mixed>}  $draft
     * @return array{title:string,excerpt:string,body:string,tags:list<string>,faq:list<array{question:string,answer:string}>,meta?:array<string,mixed>}
     */
    private function withFallbackFlag(array $draft, string $reason): array
    {
        $draft['meta'] = array_merge($draft['meta'] ?? [], [
            'used_fallback' => true,
            'fallback_provider' => 'template',
            'fallback_reason' => $reason,
            'requested_provider' => $this->name(),
            'provider' => 'template',
        ]);

        return $draft;
    }

    /**
     * @param  mixed  $faq
     * @return list<array{question:string,answer:string}>
     */
    private function normalizeFaq(mixed $faq): array
    {
        if (! is_array($faq)) {
            return [];
        }

        $out = [];
        foreach ($faq as $item) {
            if (! is_array($item)) {
                continue;
            }
            $q = trim((string) ($item['question'] ?? ''));
            $a = trim((string) ($item['answer'] ?? ''));
            if ($q === '' || $a === '') {
                continue;
            }
            $out[] = ['question' => $q, 'answer' => $a];
        }

        return $out;
    }
}
