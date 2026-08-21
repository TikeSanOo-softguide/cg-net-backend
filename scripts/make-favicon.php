<?php

$srcPath = dirname(__DIR__).'/public/images/smart-link-logo.jpg';
$public = dirname(__DIR__).'/public';

$src = imagecreatefromjpeg($srcPath);

if ($src === false) {
    fwrite(STDERR, "Unable to read logo JPEG.\n");
    exit(1);
}

$sw = imagesx($src);
$sh = imagesy($src);
$crop = (int) round($sw * 0.58);
$sx = (int) round(($sw - $crop) / 2);
$sy = (int) round($sh * 0.08);

function savePng($src, int $sx, int $sy, int $crop, int $size, string $path): void
{
    $dst = imagecreatetruecolor($size, $size);
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $size, $size, $crop, $crop);
    imagepng($dst, $path, 6);
    imagedestroy($dst);
}

savePng($src, $sx, $sy, $crop, 16, $public.'/favicon-16x16.png');
savePng($src, $sx, $sy, $crop, 32, $public.'/favicon-32x32.png');
savePng($src, $sx, $sy, $crop, 180, $public.'/apple-touch-icon.png');
savePng($src, $sx, $sy, $crop, 192, $public.'/icon-192.png');
imagedestroy($src);

$pngs = [
    [16, $public.'/favicon-16x16.png'],
    [32, $public.'/favicon-32x32.png'],
];

$count = count($pngs);
$offset = 6 + (16 * $count);
$header = pack('v3', 0, 1, $count);
$bodies = '';

foreach ($pngs as [$size, $path]) {
    $data = file_get_contents($path);
    $header .= pack('C4v2V2', $size >= 256 ? 0 : $size, $size >= 256 ? 0 : $size, 0, 0, 1, 32, strlen($data), $offset);
    $bodies .= $data;
    $offset += strlen($data);
}

file_put_contents($public.'/favicon.ico', $header.$bodies);

echo "Favicon files written.\n";
