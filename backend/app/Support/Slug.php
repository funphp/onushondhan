<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Unicode-aware slugs. Content may be written in Bangla or English, so letters,
 * combining marks (Bangla vowel signs/hasanta) and digits of any script are kept;
 * everything else becomes a single hyphen. Latin text is lower-cased.
 */
final class Slug
{
    public static function make(string $text, int $maxChars = 120, string $fallback = 'item'): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        // Zero-width joiners/non-joiners occur inside Bangla words; drop them.
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text) ?? '';
        $slug = preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', $text) ?? '';
        $slug = trim($slug, '-');
        if ($slug === '') {
            return $fallback;
        }
        if (mb_strlen($slug) > $maxChars) {
            $slug = rtrim(mb_substr($slug, 0, $maxChars), '-');
            // Never end on a dangling combining mark.
            $slug = preg_replace('/\p{M}+$/u', '', $slug) ?: $fallback;
        }

        return $slug;
    }
}
