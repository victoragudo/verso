<?php

namespace App\Verso\Core\Admin;

use App\Models\User;

// Collects the numbers and short lists that modules show on the admin home page.
class Dashboard
{
    /** @var array<string, DashboardStat> */
    protected array $stats = [];

    /** @var array<string, DashboardPanel> */
    protected array $panels = [];

    /** @var array<string, DashboardNotice> */
    protected array $notices = [];

    public function addStat(DashboardStat $stat): void
    {
        $this->stats[$stat->key] = $stat;
    }

    // Add a panel. A panel with the same key replaces the old one.
    public function addPanel(DashboardPanel $panel): void
    {
        $this->panels[$panel->key] = $panel;
    }

    // Add a notice. A notice with the same key replaces the old one.
    public function addNotice(DashboardNotice $notice): void
    {
        $this->notices[$notice->key] = $notice;
    }

    /**
     * The notices this user can see that have something to say, sorted.
     *
     * @return list<array{key: string, icon: string|null, title: string, description: string|null, href: string|null, action: string|null, tone: string}>
     */
    public function noticesFor(User $user): array
    {
        $notices = array_filter($this->notices, fn (DashboardNotice $notice): bool => $notice->visibleTo($user));

        usort($notices, fn (DashboardNotice $a, DashboardNotice $b): int => $a->order <=> $b->order);

        return array_values(array_filter(array_map(fn (DashboardNotice $notice): ?array => $notice->toArray($user), $notices)));
    }

    /**
     * The stats this user can see, sorted and counted.
     *
     * @return list<array{key: string, label: string, value: int, href: string|null}>
     */
    public function statsFor(User $user): array
    {
        $stats = array_filter($this->stats, fn (DashboardStat $stat): bool => $stat->visibleTo($user));

        usort($stats, fn (DashboardStat $a, DashboardStat $b): int => $a->order <=> $b->order);

        return array_map(fn (DashboardStat $stat): array => $stat->toArray(), $stats);
    }

    /**
     * The panels this user can see, sorted and filled.
     *
     * @return list<array{key: string, label: string, icon: string, emptyText: string, href: string|null, items: list<array{key: string, title: string, meta: string|null, href: string|null, status: string|null}>}>
     */
    public function panelsFor(User $user): array
    {
        $panels = array_filter($this->panels, fn (DashboardPanel $panel): bool => $panel->visibleTo($user));

        usort($panels, fn (DashboardPanel $a, DashboardPanel $b): int => $a->order <=> $b->order);

        return array_map(fn (DashboardPanel $panel): array => $panel->toArray($user), $panels);
    }
}
