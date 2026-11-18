<?php

namespace App\Verso\Extensions\Models;

use App\Models\User;
use App\Verso\Extensions\Enums\ExtensionKind;
use App\Verso\Extensions\Verification\Enums\RiskLevel;
use App\Verso\Extensions\Verification\Finding;
use App\Verso\Extensions\Verification\VerificationReport;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A stored verification report of one plugin or theme version.
 *
 * @property int $id
 * @property ExtensionKind $kind
 * @property string $slug
 * @property string $version
 * @property string $source
 * @property string|null $sha256
 * @property string|null $staging_token
 * @property RiskLevel $risk
 * @property int $score
 * @property list<array<string, mixed>> $findings
 * @property array<string, mixed>|null $details
 * @property int|null $user_id
 * @property CarbonInterface|null $installed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
class PluginVerification extends Model
{
    // Where the checked plugin came from.
    public const SOURCE_UPLOAD = 'upload';

    public const SOURCE_REGISTRY = 'registry';

    public const SOURCE_INSTALLED = 'installed';

    /** @var list<string> */
    protected $guarded = ['id'];

    /** @var array<string, mixed> */
    protected $attributes = ['kind' => 'plugin'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ExtensionKind::class,
            'risk' => RiskLevel::class,
            'score' => 'integer',
            'findings' => 'array',
            'details' => 'array',
            'installed_at' => 'datetime',
        ];
    }

    // Details key that lists the rules which stop the install, whatever the risk.
    public const BLOCKING = 'blocking';

    // Store a report, then delete the oldest reports of the plugin or theme beyond the configured limit.
    public static function record(VerificationReport $report, string $source, ?string $sha256 = null, ?string $token = null, ?int $userId = null, ExtensionKind $kind = ExtensionKind::Plugin): self
    {
        $verification = self::query()->create([
            'kind' => $kind,
            'slug' => $report->slug,
            'version' => $report->version,
            'source' => $source,
            'sha256' => $sha256,
            'staging_token' => $token,
            'risk' => $report->risk,
            'score' => $report->score,
            'findings' => array_map(fn (Finding $finding): array => $finding->toStoredArray(), $report->findings),
            'details' => $report->details,
            'user_id' => $userId,
        ]);

        self::prune($report->slug, $kind);

        return $verification;
    }

    // Keep the newest reports of a plugin or theme and the report of the installed version.
    public static function prune(string $slug, ExtensionKind $kind = ExtensionKind::Plugin): void
    {
        $keep = max(1, (int) config('verso.extensions.verification.reports_kept'));

        $ids = self::query()->where('kind', $kind)->where('slug', $slug)->whereNull('installed_at')->orderByDesc('id')->pluck('id')->all();

        self::query()->whereIn('id', array_slice($ids, $keep))->delete();
    }

    // The newest report of a staged upload, when the zip did not change since it was checked.
    public static function forStaged(string $token, string $sha256): ?self
    {
        return self::query()->where('staging_token', $token)->where('sha256', $sha256)->latest('id')->first();
    }

    // The newest report of an installed plugin or theme.
    public static function latestFor(string $slug, ExtensionKind $kind = ExtensionKind::Plugin): ?self
    {
        return self::query()->where('kind', $kind)->where('slug', $slug)->latest('id')->first();
    }

    /**
     * Risk level of the newest report of each plugin or theme, by slug.
     *
     * @return array<string, string>
     */
    public static function latestRisks(ExtensionKind $kind = ExtensionKind::Plugin): array
    {
        return self::query()
            ->whereIn('id', self::query()->selectRaw('max(id)')->where('kind', $kind)->groupBy('slug'))
            ->get(['slug', 'risk'])
            ->mapWithKeys(fn (self $verification): array => [$verification->slug => $verification->risk->value])
            ->all();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isHighRisk(): bool
    {
        return $this->risk === RiskLevel::High;
    }

    /**
     * Rules found in this report that stop the install, like missing theme views or a broken render.
     *
     * @return list<string>
     */
    public function blockingRules(): array
    {
        return array_values(array_map(strval(...), (array) ($this->details[self::BLOCKING] ?? [])));
    }

    // Why this plugin or theme cannot be installed yet, or null. High risk needs the slug typed again, or is blocked.
    // The "block high risk" setting only covers plugins, because themes cannot run code outside their views.
    public function installProblem(?string $typedSlug, bool $blockHighRisk): ?string
    {
        $isTheme = $this->kind === ExtensionKind::Theme;

        if ($this->blockingRules() !== []) {
            $rules = implode(', ', $this->blockingRules());

            return $isTheme
                ? __('This theme cannot be installed: :rules. Fix the findings and upload it again.', ['rules' => $rules])
                : __('This plugin cannot be installed: :rules. Fix the findings and upload it again.', ['rules' => $rules]);
        }

        if (! $this->isHighRisk()) {
            return null;
        }

        if ($blockHighRisk && ! $isTheme) {
            return __('This plugin is high risk, and this site blocks high risk plugins.');
        }

        if (trim((string) $typedSlug) === $this->slug) {
            return null;
        }

        return $isTheme
            ? __('This theme is high risk. Type ":slug" to confirm the install.', ['slug' => $this->slug])
            : __('This plugin is high risk. Type ":slug" to confirm the install.', ['slug' => $this->slug]);
    }

    // Mark this report as the one of the installed version.
    public function markInstalled(): void
    {
        self::query()->where('kind', $this->kind)->where('slug', $this->slug)->whereKeyNot($this->id)->update(['installed_at' => null]);
        $this->forceFill(['installed_at' => now(), 'staging_token' => null])->save();
    }

    /**
     * The report back as a value object.
     */
    public function report(): VerificationReport
    {
        return new VerificationReport(
            slug: $this->slug,
            version: $this->version,
            findings: array_map(fn (array $finding): Finding => Finding::fromArray($finding), $this->findings),
            score: $this->score,
            risk: $this->risk,
            details: $this->details ?? [],
        );
    }

    /**
     * Data for the admin screens.
     *
     * @return array<string, mixed>
     */
    public function toReportArray(): array
    {
        $report = $this->report();

        return [
            ...$report->toArray(),
            'findings' => array_map(fn (Finding $finding): array => $finding->toDisplayArray(), $report->findings),
            'id' => $this->id,
            'kind' => $this->kind->value,
            'blocking' => $this->blockingRules(),
            'source' => $this->source,
            'checkedAt' => $this->created_at?->toIso8601String(),
            'installedAt' => $this->installed_at?->toIso8601String(),
        ];
    }
}
