<?php

namespace App\Services\Content\Distribution;

use App\Contracts\DistributionAdapter;
use App\Models\Article;
use App\Models\ContentDistribution;
use App\Models\ContentJob;
use App\Services\Content\ContentAuditLogger;
use App\Support\SafeError;

class DistributionManager
{
    /** @var list<DistributionAdapter> */
    private array $adapters;

    public function __construct(
        private readonly ContentAuditLogger $audits,
        WebsiteAdapter $website,
        RssAdapter $rss,
        NewsSitemapAdapter $newsSitemap,
        IndexNowAdapter $indexNow,
        WhatsAppAdapter $whatsapp,
        TelegramAdapter $telegram,
    ) {
        $this->adapters = [
            $website,
            $rss,
            $newsSitemap,
            $indexNow,
            $whatsapp,
            $telegram,
            new StubSocialAdapter('facebook', 'facebook_credentials_missing'),
            new StubSocialAdapter('x', 'x_credentials_missing'),
            new StubSocialAdapter('linkedin', 'linkedin_credentials_missing'),
        ];
    }

    public function queueAndDispatch(Article $article): void
    {
        $job = ContentJob::query()->create([
            'content_event_id' => $article->content_event_id,
            'article_id' => $article->id,
            'stage' => 'distribute',
            'status' => 'running',
            'attempts' => 1,
            'started_at' => now(),
        ]);

        $results = [];
        foreach ($this->adapters as $adapter) {
            $channel = $adapter->channel();
            $row = ContentDistribution::query()->updateOrCreate(
                ['article_id' => $article->id, 'channel' => $channel],
                [
                    'status' => ContentDistribution::STATUS_QUEUED,
                    'queued_at' => now(),
                    'attempts' => 0,
                ],
            );

            try {
                if (! $adapter->enabled()) {
                    $row->update([
                        'status' => ContentDistribution::STATUS_SKIPPED,
                        'last_error' => 'channel_disabled',
                        'payload' => ['reason' => 'channel_disabled'],
                    ]);
                    $results[$channel] = 'skipped';
                    continue;
                }

                $outcome = $adapter->distribute($article);
                $status = $outcome['status'] ?? 'failed';
                $row->update([
                    'status' => $status,
                    'attempts' => $row->attempts + 1,
                    'payload' => $outcome['payload'] ?? null,
                    'last_error' => $outcome['error'] ?? null,
                    'sent_at' => $status === 'sent' ? now() : null,
                ]);
                $results[$channel] = $status;
            } catch (\Throwable $e) {
                // Isolation: one adapter must never abort the fan-out.
                SafeError::log($e, 'content.distribution adapter failed', [
                    'channel' => $channel,
                    'article_id' => $article->id,
                ]);
                $row->update([
                    'status' => ContentDistribution::STATUS_FAILED,
                    'attempts' => $row->attempts + 1,
                    'last_error' => SafeError::storedMessage('Distribution failed. See server logs for details.'),
                ]);
                $results[$channel] = 'failed';
            }
        }

        $job->update([
            'status' => in_array('failed', $results, true) ? 'completed_with_errors' : 'completed',
            'finished_at' => now(),
            'meta' => $results,
        ]);
        $this->audits->log('distributed', $article, after: $results);
    }
}
