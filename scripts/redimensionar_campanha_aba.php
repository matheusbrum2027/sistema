<?php
declare(strict_types=1);
$src = dirname(__DIR__) . '/assets/campanha-aba.png';
$im = @imagecreatefrompng($src);
if (!$im) {
    fwrite(STDERR, "fail open\n");
    exit(1);
}
$w = imagesx($im);
$h = imagesy($im);
$size = 128;
$dst = imagecreatetruecolor($size, $size);
imagealphablending($dst, false);
imagesavealpha($dst, true);
$clear = imagecolorallocatealpha($dst, 0, 0, 0, 127);
imagefilledrectangle($dst, 0, 0, $size, $size, $clear);
imagecopyresampled($dst, $im, 0, 0, 0, 0, $size, $size, $w, $h);
imagepng($dst, $src, 8);
echo filesize($src) . "\n";
