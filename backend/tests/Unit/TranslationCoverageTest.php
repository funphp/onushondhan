<?php

/*
| Every user-facing message the public API can emit must have a Bangla translation.
| Scans __('...') literals and ApiException/fail() messages outside the admin panel.
*/
it('has a Bangla translation for every translatable API string', function () {
    $bn = json_decode((string) file_get_contents(dirname(__DIR__, 2).'/lang/bn.json'), true);
    $missing = [];
    $checked = 0;
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/app'));
    foreach ($files as $file) {
        $path = (string) $file;
        if (! str_ends_with($path, '.php') || str_contains($path, '/Filament/') || str_contains($path, '/Console/')) {
            continue;
        }
        $code = (string) file_get_contents($path);
        $patterns = [
            "/__\\('((?:[^'\\\\]|\\\\.)+)'/",
            "/new ApiException\\(\\s*'((?:[^'\\\\]|\\\\.)+)'/",
            "/ApiException::(?:forbidden|conflict|invalidState)\\('((?:[^'\\\\]|\\\\.)+)'/",
            "/->fail\\(\\\$field, '((?:[^'\\\\]|\\\\.)+)'/",
            "/\\[\\s*'((?:[A-Z][^'\\\\]|\\\\.)+\\.)'\\s*\\]/",
        ];
        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $code, $m);
            foreach ($m[1] as $key) {
                $key = stripslashes($key);
                $checked++;
                if (! array_key_exists($key, $bn)) {
                    $missing[$key] = basename($path);
                }
            }
        }
    }
    expect($checked)->toBeGreaterThan(100)
        ->and($missing)->toBe([]);
});
