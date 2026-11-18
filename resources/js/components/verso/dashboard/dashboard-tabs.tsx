import { usePage } from '@inertiajs/react';
import { activeNavKey } from '@/components/verso/shell/nav-groups';
import { FilterTabs } from '@/components/verso/ui';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { t } from '@/lib/i18n';

const DASHBOARD_KEY = 'dashboard';

// Tabs on top of the dashboard: Overview plus the pages plugins put there, like Analytics.
// Nothing is shown when no plugin adds a dashboard page.
export function DashboardTabs() {
    const { versoNav } = usePage().props;
    const { currentUrl } = useCurrentUrl();
    const overview = versoNav.find((item) => item.key === DASHBOARD_KEY);
    const pages = versoNav.filter((item) => item.parent === DASHBOARD_KEY);

    if (!overview || pages.length === 0) {
        return null;
    }

    const items = [{ ...overview, label: t('Overview') }, ...pages];
    const active = activeNavKey(items, currentUrl) ?? DASHBOARD_KEY;

    return (
        <div className="mb-6">
            <FilterTabs
                label={t('Dashboard pages')}
                active={active}
                tabs={items.map((item) => ({
                    key: item.key,
                    label: item.label,
                    href: item.href,
                }))}
            />
        </div>
    );
}
