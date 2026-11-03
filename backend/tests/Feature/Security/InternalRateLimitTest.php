<?php

it('exempts server-side render requests carrying the internal token from the per-IP api limit', function () {
    config(['onushondhan.internal_api_token' => 'test-internal-token-0123456789']);

    for ($i = 0; $i < 241; $i++) {
        $last = $this->withHeaders(['X-Internal-Token' => 'test-internal-token-0123456789'])->getJson('/api/v1/categories');
    }
    $last->assertOk();

    // Without (or with a wrong) token the shared per-IP limit still applies.
    for ($i = 0; $i < 240; $i++) {
        $this->withHeaders(['X-Internal-Token' => 'wrong'])->getJson('/api/v1/categories');
    }
    $this->withHeaders(['X-Internal-Token' => 'wrong'])->getJson('/api/v1/categories')->assertStatus(429);
});

it('never exempts anyone when no internal token is configured', function () {
    config(['onushondhan.internal_api_token' => '']);

    for ($i = 0; $i < 240; $i++) {
        $this->withHeaders(['X-Internal-Token' => ''])->getJson('/api/v1/categories');
    }
    $this->withHeaders(['X-Internal-Token' => ''])->getJson('/api/v1/categories')->assertStatus(429);
});
