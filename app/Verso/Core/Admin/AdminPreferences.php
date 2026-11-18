<?php

namespace App\Verso\Core\Admin;

use App\Models\User;

// Admin choices of one user, saved in users.admin_preferences: opened sidebar items, list views, simple mode and closed cards.
final class AdminPreferences
{
    public const DENSITIES = ['comfortable', 'compact'];

    // List keys are like "entries.post" or "comments".
    public const LIST_KEY_PATTERN = '/^[a-z0-9][a-z0-9._-]{0,79}$/';

    public function __construct(protected User $user) {}

    public static function of(User $user): self
    {
        return new self($user);
    }

    /**
     * Keys of the sidebar items the user has opened.
     *
     * @return list<string>
     */
    public function seenNavKeys(): array
    {
        $seen = $this->data()['nav_seen'] ?? [];

        return array_values(array_filter(is_array($seen) ? $seen : [], 'is_string'));
    }

    public function hasSeen(string $key): bool
    {
        return in_array($key, $this->seenNavKeys(), true);
    }

    // Remember that the user opened an item. Only the newest keys are kept.
    public function markSeen(string $key): void
    {
        if ($this->hasSeen($key)) {
            return;
        }

        $seen = [...$this->seenNavKeys(), $key];
        $max = max(1, (int) config('verso.admin.nav.max_seen'));

        $this->save(['nav_seen' => array_slice($seen, -$max)]);
    }

    /**
     * The view of one admin list: chosen columns (null means the defaults) and density.
     *
     * @return array{columns: list<string>|null, density: string|null}
     */
    public function listView(string $list): array
    {
        $lists = $this->data()['lists'] ?? [];
        $view = is_array($lists) && is_array($lists[$list] ?? null) ? $lists[$list] : [];
        $columns = $view['columns'] ?? null;
        $density = $view['density'] ?? null;

        return [
            'columns' => is_array($columns) ? array_values(array_filter($columns, 'is_string')) : null,
            'density' => in_array($density, self::DENSITIES, true) ? $density : null,
        ];
    }

    /**
     * The view of one list for the page props, with its key, as the UI expects it.
     *
     * @return array{key: string, columns: list<string>|null, density: string|null}
     */
    public function listViewProps(string $list): array
    {
        return ['key' => $list, ...$this->listView($list)];
    }

    /**
     * Save the view of one admin list. Null columns go back to the defaults.
     *
     * @param  list<string>|null  $columns
     */
    public function saveListView(string $list, ?array $columns, ?string $density): void
    {
        $lists = $this->data()['lists'] ?? [];
        $lists = is_array($lists) ? $lists : [];

        $lists[$list] = array_filter([
            'columns' => $columns === null ? null : array_values(array_unique($columns)),
            'density' => in_array($density, self::DENSITIES, true) ? $density : null,
        ], fn (mixed $value): bool => $value !== null);

        $this->save(['lists' => $lists]);
    }

    // The simple admin mode the user picked, or null when the user never picked one (the site default applies).
    public function simpleMode(): ?bool
    {
        $value = $this->data()['simple_mode'] ?? null;

        return is_bool($value) ? $value : null;
    }

    public function setSimpleMode(bool $enabled): void
    {
        $this->save(['simple_mode' => $enabled]);
    }

    /**
     * Keys of the cards the user closed, like the launch checklist.
     *
     * @return list<string>
     */
    public function dismissed(): array
    {
        $keys = $this->data()['dismissed'] ?? [];

        return array_values(array_filter(is_array($keys) ? $keys : [], 'is_string'));
    }

    public function hasDismissed(string $key): bool
    {
        return in_array($key, $this->dismissed(), true);
    }

    public function dismiss(string $key): void
    {
        if (! $this->hasDismissed($key)) {
            $this->save(['dismissed' => [...$this->dismissed(), $key]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function data(): array
    {
        return $this->user->admin_preferences ?? [];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    protected function save(array $changes): void
    {
        $this->user->admin_preferences = [...$this->data(), ...$changes];
        $this->user->save();
    }
}
