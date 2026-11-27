<?php

$w = 1200;
$h = 630;
$im = imagecreatetruecolor($w, $h);
imagesavealpha($im, true);

$amber = imagecolorallocate($im, 242, 166, 63);
$text = imagecolorallocate($im, 245, 241, 232);
$muted = imagecolorallocate($im, 159, 170, 181);
$dark = imagecolorallocate($im, 16, 21, 28);

for ($y = 0; $y < $h; $y++) {
    $t = $y / ($h - 1);
    $r = (int) (11 + (27 - 11) * $t);
    $g = (int) (15 + (35 - 15) * $t);
    $b = (int) (20 + (45 - 20) * $t);
    $c = imagecolorallocate($im, $r, $g, $b);
    imageline($im, 0, $y, $w, $y, $c);
}

imagefilledrectangle($im, 0, 0, 8, $h, $amber);

$cx = 900;
$cy = 315;
$r = 150;
imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, $amber);

$drop = [
    $cx, $cy - 95,
    $cx + 48, $cy + 20,
    $cx + 38, $cy + 70,
    $cx, $cy + 95,
    $cx - 38, $cy + 70,
    $cx - 48, $cy + 20,
];
imagefilledpolygon($im, $drop, $dark);
imagefilledellipse($im, $cx, $cy + 35, 44, 44, $amber);

$font = 'C:/Windows/Fonts/segoeuib.ttf';
$fontReg = 'C:/Windows/Fonts/segoeui.ttf';
if (! is_file($font)) {
    $font = 'C:/Windows/Fonts/arialbd.ttf';
}
if (! is_file($fontReg)) {
    $fontReg = 'C:/Windows/Fonts/arial.ttf';
}

imagettftext($im, 64, 0, 72, 250, $amber, $font, 'Fuel Prices PK');
imagettftext($im, 26, 0, 74, 320, $muted, $fontReg, 'Live Pakistan fuel prices');
imagettftext($im, 22, 0, 74, 370, $text, $fontReg, 'Compare  ·  Calculate  ·  Nearby pumps');
imagettftext($im, 18, 0, 74, 540, $muted, $fontReg, 'oilprice.topgradesoftware.com');

$out = dirname(__DIR__).'/public/og-image.png';
imagepng($im, $out, 6);
imagedestroy($im);

echo $out.' ('.filesize($out)." bytes)\n";
