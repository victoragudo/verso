<?php

namespace App\Verso\Core\Admin;

use App\Models\User;
use App\Verso\Core\Settings\Settings;
use Illuminate\Support\Carbon;

// Marks sidebar items from newly enabled plugins as "New" until the user opens them.
class NavNewBadges
{
    // Setting that holds when each plugin was enabled: slug => ISO 8601 time.
    public const SETTING = 'plugins.enabled_at';

    // Plugin parent items only hold links. The UI shows a dot on them when a child is new.
    protected const GROUP_PREFIX = 'plugin.';

    public function __construct(protected Settings $settings) {}

    // Remember that a plugin was just enabled.
    public function pluginEnabled(string $slug): void
    {
        $times = $this->enabledTimes();
        $times[$slug] = now()->toIso8601String();

        $this->settings->set(self::SETTING, $times);
    }

    /**
     * Add "isNew" to each nav row. The item of the current page counts as opened.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function apply(?User $user, array $rows, string $currentPath): array
    {
        if ($user === null || $rows === []) {
            return $rows;
        }

        $fresh = $this->freshPlugins();
        $preferences = AdminPreferences::of($user);
        $active = $fresh === [] ? null : $this->activeKey($rows, $currentPath);

        return array_map(function (array $row) use ($fresh, $preferences, $active): array {
            $plugin = $row['plugin'] ?? null;
            $isNew = is_string($plugin)
                && isset($fresh[$plugin])
                && ! str_starts_with((string) $row['key'], self::GROUP_PREFIX)
                && ! $preferences->hasSeen((string) $row['key']);

            if ($isNew && $row['key'] === $active) {
                $preferences->markSeen((string) $row['key']);
                $isNew = false;
            }

            return [...$row, 'isNew' => $isNew];
        }, $rows);
    }

    /**
     * The key of the item whose link matches the path best: the same path, or the longest parent path.
     * On a tie the later item wins, so a child beats the parent item that links to it.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function activeKey(array $rows, string $currentPath): ?string
    {
        $current = $this->normalize($currentPath);
        $best = null;
        $bestLength = -1;

        foreach ($rows as $row) {
            // Links to a part of a page (#invitations) never mark the page itself.
            if (str_contains((string) ($row['href'] ?? ''), '#')) {
                continue;
            }

            $path = $this->normalize((string) ($row['href'] ?? ''));
            $matches = $current === $path
                || (($row['group'] ?? null) !== NavGroup::Dashboard->value && str_starts_with($current, $path.'/'));

            if ($matches && strlen($path) >= $bestLength) {
                $best = (string) $row['key'];
                $bestLength = strlen($path);
            }
        }

        return $best;
    }

    /**
     * Plugins enabled within the badge time.
     *
     * @return array<string, true>
     */
    protected function freshPlugins(): array
    {
        $days = (int) config('verso.admin.nav.new_badge_days');

        if ($days <= 0) {
            return [];
        }

        $since = now()->subDays($days);
        $fresh = [];

        foreach ($this->enabledTimes() as $slug => $time) {
            if (Carbon::parse($time)->greaterThan($since)) {
                $fresh[$slug] = true;
            }
        }

        return $fresh;
    }

    /**
     * @return array<string, string>
     */
    protected function enabledTimes(): array
    {
        $value = $this->settings->get(self::SETTING, []);

        return is_array($value) ? array_filter($value, fn (mixed $time, mixed $slug): bool => is_string($slug) && is_string($time), ARRAY_FILTER_USE_BOTH) : [];
    }

    protected function normalize(string $href): string
    {
        $path = '/'.ltrim((string) preg_replace('/[?#].*$/', '', $href), '/');

        return rtrim($path, '/') === '' ? '/' : rtrim($path, '/');
    }
}
