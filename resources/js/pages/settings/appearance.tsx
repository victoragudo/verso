import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import { SectionCard } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import { edit as editAppearance } from '@/routes/appearance';

// Account page to pick light, dark or system theme.
export default function Appearance() {
    return (
        <>
            <Head title={t('Appearance settings')} />

            <SectionCard
                title={t('Appearance')}
                description={t('Choose how the admin looks on this device.')}
            >
                <AppearanceTabs />
            </SectionCard>
        </>
    );
}

Appearance.layout = () => ({
    breadcrumbs: [
        {
            title: t('Appearance settings'),
            href: editAppearance(),
        },
    ],
});
