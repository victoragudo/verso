<?php

namespace Verso\Plugins\Analytics\Support;

use App\Models\User;
use App\Verso\Core\Enums\Role;
use App\Verso\Core\Settings\Settings;

// The analytics options people can change in the admin. Defaults come from the plugin config.
class AnalyticsSettings
{
    public const RETENTION_DAYS = 'analytics.retention_days';

    public const IGNORE_ROLES = 'analytics.ignore_roles';

    // Older on or off switch for admins only. Read when the role list was never saved.
    public const LEGACY_IGNORE_ADMINS = 'analytics.ignore_admins';

    public function __construct(protected Settings $settings) {}

    // Days raw events are kept before they are deleted. Daily totals are kept forever.
    public function retentionDays(): int
    {
        $value = (int) $this->settings->get(self::RETENTION_DAYS, config('analytics.defaults.retention_days'));

        return max($this->minRetention(), min($this->maxRetention(), $value));
    }

    /**
     * Roles whose logged-in visits are not counted. Unknown role names are dropped.
     *
     * @return list<string>
     */
    public function ignoredRoles(): array
    {
        $saved = $this->settings->get(self::IGNORE_ROLES);

        if (! is_array($saved)) {
            $legacy = $this->settings->get(self::LEGACY_IGNORE_ADMINS);
            $saved = $legacy === null ? (array) config('analytics.defaults.ignore_roles') : ($legacy ? [Role::Admin->value] : []);
        }

        return $this->knownRoles($saved);
    }

    // True when this logged-in user's visits are not counted.
    public function ignores(?User $user): bool
    {
        return $user instanceof User && array_intersect($user->roleSlugs(), $this->ignoredRoles()) !== [];
    }

    /**
     * @param  list<string>  $ignoredRoles
     */
    public function update(int $retentionDays, array $ignoredRoles): void
    {
        $this->settings->setMany([
            self::RETENTION_DAYS => $retentionDays,
            self::IGNORE_ROLES => $this->knownRoles($ignoredRoles),
        ]);
    }

    /**
     * @param  array<mixed>  $roles
     * @return list<string>
     */
    protected function knownRoles(array $roles): array
    {
        $known = [];

        foreach ($roles as $role) {
            $case = is_string($role) ? Role::tryFrom($role) : null;

            if ($case !== null && ! in_array($case->value, $known, true)) {
                $known[] = $case->value;
            }
        }

        return $known;
    }

    public function minRetention(): int
    {
        return max(1, (int) config('analytics.retention_limits.min'));
    }

    public function maxRetention(): int
    {
        return max($this->minRetention(), (int) config('analytics.retention_limits.max'));
    }

    /**
     * @return array{retention_days: int, ignore_roles: list<string>}
     */
    public function toArray(): array
    {
        return [
            'retention_days' => $this->retentionDays(),
            'ignore_roles' => $this->ignoredRoles(),
        ];
    }
}
