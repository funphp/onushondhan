<?php

use App\Support\Slug;

it('keeps Bangla words intact, including vowel signs and conjuncts', function () {
    expect(Slug::make('খুলনায় সড়ক সংস্কারের ঠিকাদারি'))->toBe('খুলনায়-সড়ক-সংস্কারের-ঠিকাদারি');
    expect(Slug::make('ধলেশ্বরী নদীতে ট্যানারির বর্জ্য: ছয় মাস'))->toBe('ধলেশ্বরী-নদীতে-ট্যানারির-বর্জ্য-ছয়-মাস');
});

it('handles English and mixed text', function () {
    expect(Slug::make('Road Repair Contracts in Khulna, 2025!'))->toBe('road-repair-contracts-in-khulna-2025');
    expect(Slug::make('Padma সেতু audit'))->toBe('padma-সেতু-audit');
});

it('falls back and truncates safely', function () {
    expect(Slug::make('!!!', 50, 'investigation'))->toBe('investigation');
    $long = Slug::make(str_repeat('সংস্কার ', 40), 30);
    expect(mb_strlen($long))->toBeLessThanOrEqual(30)->and($long)->not->toEndWith('-');
});
