<?php

namespace App\Services;

/**
 * Turns the data URL a browser sends into a clean PNG.
 *
 * Only real PNG and JPEG images are accepted: the content is decoded and
 * re-encoded, so whatever else the upload carried is dropped.
 */
class SignatureImage
{
    /** Largest accepted image once decoded, in bytes. */
    public const MAX_BYTES = 1024 * 1024;

    /** Signatures wider than this are scaled down, to keep the final PDF small. */
    public const MAX_WIDTH = 1000;

    /**
     * PNG bytes for the data URL, or null when it is not a valid PNG or JPEG.
     */
    public function fromDataUrl(string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/(?:png|jpe?g);base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $match)) {
            return null;
        }

        $bytes = base64_decode($match[1], true);

        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_BYTES) {
            return null;
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        if (imagesx($image) > self::MAX_WIDTH) {
            $scaled = imagescale($image, self::MAX_WIDTH);

            if ($scaled !== false) {
                $image = $scaled;
            }
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $written = imagepng($image);
        $png = (string) ob_get_clean();

        return $written && $png !== '' ? $png : null;
    }
}
