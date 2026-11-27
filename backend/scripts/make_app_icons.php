<?php

/**
 * Square app icons matching the OG mark (amber circle + dark droplet).
 */

function makeIcon(int $size, string $path): void
{
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $transparent);

    $bg = imagecolorallocate($im, 16, 21, 28);
    $amber = imagecolorallocate($im, 242, 166, 63);
    $dark = imagecolorallocate($im, 16, 21, 28);

    $radius = (int) round($size * 0.188); // ~96/512
    // Rounded square background
    imagefilledrectangle($im, $radius, 0, $size - $radius - 1, $size - 1, $bg);
    imagefilledrectangle($im, 0, $radius, $size - 1, $size - $radius - 1, $bg);
    imagefilledellipse($im, $radius, $radius, $radius * 2, $radius * 2, $bg);
    imagefilledellipse($im, $size - $radius - 1, $radius, $radius * 2, $radius * 2, $bg);
    imagefilledellipse($im, $radius, $size - $radius - 1, $radius * 2, $radius * 2, $bg);
    imagefilledellipse($im, $size - $radius - 1, $size - $radius - 1, $radius * 2, $radius * 2, $bg);

    $cx = (int) round($size / 2);
    $cy = (int) round($size / 2);
    $circle = (int) round($size * 0.5625); // 288/512
    imagefilledellipse($im, $cx, $cy, $circle, $circle, $amber);

    // Droplet (same proportions as OG / icon SVG)
    $s = $size / 512;
    $drop = [
        (int) round($cx), (int) round($cy - 91 * $s),
        (int) round($cx + 48 * $s), (int) round($cy + 20 * $s),
        (int) round($cx + 38 * $s), (int) round($cy + 70 * $s),
        (int) round($cx), (int) round($cy + 95 * $s),
        (int) round($cx - 38 * $s), (int) round($cy + 70 * $s),
        (int) round($cx - 48 * $s), (int) round($cy + 20 * $s),
    ];
    imagefilledpolygon($im, $drop, $dark);

    $hole = (int) round(42 * $s);
    imagefilledellipse($im, $cx, (int) round($cy + 35 * $s), $hole, $hole, $amber);

    imagepng($im, $path, 6);
    imagedestroy($im);
    echo basename($path).' '.filesize($path)." bytes\n";
}

$public = dirname(__DIR__).'/public/icons';
if (! is_dir($public)) {
    mkdir($public, 0755, true);
}

makeIcon(192, $public.'/icon-192.png');
makeIcon(512, $public.'/icon-512.png');

// Also write a maskable-safe full-bleed variant for Android/PWA (no rounded corners crop risk)
function makeMaskable(int $size, string $path): void
{
    $im = imagecreatetruecolor($size, $size);
    $bg = imagecolorallocate($im, 16, 21, 28);
    $amber = imagecolorallocate($im, 242, 166, 63);
    $dark = imagecolorallocate($im, 16, 21, 28);
    imagefilledrectangle($im, 0, 0, $size - 1, $size - 1, $bg);

    $cx = (int) round($size / 2);
    $cy = (int) round($size / 2);
    // Keep mark inside safe zone (~80% for maskable)
    $circle = (int) round($size * 0.52);
    imagefilledellipse($im, $cx, $cy, $circle, $circle, $amber);

    $s = $size / 512;
    $drop = [
        (int) round($cx), (int) round($cy - 80 * $s),
        (int) round($cx + 42 * $s), (int) round($cy + 18 * $s),
        (int) round($cx + 33 * $s), (int) round($cy + 62 * $s),
        (int) round($cx), (int) round($cy + 84 * $s),
        (int) round($cx - 33 * $s), (int) round($cy + 62 * $s),
        (int) round($cx - 42 * $s), (int) round($cy + 18 * $s),
    ];
    imagefilledpolygon($im, $drop, $dark);
    $hole = (int) round(36 * $s);
    imagefilledellipse($im, $cx, (int) round($cy + 30 * $s), $hole, $hole, $amber);

    imagepng($im, $path, 6);
    imagedestroy($im);
    echo basename($path).' '.filesize($path)." bytes\n";
}

makeMaskable(512, $public.'/icon-512-maskable.png');
makeMaskable(192, $public.'/icon-192-maskable.png');

// Flutter / Android launcher densities (full-bleed mark, no rounded corners — OS masks).
$androidRes = dirname(__DIR__, 2).'/mobile/android/app/src/main/res';
$androidSizes = [
    'mipmap-mdpi' => 48,
    'mipmap-hdpi' => 72,
    'mipmap-xhdpi' => 96,
    'mipmap-xxhdpi' => 144,
    'mipmap-xxxhdpi' => 192,
];
foreach ($androidSizes as $folder => $px) {
    $dir = $androidRes.'/'.$folder;
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    makeMaskable($px, $dir.'/ic_launcher.png');
}
