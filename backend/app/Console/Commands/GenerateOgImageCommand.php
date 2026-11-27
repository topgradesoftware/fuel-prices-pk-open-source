<?php

namespace App\Console\Commands;

use App\Models\FuelPrice;
use App\Models\FuelType;
use Illuminate\Console\Command;

/**
 * Render the live petrol/diesel price onto the OG template so link previews
 * (WhatsApp, Facebook, X…) always show the current rate.
 */
class GenerateOgImageCommand extends Command
{
    protected $signature = 'og:generate';

    protected $description = 'Render current fuel prices onto the Open Graph share image';

    private const FONT_CANDIDATES = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans-Bold.ttf',
        'C:/Windows/Fonts/segoeuib.ttf',
        'C:/Windows/Fonts/arialbd.ttf',
    ];

    private const FONT_REGULAR_CANDIDATES = [
        '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans.ttf',
        'C:/Windows/Fonts/segoeui.ttf',
        'C:/Windows/Fonts/arial.ttf',
    ];

    public function handle(): int
    {
        if (! extension_loaded('gd')) {
            $this->warn('GD extension missing — keeping existing og-image.jpg.');

            return self::SUCCESS;
        }

        $template = resource_path('og/og-template.jpg');
        if (! is_file($template)) {
            $this->warn('OG template missing — keeping existing og-image.jpg.');

            return self::SUCCESS;
        }

        $bold = $this->firstFile(self::FONT_CANDIDATES);
        $regular = $this->firstFile(self::FONT_REGULAR_CANDIDATES) ?? $bold;
        if ($bold === null) {
            $this->warn('No TTF font found — keeping existing og-image.jpg.');

            return self::SUCCESS;
        }

        $petrol = $this->headlinePrice('petrol');
        $hsd = $this->headlinePrice('hsd');
        if ($petrol === null) {
            $this->warn('No petrol price available — keeping existing og-image.jpg.');

            return self::SUCCESS;
        }

        $im = imagecreatefromjpeg($template);
        if ($im === false) {
            $this->error('Could not load OG template.');

            return self::FAILURE;
        }

        $amber = imagecolorallocate($im, 242, 166, 63);
        $white = imagecolorallocate($im, 245, 241, 232);
        $muted = imagecolorallocate($im, 168, 178, 189);

        // Card interior (template is 1200x630; glass card sits lower-right).
        $x = 745;

        imagettftext($im, 17, 0, $x, 425, $muted, $regular, 'PETROL');
        imagettftext($im, 42, 0, $x, 487, $amber, $bold, 'Rs '.number_format($petrol['price'], 2));
        imagettftext($im, 18, 0, $x + $this->textWidth(42, $bold, 'Rs '.number_format($petrol['price'], 2)) + 10, 487, $muted, $regular, '/L');

        if ($hsd !== null) {
            imagettftext($im, 18, 0, $x, 535, $white, $regular, 'Diesel (HSD): Rs '.number_format($hsd['price'], 2).'/L');
        }

        $date = $petrol['effective_date'] ?: now()->toDateString();
        imagettftext($im, 15, 0, $x, 578, $muted, $regular, 'Effective '.date('d M Y', strtotime($date)));

        $out = public_path('og-image.jpg');
        imagejpeg($im, $out, 85);
        imagedestroy($im);

        $this->info('OG image rendered: petrol Rs '.number_format($petrol['price'], 2).' → '.$out);

        return self::SUCCESS;
    }

    /**
     * @return array{price: float, effective_date: ?string}|null
     */
    private function headlinePrice(string $fuelSlug): ?array
    {
        $typeId = FuelType::query()->where('slug', $fuelSlug)->value('id');
        if ($typeId === null) {
            return null;
        }

        $row = FuelPrice::query()
            ->where('fuel_type_id', $typeId)
            ->where('price', '>', 0)
            ->whereHas('company', fn ($q) => $q->where('slug', 'pso'))
            ->whereNull('city_id')
            ->orderByDesc('effective_date')
            ->first()
            ?? FuelPrice::query()
                ->where('fuel_type_id', $typeId)
                ->where('price', '>', 0)
                ->orderByDesc('effective_date')
                ->first();

        if ($row === null) {
            return null;
        }

        return [
            'price' => (float) $row->price,
            'effective_date' => optional($row->effective_date)?->toDateString(),
        ];
    }

    private function firstFile(array $candidates): ?string
    {
        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private function textWidth(float $size, string $font, string $text): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return $box === false ? 0 : (int) abs($box[2] - $box[0]);
    }
}
