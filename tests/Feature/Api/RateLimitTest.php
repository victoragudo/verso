<?php

it('limits requests per minute from config', function () {
    config(['verso.api.rate_limit' => 3]);

    foreach (range(1, 3) as $attempt) {
        $this->getJson('/api/v1/entries/post')->assertOk()->assertHeader('X-RateLimit-Limit', '3');
    }

    $this->getJson('/api/v1/entries/post')->assertTooManyRequests();
});

it('counts each IP address on its own', function () {
    config(['verso.api.rate_limit' => 1]);

    $this->getJson('/api/v1/entries/post', ['REMOTE_ADDR' => '203.0.113.7'])->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])->getJson('/api/v1/entries/post')->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.20'])->getJson('/api/v1/entries/post')->assertTooManyRequests();
});

it('answers errors as JSON', function () {
    $this->get('/api/v1/entries/recipe')->assertNotFound()->assertHeader('Content-Type', 'application/json');
});
