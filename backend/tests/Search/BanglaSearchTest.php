<?php

it('finds Bangla and English investigations with full-text search', function () {
    $bn = $this->publishedInvestigation(null, ['title' => 'খুলনায় সড়ক সংস্কারের ঠিকাদারি', 'summary' => 'একক দরদাতাকে কাজ দেওয়ার অভিযোগ।']);
    $en = $this->publishedInvestigation(null, ['title' => 'Ferry subsidy audit', 'summary' => 'Subsidies paid without logs.']);

    expect(collect($this->getJson('/api/v1/investigations?q='.urlencode('সড়ক'))->json('data'))->pluck('id')->all())->toBe([$bn->public_id]);
    expect(collect($this->getJson('/api/v1/investigations?q='.urlencode('ঠিকাদার'))->json('data'))->pluck('id')->all())->toBe([$bn->public_id]);
    expect(collect($this->getJson('/api/v1/investigations?q=ferry')->json('data'))->pluck('id')->all())->toBe([$en->public_id]);
});
