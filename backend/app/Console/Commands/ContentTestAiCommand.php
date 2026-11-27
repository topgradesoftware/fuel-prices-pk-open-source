<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ContentEvent;
use App\Services\Content\AiBudgetManager;
use App\Services\Content\ContentAutomationOrchestrator;
use App\Services\Content\Providers\GeminiDraftProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ContentTestAiCommand extends Command
{
    protected $signature = 'content:test-ai
        {--keep : Keep the test article instead of archiving it}
        {--skip-pipeline : Only ping the provider (no draft article)}';

    protected $description = 'Verify the configured AI provider (Gemini/OpenAI), then run a weekly-style draft into Pending Review';

    public function handle(
        AiBudgetManager $budget,
        ContentAutomationOrchestrator $orchestrator,
        GeminiDraftProvider $gemini,
    ): int
    {
        $provider = (string) config('content.ai.provider');
        $model = (string) config('content.ai.model');
        $this->info("Provider: {$provider}");
        $this->info("Model: {$model}");
        $this->info('API key: '.(filled(config('content.ai.api_key')) ? 'set ('.strlen((string) config('content.ai.api_key')).' chars)' : 'MISSING'));

        // 1) Connectivity
        if ($provider === 'gemini') {
            $health = $gemini->healthCheck();
        } elseif (in_array($provider, ['openai_compatible', 'openai'], true)) {
            $health = $this->openaiHealth();
        } else {
            $this->error('CONTENT_AI_PROVIDER is "'.$provider.'" — set it to gemini (or openai_compatible) to test AI.');

            return self::FAILURE;
        }

        if (! ($health['ok'] ?? false)) {
            $this->error('API health check failed: '.($health['message'] ?? 'unknown'));

            return self::FAILURE;
        }

        $this->info('✅ API key accepted — '.($health['message'] ?? 'ok'));
        if (! empty($health['preview'])) {
            $this->line('   Preview: '.$health['preview']);
        }

        if ($this->option('skip-pipeline')) {
            return self::SUCCESS;
        }

        $gate = $budget->canUseAi(AiBudgetManager::PRIORITY_HIGH);
        if (! ($gate['allowed'] ?? false)) {
            $this->warn('Budget gate would block AI ('.($gate['reason'] ?? 'unknown').') — pipeline may fall back to template.');
        }

        // 2) Full draft → pending review (unique fingerprint, never auto-publish)
        $this->info('Running draft pipeline (weekly_summary test event)…');
        $stamp = now()->format('YmdHis');
        $event = ContentEvent::query()->create([
            'type' => 'weekly_summary',
            'severity' => 'normal',
            'fingerprint' => hash('sha256', 'ai-test|'.$stamp.'|'.Str::random(8)),
            'status' => ContentEvent::STATUS_DETECTED,
            'payload' => [
                'period_label' => 'AI-TEST-'.$stamp,
                'effective_date' => now()->toDateString(),
                'test' => true,
                'moves' => [
                    ['fuel' => 'petrol', 'price' => 315.80, 'previous' => 310.00, 'delta' => 5.80, 'effective_date' => now()->toDateString()],
                    ['fuel' => 'hsd', 'price' => 285.00, 'previous' => 282.00, 'delta' => 3.00, 'effective_date' => now()->toDateString()],
                ],
                'primary_fuel' => 'petrol',
                'primary_price' => 315.80,
                'previous_price' => 310.00,
                'primary_delta' => 5.80,
            ],
            'detected_at' => now(),
        ]);

        $articles = $orchestrator->planAndDispatch([$event]);
        if ($articles === []) {
            $this->error('Pipeline produced no article.');

            return self::FAILURE;
        }

        /** @var Article $article */
        $article = $articles[0]->fresh();
        $this->info("✅ Draft generated #{$article->id} ({$article->ai_provider} / {$article->ai_model})");
        $this->line('   Title: '.$article->title);
        $this->line('   Status: '.$article->status);

        $ok = true;
        if ($article->status !== Article::STATUS_PENDING_REVIEW) {
            $this->error('❌ Expected pending_review, got '.$article->status);
            $ok = false;
        } else {
            $this->info('✅ Draft is in Pending Review');
        }

        if ($article->status === Article::STATUS_PUBLISHED || $article->published_at !== null) {
            $this->error('❌ Article was published — auto-publish should be off for weekly summaries');
            $ok = false;
        } else {
            $this->info('✅ Nothing auto-published');
        }

        if ($article->ai_provider === 'template' && ($gate['allowed'] ?? false)) {
            $this->warn('Draft used template fallback even though budget allowed AI — check logs.');
        }

        if (! $this->option('keep')) {
            $article->update(['status' => Article::STATUS_ARCHIVED]);
            $this->line("Archived test article #{$article->id} (use --keep to retain).");
        } else {
            $this->line("Kept test article #{$article->id} — review at /admin/automation");
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{ok: bool, message: string, preview?: string}
     */
    private function openaiHealth(): array
    {
        $apiKey = (string) config('content.ai.api_key');
        if ($apiKey === '') {
            return ['ok' => false, 'message' => 'CONTENT_AI_API_KEY is empty'];
        }

        try {
            $response = \Illuminate\Support\Facades\Http::baseUrl((string) config('content.ai.base_url'))
                ->withToken($apiKey)
                ->timeout(30)
                ->acceptJson()
                ->post('/chat/completions', [
                    'model' => config('content.ai.model'),
                    'temperature' => 0,
                    'max_tokens' => 8,
                    'messages' => [
                        ['role' => 'user', 'content' => 'Reply with exactly: OK'],
                    ],
                ]);

            if (! $response->successful()) {
                return ['ok' => false, 'message' => 'HTTP '.$response->status().': '.Str::limit($response->body(), 240)];
            }

            $text = (string) data_get($response->json(), 'choices.0.message.content', '');

            return [
                'ok' => $text !== '',
                'message' => 'OpenAI-compatible endpoint accepted the key.',
                'preview' => Str::limit(trim($text), 80),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
