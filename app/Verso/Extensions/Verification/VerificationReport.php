<?php

namespace App\Verso\Extensions\Verification;

use App\Verso\Extensions\Verification\Enums\RiskLevel;
use App\Verso\Extensions\Verification\Enums\Severity;

// The result of checking a plugin: findings, a score and the risk level.
final readonly class VerificationReport
{
    /**
     * @param  list<Finding>  $findings  sorted with the most serious first
     * @param  array<string, mixed>  $details  declared and used permissions, hosts and the sandbox run
     */
    public function __construct(
        public string $slug,
        public string $version,
        public array $findings,
        public int $score,
        public RiskLevel $risk,
        public array $details = [],
    ) {}

    /**
     * Sort the findings, add up the score and pick the risk level.
     *
     * @param  list<Finding>  $findings
     * @param  array<string, mixed>  $details
     */
    public static function make(string $slug, string $version, array $findings, array $details = []): self
    {
        usort($findings, fn (Finding $a, Finding $b): int => [$b->severity->rank(), $a->file ?? '', $a->line ?? 0, $a->rule]
            <=> [$a->severity->rank(), $b->file ?? '', $b->line ?? 0, $b->rule]);

        $score = self::score($findings);
        $hasHigh = array_any($findings, fn (Finding $finding): bool => $finding->severity === Severity::High);

        return new self($slug, $version, $findings, $score, RiskLevel::fromScore($score, $hasHigh), $details);
    }

    /**
     * Code rules count once per file, so one noisy file cannot hide everything else.
     * Other rules (permissions, compatibility, sandbox) count once per distinct problem.
     *
     * @param  list<Finding>  $findings
     */
    public static function score(array $findings): int
    {
        $counted = [];

        foreach ($findings as $finding) {
            $perFile = preg_match('/^(php|js|blade)\./', $finding->rule) === 1;
            $key = $finding->rule.'|'.($finding->file ?? '').($perFile ? '' : '|'.$finding->message);
            $counted[$key] = max($counted[$key] ?? 0, $finding->severity->weight());
        }

        return array_sum($counted);
    }

    /**
     * Number of findings for each severity.
     *
     * @return array<string, int>
     */
    public function counts(): array
    {
        $counts = array_fill_keys(array_column(Severity::cases(), 'value'), 0);

        foreach ($this->findings as $finding) {
            $counts[$finding->severity->value]++;
        }

        return $counts;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'version' => $this->version,
            'risk' => $this->risk->value,
            'score' => $this->score,
            'counts' => $this->counts(),
            'findings' => array_map(fn (Finding $finding): array => $finding->toArray(), $this->findings),
            'details' => $this->details,
        ];
    }
}
