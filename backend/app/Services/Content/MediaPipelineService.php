<?php

namespace App\Services\Content;

use App\Models\Article;
use App\Models\ContentJob;
use App\Support\SafeError;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Generates branded social preview images (OG, X, WhatsApp, Telegram, square, story).
 */
class MediaPipelineService
{
    private const FONT_BOLD = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        'C:/Windows/Fonts/segoeuib.ttf',
        'C:/Windows/Fonts/arialbd.ttf',
    ];

    private const FONT_REGULAR = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        'C:/Windows/Fonts/segoeui.ttf',
        'C:/Windows/Fonts/arial.ttf',
    ];

    /** @var array<string, array{w:int,h:int}> */
    private const SIZES = [
        'og' => ['w' => 1200, 'h' => 630],
        'twitter' => ['w' => 1200, 'h' => 628],
        'whatsapp' => ['w' => 1200, 'h' => 630],
        'telegram' => ['w' => 1200, 'h' => 630],
        'square' => ['w' => 1080, 'h' => 1080],
        'story' => ['w' => 1080, 'h' => 1920],
    ];

    public function __construct(
        private readonly ContentAuditLogger $audits,
    ) {}

    public function generate(Article $article): Article
    {
        $job = ContentJob::query()->create([
            'content_event_id' => $article->content_event_id,
            'article_id' => $article->id,
            'stage' => 'media',
            'status' => 'running',
            'attempts' => 1,
            'started_at' => now(),
        ]);

        try {
            if (! extension_loaded('gd')) {
                $job->update([
                    'status' => 'skipped',
                    'error' => 'gd_missing',
                    'finished_at' => now(),
                ]);

                return $article;
            }

            $dir = public_path('content/og/'.$article->id);
            File::ensureDirectoryExists($dir);

            $social = [];
            foreach (self::SIZES as $key => $size) {
                $filename = $key.'.jpg';
                $path = $dir.DIRECTORY_SEPARATOR.$filename;
                $this->renderCard($path, $size['w'], $size['h'], $article);
                $social[$key] = '/content/og/'.$article->id.'/'.$filename;
            }

            $article->fill([
                'og_image_path' => $social['og'] ?? null,
                'social_images' => $social,
            ])->save();

            $job->update([
                'status' => 'completed',
                'finished_at' => now(),
                'meta' => ['variants' => array_keys($social)],
            ]);
            $this->audits->log('media_generated', $article, after: $social);

            return $article->refresh();
        } catch (\Throwable $e) {
            SafeError::log($e, 'content.media failed', ['article_id' => $article->id]);
            $job->update([
                'status' => 'failed',
                'error' => SafeError::storedMessage('Media generation failed. See server logs for details.'),
                'finished_at' => now(),
            ]);
            throw $e;
        }
    }

    private function renderCard(string $path, int $w, int $h, Article $article): void
    {
        $im = imagecreatetruecolor($w, $h);
        if ($im === false) {
            throw new \RuntimeException('Unable to create image');
        }

        $bg = imagecolorallocate($im, 18, 28, 38);
        $amber = imagecolorallocate($im, 242, 166, 63);
        $white = imagecolorallocate($im, 245, 241, 232);
        $muted = imagecolorallocate($im, 168, 178, 189);
        imagefilledrectangle($im, 0, 0, $w, $h, $bg);

        // Accent bar
        imagefilledrectangle($im, 0, 0, (int) ($w * 0.02), $h, $amber);

        $bold = $this->firstFont(self::FONT_BOLD);
        $regular = $this->firstFont(self::FONT_REGULAR) ?? $bold;

        $brand = 'Fuel Prices PK';
        $title = Str::limit($article->title, $h > 1000 ? 90 : 70, '…');
        $excerpt = Str::limit($article->excerpt ?: '', $h > 1000 ? 160 : 110, '…');

        $padX = (int) ($w * 0.08);
        $y = (int) ($h * 0.18);

        if ($bold) {
            imagettftext($im, (int) max(18, $w * 0.028), 0, $padX, $y, $amber, $bold, $brand);
            $y += (int) ($h * 0.12);
            $this->wrappedText($im, $title, $bold, (int) max(22, $w * 0.042), $padX, $y, $white, (int) ($w * 0.84));
            $y += (int) ($h * 0.18);
            if ($regular) {
                $this->wrappedText($im, $excerpt, $regular, (int) max(14, $w * 0.022), $padX, $y, $muted, (int) ($w * 0.84));
            }
            $footerY = (int) ($h * 0.9);
            imagettftext($im, (int) max(12, $w * 0.018), 0, $padX, $footerY, $muted, $regular ?: $bold, 'oilprice.topgradesoftware.com');
        } else {
            imagestring($im, 5, $padX, $y, $brand, $amber);
            imagestring($im, 5, $padX, $y + 40, substr($title, 0, 60), $white);
        }

        imagejpeg($im, $path, 85);
        imagedestroy($im);
    }

    /**
     * @param  \GdImage  $im
     */
    private function wrappedText($im, string $text, string $font, int $size, int $x, int &$y, int $color, int $maxWidth): void
    {
        $words = preg_split('/\s+/', $text) ?: [];
        $line = '';
        $lineHeight = (int) ($size * 1.45);

        foreach ($words as $word) {
            $test = $line === '' ? $word : $line.' '.$word;
            $box = imagettfbbox($size, 0, $font, $test);
            $width = $box === false ? 0 : abs($box[2] - $box[0]);
            if ($width > $maxWidth && $line !== '') {
                imagettftext($im, $size, 0, $x, $y, $color, $font, $line);
                $y += $lineHeight;
                $line = $word;
            } else {
                $line = $test;
            }
        }
        if ($line !== '') {
            imagettftext($im, $size, 0, $x, $y, $color, $font, $line);
            $y += $lineHeight;
        }
    }

    /** @param  list<string>  $candidates */
    private function firstFont(array $candidates): ?string
    {
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
