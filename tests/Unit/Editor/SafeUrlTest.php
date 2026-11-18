<?php

use App\Verso\Editor\Rules\SafeUrl;
use Illuminate\Support\Facades\Validator;

it('passes safe and empty urls', function (mixed $url) {
    expect(Validator::make(['src' => $url], ['src' => [new SafeUrl]])->passes())->toBeTrue();
})->with(['https://example.org/a.jpg', '/storage/a.jpg', 'mailto:ana@example.org', '', null]);

it('fails dangerous urls with a clear message', function (string $url) {
    $validator = Validator::make(['src' => $url], ['src' => [new SafeUrl]]);

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->first('src'))->toBe('The src must be a safe web address.');
})->with(['javascript:alert(1)', 'data:image/png;base64,AAAA', 'vbscript:x']);
