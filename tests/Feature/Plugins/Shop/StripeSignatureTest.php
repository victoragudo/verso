<?php

use Tests\Support\ShopFixtures;
use Verso\Plugins\Shop\Payments\Stripe\StripeSignature;

beforeEach(function () {
    ShopFixtures::autoload();
    $this->signature = new StripeSignature(tolerance: 300);
    $this->payload = '{"id":"evt_1QxHarbour","type":"checkout.session.completed"}';
    $this->secret = 'whsec_9fK2harbour';
    $this->now = 1_763_380_000;
});

it('accepts a header signed with the secret', function () {
    $header = StripeSignature::header($this->payload, $this->secret, $this->now);

    expect($this->signature->isValid($this->payload, $header, $this->secret, $this->now))->toBeTrue();
});

it('matches the HMAC that Stripe documents', function () {
    expect(StripeSignature::sign('{"ok":true}', 1_700_000_000, 'whsec_test'))
        ->toBe(hash_hmac('sha256', '1700000000.{"ok":true}', 'whsec_test'));
});

it('accepts any of several v1 signatures', function () {
    $good = StripeSignature::sign($this->payload, $this->now, $this->secret);
    $header = "t={$this->now},v1=".str_repeat('0', 64).",v1={$good},v0=legacy";

    expect($this->signature->isValid($this->payload, $header, $this->secret, $this->now))->toBeTrue();
});

it('refuses a changed payload', function () {
    $header = StripeSignature::header($this->payload, $this->secret, $this->now);

    expect($this->signature->isValid(str_replace('completed', 'expired', $this->payload), $header, $this->secret, $this->now))->toBeFalse();
});

it('refuses another secret', function () {
    $header = StripeSignature::header($this->payload, 'whsec_someone_else', $this->now);

    expect($this->signature->isValid($this->payload, $header, $this->secret, $this->now))->toBeFalse();
});

it('refuses old and future signatures outside the tolerance', function () {
    $old = StripeSignature::header($this->payload, $this->secret, $this->now - 301);
    $future = StripeSignature::header($this->payload, $this->secret, $this->now + 301);
    $edge = StripeSignature::header($this->payload, $this->secret, $this->now - 300);

    expect($this->signature->isValid($this->payload, $old, $this->secret, $this->now))->toBeFalse()
        ->and($this->signature->isValid($this->payload, $future, $this->secret, $this->now))->toBeFalse()
        ->and($this->signature->isValid($this->payload, $edge, $this->secret, $this->now))->toBeTrue();
});

it('skips the time check when the tolerance is zero', function () {
    $header = StripeSignature::header($this->payload, $this->secret, 1_000);

    expect((new StripeSignature(0))->isValid($this->payload, $header, $this->secret, $this->now))->toBeTrue();
});

it('refuses broken headers', function (?string $header) {
    expect($this->signature->isValid($this->payload, $header, $this->secret, $this->now))->toBeFalse();
})->with([
    'missing' => [null],
    'empty' => [''],
    'no time' => ['v1=abc123'],
    'no signature' => ['t=1763380000'],
    'text time' => ['t=yesterday,v1=abc123'],
    'only v0' => ['t=1763380000,v0=abc123'],
]);

it('refuses everything without a secret', function () {
    $header = StripeSignature::header($this->payload, '', $this->now);

    expect($this->signature->isValid($this->payload, $header, '', $this->now))->toBeFalse();
});
