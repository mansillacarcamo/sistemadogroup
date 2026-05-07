<?php
/**
 * Genera todos los tamaños de íconos PWA + apple-touch-icon a partir
 * de logo.png. Pinta fondo blanco para que iOS no muestre la inicial
 * y deje el ícono como app.
 *
 * Uso:  php img/_generate_icons.php
 */
$src = __DIR__ . '/logo.png';
if (!file_exists($src)) { fwrite(STDERR, "logo.png no existe\n"); exit(1); }

$logo = imagecreatefrompng($src);
imagealphablending($logo, true);
imagesavealpha($logo, true);
$lw = imagesx($logo); $lh = imagesy($logo);

$tamanos = [192, 512, 180, 167, 152, 120, 76];
$padding = 0.10; // 10% padding alrededor del logo

foreach ($tamanos as $size) {
    $img = imagecreatetruecolor($size, $size);
    $blanco = imagecolorallocate($img, 255, 255, 255);
    imagefilledrectangle($img, 0, 0, $size, $size, $blanco);

    $maxLogo = (int)($size * (1 - 2 * $padding));
    $scale = min($maxLogo / $lw, $maxLogo / $lh);
    $newW = (int)($lw * $scale);
    $newH = (int)($lh * $scale);
    $x = (int)(($size - $newW) / 2);
    $y = (int)(($size - $newH) / 2);

    imagecopyresampled($img, $logo, $x, $y, 0, 0, $newW, $newH, $lw, $lh);
    $out = __DIR__ . "/icon-{$size}.png";
    imagepng($img, $out, 9);
    imagedestroy($img);
    echo "✓ $out\n";
}
imagedestroy($logo);
echo "Listo.\n";
