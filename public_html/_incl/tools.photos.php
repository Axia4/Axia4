<?php

function generatethumbnail($src, $dest, $targetWidth, $targetHeight) {
    $sourceImage = null;
    $info = getimagesize($src);
    $mime = $info['mime'];

    switch ($mime) {
        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($src);
            break;
        case 'image/png':
            $sourceImage = imagecreatefrompng($src);
            break;
        case 'image/gif':
            $sourceImage = imagecreatefromgif($src);
            break;
        default:
            return false;
    }
    if (file_exists($dest)) {
        return false; // Thumbnail already exists
    }
    $width = imagesx($sourceImage);
    $height = imagesy($sourceImage);
    // If the target height is not set, calculate it to maintain aspect ratio
    if ($targetHeight == 0) {
        $targetHeight = (int)($targetWidth * $height / $width);
    }

    $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
    // Preserve transparency for PNG and GIF
    if ($mime == 'image/png' || $mime == 'image/gif') {
        imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }
    // Resize and crop
    $sourceAspect = $width / $height;
    $thumbAspect = $targetWidth / $targetHeight;

    if ($sourceAspect > $thumbAspect) {
        // Source is wider
        $newHeight = $targetHeight;
        $newWidth = (int)($targetHeight * $sourceAspect);
    } else {
        // Source is taller or equal
        $newWidth = $targetWidth;
        $newHeight = (int)($targetWidth / $sourceAspect);
    }

    // Center the image
    $xOffset = (int)(($newWidth - $targetWidth) / 2);
    $yOffset = (int)(($newHeight - $targetHeight) / 2);

    imagecopyresampled($thumb, $sourceImage, -$xOffset, -$yOffset, 0, 0, $newWidth, $newHeight, $width, $height);
    // Save as jpg
    imagejpeg($thumb, $dest, 85);
    imagedestroy($sourceImage);
    imagedestroy($thumb);
    return true;
}