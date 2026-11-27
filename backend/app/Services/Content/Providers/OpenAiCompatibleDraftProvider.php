<?php

namespace App\Services\Content\Providers;

use App\Contracts\AiDraftProvider;
use App\Models\Article;
use App\Models\ContentEvent;
use App\Services\Content\AiBudgetManager;
use App\Support\SafeError;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class OpenAiCompatibleDraftProvider implements AiDraftProvider
{
    public function __construct(
        private readonly TemplateDraftProvider $fallback,
        private readonly AiBudgetManager $budget,
    ) {}

    public function name(): string
    {
        return 'openai_compatible';
    }

    public function model(): string
    {
        return (string) config('content.ai.model', 'gpt-4o-mini');
    }

    public function generate(Article $article, ContentEvent $event): array
    {
        $apiKey = (string) config('content.ai.api_key');
        if ($apiKey === '') {
            Log::warning('content.ai: API key missing — using template fallback');
            $this->budget->record('fallback', $article, $event, meta: ['reason' => 'api_key_missing']);

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

        try {
            $response = Http::baseUrl((string) config('content.ai.base_url'))
                ->withToken($apiKey)
                ->timeout((int) config('content.ai.timeout', 60))
                ->acceptJson()
                ->post('/chat/completions', [
                    'model' => $this->model(),
                    'temperature' => 0.4,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);

            if (! $response->successful()) {
                SafeError::log(
                    new RuntimeException('AI HTTP '.$response->status()),
                    'content.ai HTTP error',
                    ['status' => $response->status(), 'body' => Str::limit($response->body(), 500)],
                );
                throw new RuntimeException('AI provider request failed.');
            }

            $json = $response->json();
            $content = data_get($json, 'choices.0.message.content');
            if (! is_string($content) || trim($content) === '') {
                throw new RuntimeException('Empty AI content');
            }

            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            $promptTokens = (int) data_get($json, 'usage.prompt_tokens', 0);
            $completionTokens = (int) data_get($json, 'usage.completion_tokens', 0);
            $this->budget->record(
                status: 'success',
                article: $article,
                event: $event,
                provider: $this->name(),
                model: $this->model(),
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
                    'model' => $this->model(),
                    'prompt_tokens' => $promptTokens,
                    'completion_tokens' => $completionTokens,
                ],
            ];
        } catch (\Throwable $e) {
            SafeError::log($e, 'content.ai: generation failed, using template', [
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
