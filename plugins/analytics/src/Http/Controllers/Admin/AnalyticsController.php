<?php

namespace Verso\Plugins\Analytics\Http\Controllers\Admin;

use App\Verso\Core\Enums\Role;
use App\Verso\Core\Support\OptionalRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Verso\Plugins\Analytics\Reports\AnalyticsReport;
use Verso\Plugins\Analytics\Support\AnalyticsSettings;

// The analytics page: stats for a date range and the privacy settings.
class AnalyticsController
{
    public function index(Request $request, AnalyticsReport $report, AnalyticsSettings $settings, OptionalRoles $roles): Response
    {
        $ranges = $this->ranges();
        $range = (int) $request->query('range', (string) config('analytics.default_range'));

        if (! in_array($range, $ranges, true)) {
            $range = in_array((int) config('analytics.default_range'), $ranges, true) ? (int) config('analytics.default_range') : $ranges[0];
        }

        return Inertia::render('admin/plugins/analytics/index', [
            'range' => $range,
            'ranges' => array_map(fn (int $days): array => [
                'days' => $days,
                'href' => route('admin.analytics.index', ['range' => $days], false),
            ], $ranges),
            'report' => $report->build($range),
            'settings' => $settings->toArray(),
            'roleOptions' => array_map(
                fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()],
                $roles->available(),
            ),
            'retentionLimits' => ['min' => $settings->minRetention(), 'max' => $settings->maxRetention()],
            'settingsUrl' => route('admin.analytics.settings.update', [], false),
        ]);
    }

    public function updateSettings(Request $request, AnalyticsSettings $settings): RedirectResponse
    {
        $data = $request->validate([
            'retention_days' => ['required', 'integer', 'min:'.$settings->minRetention(), 'max:'.$settings->maxRetention()],
            'ignore_roles' => ['present', 'array'],
            'ignore_roles.*' => ['string', 'distinct', Rule::enum(Role::class)],
        ]);

        $settings->update((int) $data['retention_days'], array_values(array_map(strval(...), (array) $data['ignore_roles'])));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('analytics::Analytics settings saved.')]);

        return back();
    }

    /**
     * @return non-empty-list<int>
     */
    protected function ranges(): array
    {
        $ranges = array_values(array_unique(array_filter(
            array_map(intval(...), (array) config('analytics.ranges')),
            fn (int $days): bool => $days > 0,
        )));
        sort($ranges);

        return $ranges === [] ? [max(1, (int) config('analytics.default_range'))] : $ranges;
    }
}
