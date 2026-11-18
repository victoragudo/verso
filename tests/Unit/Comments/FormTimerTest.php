<?php

use App\Verso\Comments\Support\FormTimer;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    $this->timer = new FormTimer(minSeconds: 3, maxSeconds: 3600);
});

it('measures the time since the form was shown', function () {
    $this->travelTo(now()->startOfMinute());
    $token = $this->timer->token();

    $this->travel(10)->seconds();

    expect($this->timer->elapsed($token))->toBe(10)
        ->and($this->timer->isTooFast($token))->toBeFalse()
        ->and($this->timer->isExpired($token))->toBeFalse();
});

it('catches forms sent too fast', function () {
    $token = $this->timer->token();

    expect($this->timer->isTooFast($token))->toBeTrue();
});

it('expires old forms', function () {
    $token = $this->timer->token();
    $this->travel(2)->hours();

    expect($this->timer->isExpired($token))->toBeTrue();
});

it('treats missing or broken tokens as expired', function () {
    expect($this->timer->elapsed(null))->toBeNull()
        ->and($this->timer->elapsed(''))->toBeNull()
        ->and($this->timer->elapsed('not-encrypted'))->toBeNull()
        ->and($this->timer->elapsed(Crypt::encryptString('yesterday')))->toBeNull()
        ->and($this->timer->isExpired('not-encrypted'))->toBeTrue()
        ->and($this->timer->isTooFast('not-encrypted'))->toBeFalse();
});
