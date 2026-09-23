<?php

use App\Models\Investigation;

it('finds published investigations by full-text query and filters', function () {
    $hit = $this->publishedInvestigation(null, ['title' => 'Mangrove clearance near Sundarbans', 'summary' => 'Satellite images show mangrove loss.']);
    $this->publishedInvestigation(null, ['title' => 'Hospital oxygen procurement', 'summary' => 'Oxygen purchase records.']);
    $draft = Investigation::factory()->create(['title' => 'Mangrove draft never published']);

    $ids = collect($this->getJson('/api/v1/investigations?q=mangrove')->assertOk()->json('data'))->pluck('id');
    expect($ids->all())->toBe([$hit->public_id]);
    expect($ids)->not->toContain($draft->public_id);

    $this->getJson('/api/v1/investigations?q=mang')->assertJsonCount(1, 'data'); // prefix match
    $this->getJson('/api/v1/investigations?q=%2B%2B%2B--*')->assertOk();         // operators stripped
    $this->getJson('/api/v1/investigations?category=governance-public-administration&country=BD&sort=most_read')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson('/api/v1/investigations?category=unknown')->assertJsonCount(0, 'data');
    $this->getJson('/api/v1/investigations?from=2000-01-01&to=2000-01-02')->assertJsonCount(0, 'data');
});
