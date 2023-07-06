<?php

$options = getopt("r::i:s:p:o:c:", ["recursive::", "output-image:", "output-style:", "padding:", "override-size:", "columns_number:"]);

$recursive = isset($options['r']) || isset($options['recursive']);
$outputImage = $options['i'] ?? $options['output-image'] ?? 'sprite.png';
$outputStyle = $options['s'] ?? $options['output-style'] ?? 'style.css';
$padding = $options['p'] ?? $options['padding'] ?? 0;
$overrideSize = $options['o'] ?? $options['override-size'] ?? null;
$columnsNumber = $options['c'] ?? $options['columns_number'] ?? null;


function getPngFiles($dir, $recursive)
{
    $pngFiles = [];

    $files = array_diff(glob($dir . '/*'), ['..', '.']);

    foreach ($files as $file) {
        if (is_dir($file) && $recursive) {
            $pngFiles = array_merge($pngFiles, getPngFiles($file, $recursive));
        } elseif (pathinfo($file, PATHINFO_EXTENSION) === 'png') {
            $pngFiles[] = $file;
        }
    }

    return $pngFiles;
}


$images = getPngFiles('assets_folder', $recursive);

$width = 0;
$height = 0;
$positions = [];

$currentX = 0;
$currentY = 0;
$maxRowHeight = 0;
$columnCount = 0;

foreach ($images as $image) {
    $size = getimagesize($image);

    if ($overrideSize) {
        $size[0] = $size[1] = $overrideSize;
    }

    if ($columnsNumber && $columnCount >= $columnsNumber) {
        $currentX = 0;
        $currentY += $maxRowHeight + $padding;
        $maxRowHeight = 0;
        $columnCount = 0;
    }

    $positions[$image] = ['x' => $currentX, 'y' => $currentY];
    $width = max($width, $currentX + $size[0]);
    $height = max($height, $currentY + $size[1]);
    $maxRowHeight = max($maxRowHeight, $size[1]);

    $currentX += $size[0] + $padding;
    $columnCount++;
}
if ($width === 0 || $height === 0) {
    die("Aucune image valide trouvée. Assurez-vous que le dossier contient des images PNG avec une largeur et une hauteur supérieures à 0.\n");
}

$sprite = imagecreatetruecolor($width, $height);
imagesavealpha($sprite, true);
$transparent = imagecolorallocatealpha($sprite, 0, 0, 0, 127);
imagefill($sprite, 0, 0, $transparent);

foreach ($images as $image) {
    $source = imagecreatefrompng($image);
    $size = getimagesize($image);

    if ($overrideSize) {
        $resizedSource = imagecreatetruecolor($overrideSize,$overrideSize);
        imagesavealpha($resizedSource, true);
        $transparent = imagecolorallocatealpha($resizedSource, 0, 0, 0, 127);
        imagefill($resizedSource, 0, 0, $transparent);
        imagecopyresampled($resizedSource, $source, 0, 0, 0, 0, $overrideSize, $overrideSize, $size[0], $size[1]);
        $source = $resizedSource;
        $size[0] = $size[1] = $overrideSize;
        }
        $x = $positions[$image]['x'];
        $y = $positions[$image]['y'];
        imagecopy($sprite, $source, $x, $y, 0, 0, $size[0], $size[1]);
        imagedestroy($source);
    }

    imagepng($sprite, $outputImage);
    imagedestroy($sprite);
    
    
$css = '';

foreach ($images as $image) {
    $name = pathinfo($image, PATHINFO_FILENAME);
    $x = $positions[$image]['x'];
    $y = $positions[$image]['y'];

    $css .= ".{$name} {\n";
    $css .= "  background-image: url('{$outputImage}');\n";
    $css .= "  background-position: -{$x}px -{$y}px;\n";

    if ($overrideSize) {
        $css .= "  width: {$overrideSize}px;\n";
        $css .= "  height: {$overrideSize}px;\n";
    } else {
        $size = getimagesize($image);
        $css .= "  width: {$size[0]}px;\n";
        $css .= "  height: {$size[1]}px;\n";
    }

    $css .= "}\n";
}

file_put_contents($outputStyle, $css);
