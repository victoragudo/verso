<?php

use App\Verso\Updates\Exceptions\UpdateFailed;
use App\Verso\Updates\Updater\PackageVerifier;

beforeEach(function () {
    $this->zip = tempnam(sys_get_temp_dir(), 'verso-zip');
    file_put_contents($this->zip, 'PK release bytes for Verso 1.2.0');
    $this->keys = sodium_crypto_sign_keypair();
});

afterEach(function () {
    @unlink($this->zip);
});

it('reads a sum from a plain or sha256sum style checksum file', function () {
    $verifier = new PackageVerifier('');
    $sum = hash('sha256', 'release');

    expect($verifier->checksumFrom($sum))->toBe($sum)
        ->and($verifier->checksumFrom(strtoupper($sum).'  verso-1.2.0.zip'."\n"))->toBe($sum);
});

it('refuses a checksum file without a sum', function () {
    (new PackageVerifier(''))->checksumFrom('<html>Not found</html>');
})->throws(UpdateFailed::class, 'does not hold a sha256 sum');

it('accepts a package that matches its checksum', function () {
    (new PackageVerifier(''))->verifyChecksum($this->zip, hash_file('sha256', $this->zip).'  verso-1.2.0.zip');

    expect(true)->toBeTrue();
});

it('refuses a package that does not match its checksum', function () {
    (new PackageVerifier(''))->verifyChecksum($this->zip, hash('sha256', 'another package'));
})->throws(UpdateFailed::class, 'does not match its sha256 sum');

it('only requires a signature when a public key is set', function () {
    expect((new PackageVerifier(''))->requiresSignature())->toBeFalse()
        ->and((new PackageVerifier(base64_encode(sodium_crypto_sign_publickey($this->keys))))->requiresSignature())->toBeTrue();
});

it('accepts a valid Ed25519 signature in base64 or raw bytes', function () {
    $verifier = new PackageVerifier(base64_encode(sodium_crypto_sign_publickey($this->keys)));
    $signature = sodium_crypto_sign_detached((string) file_get_contents($this->zip), sodium_crypto_sign_secretkey($this->keys));

    $verifier->verifySignature($this->zip, base64_encode($signature)."\n");
    $verifier->verifySignature($this->zip, $signature);

    expect(true)->toBeTrue();
});

it('refuses a signature made with another key', function () {
    $other = sodium_crypto_sign_keypair();
    $verifier = new PackageVerifier(base64_encode(sodium_crypto_sign_publickey($this->keys)));
    $signature = sodium_crypto_sign_detached((string) file_get_contents($this->zip), sodium_crypto_sign_secretkey($other));

    $verifier->verifySignature($this->zip, base64_encode($signature));
})->throws(UpdateFailed::class, 'signature does not match');

it('refuses a signature of a changed package', function () {
    $verifier = new PackageVerifier(base64_encode(sodium_crypto_sign_publickey($this->keys)));
    $signature = sodium_crypto_sign_detached((string) file_get_contents($this->zip), sodium_crypto_sign_secretkey($this->keys));
    file_put_contents($this->zip, 'PK release bytes for Verso 1.2.0 with a backdoor');

    $verifier->verifySignature($this->zip, base64_encode($signature));
})->throws(UpdateFailed::class, 'signature does not match');

it('refuses a broken signature or public key', function (string $key, string $signature, string $message) {
    expect(fn () => (new PackageVerifier($key))->verifySignature($this->zip, $signature))
        ->toThrow(UpdateFailed::class, $message);
})->with([
    'short key' => [base64_encode('too short'), base64_encode(str_repeat('s', 64)), 'not a valid Ed25519 key'],
    'bad signature' => [base64_encode(str_repeat('k', 32)), 'not-base64-%%%', 'not a valid Ed25519 signature'],
]);
