<?php

namespace Verso\Plugins\Memberships;

use App\Verso\Content\Models\Entry;
use App\Verso\Plugins\Filters\ApiEntryFilter;
use App\Verso\Plugins\Filters\EntryContentFilter;
use App\Verso\Plugins\PluginServiceProvider;
use App\Verso\System\Config\ConfigParameter;
use App\Verso\System\Config\Unit;
use Illuminate\Console\Scheduling\Schedule;
use Verso\Plugins\Memberships\Blocks\MembersOnlyBlock;
use Verso\Plugins\Memberships\Console\ExpireMembershipsCommand;
use Verso\Plugins\Memberships\Panels\MembershipPanel;

// Boots the Memberships plugin: plans, members, restricted entries and the members only block.
class MembershipsServiceProvider extends PluginServiceProvider
{
    // The block type stored in entries.
    public const BLOCK = 'members-only';

    // Restricted content is replaced after every other filter has run.
    public const FILTER_PRIORITY = 1000;

    public function register(): void
    {
        $this->mergeConfigFrom($this->path('config/memberships.php'), 'memberships');
        $this->registerSystemSettings('Memberships', 'memberships', ...$this->systemSettings());
        $this->app->singleton(MembershipAccess::class);
    }

    public function boot(): void
    {
        $this->loadPluginMigrations();
        $this->loadPluginViews('memberships');
        $this->loadAdminRoutes('routes/admin.php');

        $access = $this->app->make(MembershipAccess::class);

        $this->registerBlock(new MembersOnlyBlock($access));
        $this->registerEntryPanel(new MembershipPanel);
        $this->registerFilters($access);
        $this->registerSchedule();

        if ($this->app->runningInConsole()) {
            $this->commands([ExpireMembershipsCommand::class]);
        }
    }

    // Hide restricted entries in the theme, the feed and the API.
    protected function registerFilters(MembershipAccess $access): void
    {
        $this->addFilter(EntryContentFilter::class, function (string $html, Entry $entry) use ($access): string {
            if ($access->canView(auth()->user(), $entry)) {
                return $html;
            }

            $excerpt = trim((string) $entry->excerpt);

            return ($excerpt !== '' ? '<p>'.e($excerpt).'</p>' : '').$access->teaser();
        }, self::FILTER_PRIORITY);

        $this->addFilter(ApiEntryFilter::class, function (array $data, Entry $entry) use ($access): array {
            $user = auth()->user();

            if (array_key_exists('blocks', $data) && is_array($data['blocks'])) {
                $data['blocks'] = $access->canView($user, $entry) ? $access->stripBlocks($data['blocks'], $user) : [];
            }

            // Custom fields can hold members only content too.
            if (array_key_exists('fields', $data) && ! $access->canView($user, $entry)) {
                $data['fields'] = (object) [];
            }

            return [...$data, 'members_only' => $access->isRestricted($entry)];
        }, self::FILTER_PRIORITY);
    }

    protected function registerSchedule(): void
    {
        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('memberships:expire')->dailyAt((string) config('memberships.expire_at'))->withoutOverlapping();
        });
    }

    // Limits of this plugin that admins can change in System settings.
    /** @return list<ConfigParameter> */
    protected function systemSettings(): array
    {
        $group = $this->systemGroup();

        return [
            new ConfigParameter('memberships.per_page', $group, 'Members per page', 'Members shown on one admin page.', unit: Unit::Items, min: 5, max: 200, env: 'MEMBERSHIPS_PER_PAGE'),
        ];
    }
}
