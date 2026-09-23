<?php

use App\Support\TextDiff;

it('produces a line diff', function () {
    $ops = TextDiff::lines("a\nb\nc", "a\nx\nc");
    expect(array_column($ops, 'op'))->toBe(['equal', 'remove', 'add', 'equal']);
    expect(TextDiff::lines(null, 'new'))->toBe([['op' => 'add', 'text' => 'new']]);
});
