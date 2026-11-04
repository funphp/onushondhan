<?php

declare(strict_types=1);

namespace App\Support;

use App\Contracts\FileVault;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams a stored (already re-encoded) investigation header image from the private vault. */
final class CoverImageResponse
{
    public static function make(FileVault $vault, ?string $key, bool $public): StreamedResponse
    {
        abort_if($key === null || ! $vault->exists($key), 404);

        // Re-encoded covers are JPEG or PNG; sniff the stored bytes rather than trusting a name.
        $mime = $vault->withLocalPath($key, function (string $path): string {
            $info = @getimagesize($path);

            return $info === false ? 'image/jpeg' : $info['mime'];
        });
        $mime = in_array($mime, ['image/jpeg', 'image/png'], true) ? $mime : 'image/jpeg';

        return response()->stream(function () use ($vault, $key): void {
            $stream = $vault->readStream($key);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mime,
            // URLs carry a content hash (?v=…), so published covers can be cached for long.
            'Cache-Control' => $public ? 'public, max-age=604800' : 'private, no-store',
            'Content-Disposition' => 'inline; filename="cover.'.($mime === 'image/png' ? 'png' : 'jpg').'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
