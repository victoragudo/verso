<?php

namespace App\Verso\Updates\Updater;

use App\Verso\Updates\Exceptions\UpdateFailed;
use SodiumException;

// Checks the sha256 sum of a package and, when a public key is set, its Ed25519 signature.
class PackageVerifier
{
    public function __construct(protected string $publicKey) {}

    // True when packages must be signed.
    public function requiresSignature(): bool
    {
        return trim($this->publicKey) !== '';
    }

    // The sum from a sha256 file. It may be "<hash>" or "<hash>  <file name>".
    public function checksumFrom(string $text): string
    {
        if (preg_match('/^\s*([a-f0-9]{64})\b/i', $text, $matches) !== 1) {
            throw UpdateFailed::because(__('The checksum file does not hold a sha256 sum.'));
        }

        return strtolower($matches[1]);
    }

    public function verifyChecksum(string $path, string $checksumText): void
    {
        $expected = $this->checksumFrom($checksumText);
        $actual = (string) hash_file('sha256', $path);

        if (! hash_equals($expected, $actual)) {
            throw UpdateFailed::because(__('The package does not match its sha256 sum. It may be broken or changed, so it was not installed.'));
        }
    }

    // Check a detached Ed25519 signature (base64 or raw bytes) of the whole package.
    public function verifySignature(string $path, string $signature): void
    {
        $key = $this->decode($this->publicKey, SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES);
        $signatureBytes = $this->decode($signature, SODIUM_CRYPTO_SIGN_BYTES);

        if ($key === null) {
            throw UpdateFailed::because(__('The configured update public key is not a valid Ed25519 key.'));
        }

        if ($signatureBytes === null) {
            throw UpdateFailed::because(__('The package signature is not a valid Ed25519 signature.'));
        }

        try {
            $valid = sodium_crypto_sign_verify_detached($signatureBytes, (string) file_get_contents($path), $key);
        } catch (SodiumException) {
            $valid = false;
        }

        if (! $valid) {
            throw UpdateFailed::because(__('The package signature does not match the configured public key, so it was not installed.'));
        }
    }

    // Accept base64 text or raw bytes of the exact length.
    /**
     * @param  positive-int  $length
     * @return non-empty-string|null
     */
    protected function decode(string $value, int $length): ?string
    {
        if ($value !== '' && strlen($value) === $length) {
            return $value;
        }

        $decoded = base64_decode(trim($value), true);

        return $decoded !== false && $decoded !== '' && strlen($decoded) === $length ? $decoded : null;
    }
}
