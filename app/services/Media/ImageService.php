<?php
declare(strict_types=1);

class ImageService
{
    public static function createThumbnail(string $source, string $dest, int $maxWidth = 400): bool
    {
        if (!file_exists($source) || !is_readable($source)) {
            return false;
        }

        $info = getimagesize($source);

        if (!$info) {
            return false;
        }

        $srcWidth  = $info[0];
        $srcHeight = $info[1];
        $mime      = $info['mime'] ?? '';

        if ($srcWidth <= 0 || $srcHeight <= 0) {
            return false;
        }

        $ratio = $srcHeight / $srcWidth;

        $newWidth  = (int) $maxWidth;
        $newHeight = (int) round($maxWidth * $ratio);

        switch ($mime) {
            case 'image/jpeg':
                $src = imagecreatefromjpeg($source);
                break;

            case 'image/png':
                $src = imagecreatefrompng($source);
                break;

            case 'image/webp':
                $src = imagecreatefromwebp($source);
                break;

            default:
                return false;
        }

        if (!$src) {
            return false;
        }

        $thumb = imagecreatetruecolor($newWidth, $newHeight);

        if (!$thumb) {
            imagedestroy($src);
            return false;
        }

        /* transparência */
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);

            $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            imagefill($thumb, 0, 0, $transparent);
        }

        imagecopyresampled(
            $thumb,
            $src,
            0, 0, 0, 0,
            $newWidth, $newHeight,
            $srcWidth, $srcHeight
        );

        /* garantir pasta */
        $dir = dirname($dest);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $saved = false;

        switch ($mime) {
            case 'image/jpeg':
                $saved = imagejpeg($thumb, $dest, 85);
                break;

            case 'image/png':
                $saved = imagepng($thumb, $dest, 6);
                break;

            case 'image/webp':
                $saved = imagewebp($thumb, $dest, 85);
                break;
        }

        imagedestroy($src);
        imagedestroy($thumb);

        return $saved;
    }
}