<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Small line-based LCS diff used for version comparison.
 */
final class TextDiff
{
    private const MAX_LINES = 1500;

    /** @return list<array{op: string, text: string}> op is one of: equal, add, remove */
    public static function lines(?string $old, ?string $new): array
    {
        $a = $old === null || $old === '' ? [] : preg_split('/\R/u', $old);
        $b = $new === null || $new === '' ? [] : preg_split('/\R/u', $new);
        $a = $a === false ? [] : $a;
        $b = $b === false ? [] : $b;

        if (count($a) > self::MAX_LINES || count($b) > self::MAX_LINES) {
            return [['op' => 'remove', 'text' => '[content too large to diff line by line]'], ['op' => 'add', 'text' => '[see full versions]']];
        }

        $n = count($a);
        $m = count($b);
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $out = [];
        $i = $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $out[] = ['op' => 'equal', 'text' => $a[$i]];
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $out[] = ['op' => 'remove', 'text' => $a[$i++]];
            } else {
                $out[] = ['op' => 'add', 'text' => $b[$j++]];
            }
        }
        while ($i < $n) {
            $out[] = ['op' => 'remove', 'text' => $a[$i++]];
        }
        while ($j < $m) {
            $out[] = ['op' => 'add', 'text' => $b[$j++]];
        }

        return $out;
    }
}
