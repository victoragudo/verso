<?php

namespace Verso\Plugins\Analytics\Support;

use Illuminate\Http\Request;

// Everything the collector receives for one page view, before it is checked and cleaned.
final readonly class PageView
{
    public function __construct(
        public string $path,
        public ?string $referrerHost,
        public ?string $device,
        public string $ip,
        public string $userAgent,
        public string $site,
        public bool $optedOut = false,
    ) {}

    // Reads the beacon fields (p, r, w) and the privacy headers (DNT, Sec-GPC) from a request.
    public static function fromRequest(Request $request): self
    {
        return new self(
            path: self::text($request->input('p')),
            referrerHost: self::text($request->input('r')) ?: null,
            device: self::text($request->input('w')) ?: null,
            ip: (string) $request->ip(),
            userAgent: (string) $request->userAgent(),
            site: $request->getHost(),
            optedOut: $request->header('DNT') === '1' || $request->header('Sec-GPC') === '1',
        );
    }

    protected static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
