<?php

namespace App\Services\Content\Providers;

use App\Contracts\AiDraftProvider;
use App\Models\Article;
use App\Models\ContentEvent;
use Illuminate\Support\Str;

/**
 * Deterministic offline drafts — always available, versioned templates.
 * Swap to OpenAICompatibleDraftProvider when CONTENT_AI_PROVIDER=openai_compatible.
 */
class TemplateDraftProvider implements AiDraftProvider
{
    public function name(): string
    {
        return 'template';
    }

    public function model(): string
    {
        return 'template-'.config('content.ai.template_version', 'v1');
    }

    public function generate(Article $article, ContentEvent $event): array
    {
        $payload = $event->payload ?? [];
        $fuel = Str::headline((string) ($payload['primary_fuel'] ?? 'petrol'));
        $price = isset($payload['primary_price']) ? number_format((float) $payload['primary_price'], 2) : '—';
        $prev = isset($payload['previous_price']) ? number_format((float) $payload['previous_price'], 2) : '—';
        $delta = isset($payload['primary_delta']) ? (float) $payload['primary_delta'] : 0.0;
        $absDelta = number_format(abs($delta), 2);
        $date = (string) ($payload['effective_date'] ?? now()->toDateString());
        $direction = $delta >= 0 ? 'increased' : 'decreased';
        $movesHtml = $this->movesTable($payload['moves'] ?? []);

        $title = match ($event->type) {
            'fuel_price_increase' => "{$fuel} price rises by Rs {$absDelta} — now Rs {$price}/L (effective {$date})",
            'fuel_price_decrease' => "{$fuel} price cut by Rs {$absDelta} — now Rs {$price}/L (effective {$date})",
            'weekly_summary' => "Weekly Pakistan fuel price summary — week of {$date}",
            'monthly_summary' => "Monthly Pakistan fuel price report — {$date}",
            'government_notification' => "Government fuel price notification — {$date}",
            default => $article->title,
        };

        $excerpt = match ($event->type) {
            'fuel_price_increase', 'fuel_price_decrease' => "Official {$fuel} prices in Pakistan {$direction} by Rs {$absDelta} to Rs {$price} per litre, effective {$date}. Check city rates, calculators, and what this means for your weekly fuel budget.",
            'weekly_summary' => "A concise weekly rundown of petrol, diesel, LPG and HOBC moves across Pakistan — with links to live prices and cost calculators.",
            'monthly_summary' => "Month-in-review of Pakistan fuel prices: headline moves, city differences, and how to plan trips and monthly fuel spend.",
            default => "Latest fuel update for Pakistan drivers — effective {$date}.",
        };

        $fillImpact = number_format(((float) $absDelta) * 40, 0);
        $body = match ($event->type) {
            'weekly_summary', 'monthly_summary' => $this->summaryBody($event->type, $date, $movesHtml),
            default => $this->priceMoveBody($fuel, $price, $prev, $absDelta, $direction, $date, $movesHtml, $fillImpact),
        };

        $faq = [
            [
                'question' => "What is the new {$fuel} price in Pakistan?",
                'answer' => "As of {$date}, the headline {$fuel} rate referenced in this update is Rs {$price} per litre (previous Rs {$prev}). Always confirm your city page for local pump rates.",
            ],
            [
                'question' => 'Where can I check petrol and diesel prices for my city?',
                'answer' => 'Open the Fuel Prices PK city pages (for example Karachi, Lahore, Islamabad) or use the live home dashboard for national and city rates.',
            ],
            [
                'question' => 'How do I estimate trip or monthly fuel cost after this change?',
                'answer' => 'Use the fuel cost, trip cost, mileage, and monthly budget calculators on Fuel Prices PK — they pull the latest rates automatically.',
            ],
        ];

        return [
            'title' => $title,
            'excerpt' => $excerpt,
            'body' => $body,
            'tags' => array_values(array_unique([
                Str::slug($fuel),
                'pakistan',
                'fuel-prices',
                $event->type,
                $delta >= 0 ? 'price-increase' : 'price-decrease',
            ])),
            'faq' => $faq,
            'meta' => [
                'template' => config('content.ai.template_version'),
                'tone' => config('content.event_types.'.$event->type.'.tone', 'clear'),
            ],
        ];
    }

