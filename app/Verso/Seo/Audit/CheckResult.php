<?php

namespace App\Verso\Seo\Audit;

use App\Verso\I18n\Translate;

// What one check found: how many things have the issue, out of how many, and the list to fix.
final readonly class CheckResult
{
    /**
     * @param  list<IssueItem>  $items
     * @param  array<string, string|int|float>  $summaryValues
     */
    public function __construct(
        public int $affected,
        public int $total,
        public array $items = [],
        public bool $pending = false,
        public string $summary = '',
        public array $summaryValues = [],
    ) {}

    public static function passed(int $total = 1): self
    {
        return new self(0, max(1, $total));
    }

    // A site wide problem, like a switch that is off.
    /**
     * @param  array<string, string|int|float>  $values
     */
    public static function failed(string $message, array $values = []): self
    {
        return new self(1, 1, summary: $message, summaryValues: $values);
    }

    // The check needs data that is not there yet, like the first link check.
    /**
     * @param  array<string, string|int|float>  $values
     */
    public static function pending(string $message, array $values = []): self
    {
        return new self(0, 0, pending: true, summary: $message, summaryValues: $values);
    }

    /**
     * @param  list<IssueItem>  $items
     * @param  array<string, string|int|float>  $summaryValues
     */
    public static function of(array $items, int $total, int $maxItems, string $summary = '', array $summaryValues = []): self
    {
        return new self(count($items), max($total, count($items)), array_slice($items, 0, max(0, $maxItems)), summary: $summary, summaryValues: $summaryValues);
    }

    public function hasIssue(): bool
    {
        return $this->affected > 0;
    }

    // Share of the check weight that is lost, from 0 to 1.
    public function penalty(): float
    {
        if ($this->pending || $this->total <= 0) {
            return 0.0;
        }

        return min(1.0, $this->affected / $this->total);
    }

    public function displaySummary(): string
    {
        return $this->summary === '' ? '' : Translate::icu($this->summary, $this->summaryValues);
    }

    /** @return array{affected: int, total: int, items: list<array<string, mixed>>, pending: bool, summary: string, summaryValues: array<string, string|int|float>} */
    public function toArray(): array
    {
        return [
            'affected' => $this->affected,
            'total' => $this->total,
            'items' => array_map(fn (IssueItem $item): array => $item->toArray(), $this->items),
            'pending' => $this->pending,
            'summary' => $this->summary,
            'summaryValues' => $this->summaryValues,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];

        return new self(
            affected: (int) ($data['affected'] ?? 0),
            total: (int) ($data['total'] ?? 0),
            items: array_values(array_map(fn (mixed $item): IssueItem => IssueItem::fromArray(is_array($item) ? $item : []), $items)),
            pending: (bool) ($data['pending'] ?? false),
            summary: is_string($data['summary'] ?? null) ? $data['summary'] : '',
            summaryValues: self::values($data['summaryValues'] ?? null),
        );
    }

    /** @return array<string, string|int|float> */
    public static function values(mixed $values): array
    {
        $clean = [];

        foreach (is_array($values) ? $values : [] as $name => $value) {
            if (is_string($value) || is_int($value) || is_float($value)) {
                $clean[(string) $name] = $value;
            }
        }

        return $clean;
    }
}
