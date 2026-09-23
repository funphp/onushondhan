<?php

declare(strict_types=1);

namespace App\Services\Files;

/**
 * Produces a metadata-free re-encoded rendition of an image (EXIF/GPS/XMP dropped by
 * re-encoding through GD). Public viewers only ever receive this rendition.
 */
class ImageSanitizer
{
    private const MAX_EDGE = 2560;

    private const MAX_PIXELS = 50_000_000;

    /**
     * @return array{path: string, width: int, height: int, mime: string, source_metadata: array<string, mixed>}|null
     */
    public function sanitize(string $sourcePath, string $mime): ?array
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }
        $info = @getimagesize($sourcePath);
        if ($info === false || $info[0] * $info[1] > self::MAX_PIXELS) {
            return null;
        }

        $sourceMeta = ['width' => $info[0], 'height' => $info[1]];
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($sourcePath);
            if (is_array($exif)) {
                // Only coarse, non-locating facts are kept; coordinates are never stored.
                $sourceMeta['exif_present'] = true;
                $sourceMeta['has_gps'] = isset($exif['GPSLatitude']) || isset($exif['GPSLongitude']);
                $sourceMeta['camera'] = isset($exif['Model']) ? mb_substr((string) $exif['Model'], 0, 80) : null;
                $sourceMeta['captured_at'] = isset($exif['DateTimeOriginal']) ? mb_substr((string) $exif['DateTimeOriginal'], 0, 32) : null;
            }
        }

        $data = file_get_contents($sourcePath);
        $image = $data === false ? false : @imagecreatefromstring($data);
        if ($image === false) {
            return null;
        }

        [$w, $h] = [imagesx($image), imagesy($image)];
        $scale = min(1, self::MAX_EDGE / max($w, $h));
        if ($scale < 1) {
            $nw = max(1, (int) round($w * $scale));
            $nh = max(1, (int) round($h * $scale));
            $resized = imagecreatetruecolor($nw, $nh);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            imagecopyresampled($resized, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($image);
            $image = $resized;
            [$w, $h] = [$nw, $nh];
        }

        $out = tempnam(sys_get_temp_dir(), 'san');
        if ($out === false) {
            imagedestroy($image);

            return null;
        }
        $outMime = in_array($mime, ['image/png', 'image/gif'], true) ? 'image/png' : 'image/jpeg';
        $ok = $outMime === 'image/png' ? imagepng($image, $out, 6) : imagejpeg($image, $out, 85);
        imagedestroy($image);
        if (! $ok) {
            @unlink($out);

            return null;
        }

        return ['path' => $out, 'width' => $w, 'height' => $h, 'mime' => $outMime, 'source_metadata' => $sourceMeta];
    }
}
