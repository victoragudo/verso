import { router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { VersoIcon } from '@/components/verso/icons';
import { t, Trans } from '@/lib/i18n';
import { stop } from '@/routes/admin/view-as';
import type { ViewAsState } from '@/types';

// Shown on every admin page while an admin previews the site as another role.
export function ViewAsBanner() {
    const viewAs = usePage().props.viewAs as ViewAsState | null | undefined;

    if (!viewAs) {
        return null;
    }

    return (
        <section
            aria-label={t('Role preview')}
            className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 border-b border-warning-text/20 bg-warning-soft px-4 py-2 text-sm text-warning-text lg:px-6"
        >
            <p role="status" className="flex min-w-0 items-start gap-2">
                <VersoIcon name="shield" size={18} className="mt-px shrink-0" />
                <span className="min-w-0">
                    <Trans
                        text={t(
                            'You are viewing the admin as <strong>{role}</strong>. This preview is read only.',
                            { role: viewAs.name },
                        )}
                        components={{
                            strong: (text) => (
                                <strong className="font-semibold">
                                    {text}
                                </strong>
                            ),
                        }}
                    />
                </span>
            </p>
            <Button
                size="sm"
                variant="outline"
                className="min-h-10 md:min-h-8"
                onClick={() => router.delete(stop.url())}
            >
                {t('Exit preview')}
            </Button>
        </section>
    );
}