    private function priceMoveBody(
        string $fuel,
        string $price,
        string $prev,
        string $absDelta,
        string $direction,
        string $date,
        string $movesHtml,
        string $fillImpact,
    ): string {
        return <<<HTML
<p>Pakistan's headline <strong>{$fuel}</strong> price has <strong>{$direction}</strong> by <strong>Rs {$absDelta}</strong> per litre. The updated rate is <strong>Rs {$price}/L</strong> (previously Rs {$prev}/L), effective <strong>{$date}</strong>.</p>
<h2>What changed</h2>
{$movesHtml}
<p>Price revisions typically follow federal notifications and are reflected nationwide, with small city-level differences for some products. Always verify the rate at your local pump.</p>
<h2>How this affects your fuel budget</h2>
<p>A Rs {$absDelta}/L move adds up quickly. On a 40-litre fill, that is roughly <strong>Rs {$fillImpact}</strong> more or less at the pump. Weekly commuting and long intercity trips feel the change first.</p>
<h2>Check live prices and plan ahead</h2>
<ul>
<li>Compare city rates on Fuel Prices PK city landing pages</li>
<li>Estimate trip spend with the trip cost calculator</li>
<li>Project monthly fuel with the monthly budget calculator</li>
<li>Track history charts for 7-day and 30-day trends</li>
</ul>
<h2>Key takeaways</h2>
<ul>
<li>{$fuel}: Rs {$price}/L effective {$date}</li>
<li>Change vs previous: Rs {$absDelta}/L ({$direction})</li>
<li>Use calculators before long trips or fleet planning</li>
</ul>
HTML;
    }

    private function summaryBody(string $type, string $date, string $movesHtml): string
    {
        $label = $type === 'monthly_summary' ? 'Monthly' : 'Weekly';

        return <<<HTML
<p>This {$label} fuel price summary covers the main Pakistan pump-price moves around <strong>{$date}</strong>. Use it as a quick briefing before you open city pages or calculators.</p>
<h2>Headline moves</h2>
{$movesHtml}
<h2>What drivers should do next</h2>
<ul>
<li>Confirm petrol and diesel on your city page</li>
<li>Re-run trip cost estimates if you travel this week</li>
<li>Review history charts for trend context</li>
</ul>
<h2>Related tools</h2>
<p>Fuel Prices PK keeps live rates, history, and calculators in one place so you can react to notifications without guesswork.</p>
HTML;
    }

    /**
     * @param  list<array<string, mixed>>|mixed  $moves
     */
    private function movesTable(mixed $moves): string
    {
        if (! is_array($moves) || $moves === []) {
            return '<p>No detailed product breakdown was attached to this event.</p>';
        }

        $rows = '';
        foreach ($moves as $move) {
            if (! is_array($move)) {
                continue;
            }
            $fuel = e(Str::headline((string) ($move['fuel'] ?? 'fuel')));
            $price = number_format((float) ($move['price'] ?? 0), 2);
            $prev = number_format((float) ($move['previous'] ?? 0), 2);
            $delta = (float) ($move['delta'] ?? 0);
            $sign = $delta >= 0 ? '+' : '';
            $rows .= "<tr><td>{$fuel}</td><td>Rs {$prev}</td><td>Rs {$price}</td><td>{$sign}".number_format($delta, 2).'</td></tr>';
        }

        return '<table><thead><tr><th>Fuel</th><th>Previous</th><th>New</th><th>Change</th></tr></thead><tbody>'
            .$rows.'</tbody></table>';
    }

}
