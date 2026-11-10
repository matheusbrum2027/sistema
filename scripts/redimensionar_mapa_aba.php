<?php
declare(strict_types=1);
$raiz = dirname(__DIR__);
$srcJpg = $raiz . '/assets/mapa-aba-src.jpg';
$srcCursor = 'C:/Users/ADMIN/.cursor/projects/c-Users-ADMIN-OneDrive-rea-de-Trabalho-fln-transfer/assets/mapa-aba-3d.jpg';
if (!is_file($srcJpg) && is_file($srcCursor)) {
    copy($srcCursor, $srcJpg);
}
$im = @imagecreatefromjpeg($srcJpg);
if (!$im) {
    $im = @imagecreatefrompng($srcJpg);
}
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
$out = $raiz . '/assets/mapa-aba.png';
imagepng($dst, $out, 8);
echo filesize($out) . "\n";
