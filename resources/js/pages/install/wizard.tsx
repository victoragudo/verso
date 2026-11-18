import { Head } from '@inertiajs/react';
import { VersoLogo } from '@/components/verso/brand/verso-logo';
import type { DriverOption } from '@/components/verso/install/step-database';
import { DatabaseStep } from '@/components/verso/install/step-database';
import type { PipelineStatus } from '@/components/verso/install/steps-finish';
import {
    InstallStep,
    ReviewStep,
} from '@/components/verso/install/steps-finish';
import {
    ContentStep,
    ServicesStep,
    SiteStep,
} from '@/components/verso/install/steps-site';
import {
    RequirementsStep,
    WelcomeStep,
} from '@/components/verso/install/steps-start';
import type { WizardStepItem } from '@/components/verso/install/wizard-stepper';
import { WizardStepper } from '@/components/verso/install/wizard-stepper';
import { t } from '@/lib/i18n';
import type { InstallRequirement } from '@/types';

type DatabaseProps = Parameters<typeof DatabaseStep>[0];
type ServicesProps = Parameters<typeof ServicesStep>[0];
type ContentProps = Parameters<typeof ContentStep>[0];

type Props = {
    step: string;
    steps: WizardStepItem[];
    done: string[];
    values: Record<string, string | number | null>;
    savedSecrets: string[];
    versoVersion: string;
    languages?: Record<string, string>;
    requirements?: InstallRequirement[];
    ready?: boolean;
    drivers?: DriverOption[];
    sslModes?: string[];
    databaseDefaults?: DatabaseProps['databaseDefaults'];
    detected?: DatabaseProps['detected'];
    locales?: string[];
    timezones?: string[];
    passwordMinLength?: number;
    services?: ServicesProps['services'];
    themes?: ContentProps['themes'];
    plugins?: ContentProps['plugins'];
    summary?: Record<string, Record<string, string>>;
    editSteps?: Record<string, string>;
    status?: PipelineStatus;
};

const EMPTY_DEFAULTS: DatabaseProps['databaseDefaults'] = {
    host: '',
    database: '',
    sqlite_path: '',
    charset: '',
    collation: '',
    sslmode: '',
};

// The install wizard: one page, one step at a time, every step checked on the server.
export default function InstallWizard(props: Props) {
    const { step, steps, done, versoVersion } = props;
    const title =
        steps.find((item) => item.key === step)?.label ?? t('Install');

    return (
        <>
            <Head title={t('{title} · Install Verso', { title })} />
            <div className="min-h-svh bg-zinc-50 px-4 py-8 sm:py-12 dark:bg-background">
                <main className="mx-auto grid w-full max-w-3xl gap-6">
                    <header className="flex items-center gap-3">
                        <VersoLogo className="size-8 text-zinc-900 dark:text-white" />
                        <div>
                            <h1 className="text-base font-semibold text-zinc-900 dark:text-white">
                                {t('Install Verso')}
                            </h1>
                            <p className="text-xs text-muted-foreground">
                                {t('Version {version}', {
                                    version: versoVersion,
                                })}
                            </p>
                        </div>
                    </header>
                    <WizardStepper steps={steps} current={step} done={done} />
                    <StepView key={step} {...props} />
                    <p className="text-center text-xs text-muted-foreground">
                        {t('Verso is free software under the MIT license.')}
                    </p>
                </main>
            </div>
        </>
    );
}

function StepView(props: Props) {
    switch (props.step) {
        case 'welcome':
            return (
                <WelcomeStep
                    values={{ language: String(props.values.language ?? 'en') }}
                    languages={props.languages ?? {}}
                />
            );
        case 'requirements':
            return (
                <RequirementsStep
                    requirements={props.requirements ?? []}
                    ready={props.ready ?? false}
                />
            );
        case 'database':
            return (
                <DatabaseStep
                    values={props.values}
                    savedSecrets={props.savedSecrets}
                    drivers={props.drivers ?? []}
                    sslModes={props.sslModes ?? []}
                    databaseDefaults={props.databaseDefaults ?? EMPTY_DEFAULTS}
                    detected={props.detected ?? null}
                />
            );
        case 'site':
            return (
                <SiteStep
                    values={props.values}
                    savedSecrets={props.savedSecrets}
                    locales={props.locales ?? []}
                    languages={props.languages ?? {}}
                    timezones={props.timezones ?? []}
                    passwordMinLength={props.passwordMinLength ?? 10}
                />
            );
        case 'services':
            return props.services ? (
                <ServicesStep
                    values={props.values}
                    savedSecrets={props.savedSecrets}
                    services={props.services}
                />
            ) : null;
        case 'content':
            return (
                <ContentStep
                    values={props.values}
                    themes={props.themes ?? []}
                    plugins={props.plugins ?? []}
                />
            );
        case 'review':
            return (
                <ReviewStep
                    summary={props.summary ?? {}}
                    editSteps={props.editSteps ?? {}}
                />
            );
        default:
            return props.status ? <InstallStep status={props.status} /> : null;
    }
}
