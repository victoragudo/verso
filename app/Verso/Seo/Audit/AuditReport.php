<?php

namespace App\Verso\Seo\Audit;

// The results of every check and the site score from 0 to 100.
final readonly class AuditReport
{
    /**
     * @param  array<string, CheckResult>  $results  check key => result
     * @param  array{good: int, ok: int, poor: int, none: int}  $ratings  published entries per light of the editor checks
     */
    public function __construct(
        public array $results,
        public string $checkedAt,
        public array $ratings = ['good' => 0, 'ok' => 0, 'poor' => 0, 'none' => 0],
    ) {}

    public function result(string $key): ?CheckResult
    {
        return $this->results[$key] ?? null;
    }

    // Each check has a weight from its severity. A check loses the share of things that have the issue.
    /** @param list<SeoCheck> $checks */
    public function score(array $checks): int
    {
        $total = 0;
        $lost = 0.0;

        foreach ($checks as $check) {
            $result = $this->results[$check->key()] ?? null;

            if ($result === null || $result->pending) {
                continue;
            }

            $weight = $check->severity()->weight();
            $total += $weight;
            $lost += $weight * $result->penalty();
        }

        return $total === 0 ? 100 : (int) round(100 * (1 - $lost / $total));
    }

    /** @return array{results: array<string, array<string, mixed>>, checkedAt: string, ratings: array{good: int, ok: int, poor: int, none: int}} */
    public function toArray(): array
    {
        return [
            'results' => array_map(fn (CheckResult $result): array => $result->toArray(), $this->results),
            'checkedAt' => $this->checkedAt,
            'ratings' => $this->ratings,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $results = [];

        foreach (is_array($data['results'] ?? null) ? $data['results'] : [] as $key => $result) {
            $results[(string) $key] = CheckResult::fromArray(is_array($result) ? $result : []);
        }

        $ratings = is_array($data['ratings'] ?? null) ? $data['ratings'] : [];

        return new self($results, is_string($data['checkedAt'] ?? null) ? $data['checkedAt'] : '', [
            'good' => (int) ($ratings['good'] ?? 0),
            'ok' => (int) ($ratings['ok'] ?? 0),
            'poor' => (int) ($ratings['poor'] ?? 0),
            'none' => (int) ($ratings['none'] ?? 0),
        ]);
    }
}
