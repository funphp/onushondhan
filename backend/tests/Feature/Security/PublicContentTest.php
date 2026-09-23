<?php

it('serves health, categories, pages and sitemap', function () {
    $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('status', 'ok');
    $this->getJson('/api/v1/categories')->assertOk()->assertJsonPath('data.0.slug', 'governance-public-administration');
    $this->getJson('/api/v1/pages/terms-of-use')->assertOk()->assertJsonPath('data.requires_legal_review', true);
    $this->getJson('/api/v1/pages/about')->assertOk();
    $this->getJson('/api/v1/pages/does-not-exist')->assertNotFound();
    $pub = $this->publishedInvestigation();
    $this->getJson('/api/v1/sitemap')->assertOk()->assertJsonFragment(['path' => "/investigation/{$pub->public_id}/{$pub->slug}"]);
});

it('returns related investigations and tag/location taxonomies', function () {
    $inv = $this->investigator();
    $a = $this->publishedInvestigation($inv);
    $b = $this->publishedInvestigation($inv);
    $this->getJson("/api/v1/investigations/{$a->public_id}/related")->assertOk()->assertJsonPath('data.0.id', $b->public_id);
    $this->getJson('/api/v1/locations')->assertOk();
    $this->getJson('/api/v1/tags')->assertOk();
    $this->getJson("/api/v1/investigators/{$inv->slug}/investigations")->assertOk()->assertJsonCount(2, 'data');
});
