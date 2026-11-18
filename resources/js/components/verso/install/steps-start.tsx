import { router } from '@inertiajs/react';
import { Check, RefreshCw, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { SearchableSelect } from '@/components/verso/ui/searchable-select';
import { msg, t } from '@/lib/i18n';
import { update } from '@/routes/verso/locale';
import type { InstallRequirement } from '@/types';
import { Field } from './fields';
import { StepFrame } from './step-frame';
import { useStepForm } from './use-step-form';

const GROUP_TITLES: Record<string, string> = {
    php: msg('PHP'),
    extensions: msg('PHP extensions'),
    folders: msg('Files and folders'),
};

// Step 1: a short welcome and the language of the site.
export function WelcomeStep({
    values,
    languages,
}: {
    values: { language?: string };
    languages: Record<string, string>;
}) {
    const { form, formRef, submit } = useStepForm('welcome', {
        language: values.language ?? 'en',
    });

    return (
        <StepFrame
            title={t('Welcome to Verso')}
            description={t(
                'This wizard sets up your site in a few minutes. Every step is checked on the server, and nothing is saved before the last step.',
            )}
            formRef={formRef}
            onSubmit={submit}
            processing={form.processing}
            submitLabel={t('Start')}
        >
            <ul className="grid gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                {[
                    t(
                        'A database: SQLite works out of the box, or the host, name, user and password of MySQL, MariaDB or PostgreSQL.',
                    ),
                    t('The name and address of your site.'),
                    t(
                        'An email address and a strong password for your admin account.',
                    ),
                ].map((item) => (
                    <li key={item} className="flex gap-2">
                        <Check
                            className="mt-0.5 size-4 shrink-0 text-emerald-600"
                            aria-hidden="true"
                        />
                        {item}
                    </li>
                ))}
            </ul>
            <Field
                label={t('Language')}
                error={form.errors.language}
                hint={t(
                    'Used for the site and the admin. You can change it later.',
                )}
                className="max-w-sm"
            >
                <SearchableSelect
                    value={form.data.language}
                    onValueChange={(language) => {
                        form.setData('language', language);
                        // Show the installer in the picked language right away.
                        router.put(
                            update.url(),
                            { locale: language },
                            { preserveScroll: true },
                        );
                    }}
                    options={Object.entries(languages).map(
                        ([value, label]) => ({
                            value,
                            label,
                            keywords: [value],
                        }),
                    )}
                    searchPlaceholder={t('Search languages')}
                />
            </Field>
        </StepFrame>
    );
}

// Step 2: server checks, each failure with a clear fix.
export function RequirementsStep({
    requirements,
    ready,
}: {
    requirements: InstallRequirement[];
    ready: boolean;
}) {
    const { form, formRef, submit, serverErrors } = useStepForm(
        'requirements',
        {},
    );
    const groups = Object.keys(GROUP_TITLES).map((group) => ({
        group,
        items: requirements.filter((item) => item.group === group),
    }));
    const failed = requirements.filter((item) => !item.passed).length;

    return (
        <StepFrame
            title={t('Server check')}
            description={
                ready
                    ? t('Your server has everything Verso needs.')
                    : t(
                          '{count, plural, one {# check needs} other {# checks need}} your attention. Fix them, then check again.',
                          { count: failed },
                      )
            }
            back="welcome"
            formRef={formRef}
            onSubmit={submit}
            processing={form.processing}
            errors={
                serverErrors.requirements ? [serverErrors.requirements] : []
            }
            extraActions={
                <Button
                    type="button"
                    variant="outline"
                    onClick={() =>
                        router.reload({ only: ['requirements', 'ready'] })
                    }
                >
                    <RefreshCw aria-hidden="true" />
                    {t('Check again')}
                </Button>
            }
        >
            {groups.map(({ group, items }) =>
                items.length === 0 ? null : (
                    <section key={group} className="grid gap-2">
                        <h3 className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                            {t(GROUP_TITLES[group])}
                        </h3>
                        <ul className="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                            {items.map((item) => (
                                <li
                                    key={item.label}
                                    className="flex gap-3 px-3 py-2.5 text-sm"
                                >
                                    {item.passed ? (
                                        <Check
                                            className="mt-0.5 size-4 shrink-0 text-emerald-600"
                                            aria-label={t('Passed')}
                                        />
                                    ) : (
                                        <X
                                            className="mt-0.5 size-4 shrink-0 text-red-600"
                                            aria-label={t('Failed')}
                                        />
                                    )}
                                    <div className="grid min-w-0 gap-0.5">
                                        <span
                                            className="font-medium [overflow-wrap:anywhere] text-zinc-900 dark:text-zinc-100"
                                            data-test="requirement-label"
                                        >
                                            {item.label}
                                        </span>
                                        <span className="[overflow-wrap:anywhere] text-muted-foreground">
                                            {item.detail}
                                        </span>
                                        {!item.passed && item.fix && (
                                            <span className="rounded-md bg-amber-50 px-2 py-1 text-xs break-words text-amber-900 dark:bg-amber-950 dark:text-amber-100">
                                                {t('How to fix: {fix}', {
                                                    fix: item.fix,
                                                })}
                                            </span>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                ),
            )}
        </StepFrame>
    );
}
