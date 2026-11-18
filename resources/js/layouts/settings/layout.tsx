import type { PropsWithChildren } from 'react';
import { FilterTabs, PageHeader } from '@/components/verso/ui';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { msg, t } from '@/lib/i18n';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';

const tabs = [
    { key: 'profile', label: msg('Profile'), href: toUrl(edit()) },
    { key: 'security', label: msg('Security'), href: toUrl(editSecurity()) },
    {
        key: 'appearance',
        label: msg('Appearance'),
        href: toUrl(editAppearance()),
    },
];

// Account settings frame: page header, tabs and the page below.
export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const active =
        tabs.find((tab) => isCurrentOrParentUrl(tab.href))?.key ?? 'profile';

    return (
        <>
            <PageHeader
                icon="settings"
                title={t('Account settings')}
                description={t(
                    'Manage your profile, password and how the admin looks.',
                )}
            />
            <div className="mb-6">
                <FilterTabs
                    tabs={tabs.map((tab) => ({ ...tab, label: t(tab.label) }))}
                    active={active}
                    label={t('Settings')}
                />
            </div>
            {/* Cards flow into columns on wide screens; wide cards span the whole row. */}
            <div className="auto-fit-grid items-start gap-6 [--grid-min:36rem]">
                {children}
            </div>
        </>
    );
}
