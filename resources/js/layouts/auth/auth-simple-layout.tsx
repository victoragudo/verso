import { Link, usePage } from '@inertiajs/react';
import { VersoLogo } from '@/components/verso/brand/verso-logo';
import { LanguageSelect } from '@/components/verso/i18n/language-select';
import { t } from '@/lib/i18n';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

// Split layout for the sign in pages: a quiet brand panel and the form card.
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { siteName, versoVersion } = usePage().props;
    const homeLabel = siteName
        ? t('{site} home', { site: siteName })
        : t('Home');

    return (
        <div className="relative grid min-h-svh grid-rows-[auto_minmax(0,1fr)] bg-background lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)] lg:grid-rows-1">
            <header className="z-10 flex justify-center px-4 pt-10 lg:absolute lg:start-10 lg:top-10 lg:p-0">
                <Link
                    href={home()}
                    className="-m-2 flex items-center rounded-md p-2 text-foreground transition-colors hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                >
                    <VersoLogo
                        variant="full"
                        aria-hidden="true"
                        className="h-7 w-auto"
                    />
                    <span className="sr-only">{homeLabel}</span>
                </Link>
            </header>

            <aside
                aria-label={t('About Verso')}
                className="relative hidden overflow-hidden border-e border-border bg-muted/60 lg:flex lg:flex-col lg:justify-between lg:p-10 dark:bg-card"
            >
                <div
                    aria-hidden="true"
                    className="auth-paper absolute inset-0"
                />
                <VersoLogo
                    aria-hidden="true"
                    className="pointer-events-none absolute -end-20 -top-20 size-[22rem] text-foreground/[0.03] dark:text-foreground/[0.02]"
                />

                <span aria-hidden="true" className="h-7" />

                <p className="relative max-w-[22ch] text-[1.75rem] leading-tight font-semibold tracking-[-0.015em] text-balance text-foreground">
                    {t('The back of the page, where your site is written.')}
                </p>

                <footer className="relative flex items-center justify-between gap-4 text-sm text-muted-foreground">
                    <span>{t('Free and open source, MIT licensed')}</span>
                    {versoVersion && (
                        <span className="tabular-nums">
                            Verso {versoVersion}
                        </span>
                    )}
                </footer>
            </aside>

            <main className="flex min-w-0 flex-col items-center justify-center px-4 pt-8 pb-10 sm:px-6 lg:px-10 lg:py-10">
                <div className="auth-enter w-full max-w-[25rem]">
                    <div className="rounded-xl border border-border bg-card p-6 shadow-xs sm:p-8">
                        <header className="mb-6 space-y-1.5">
                            <h1 className="text-xl leading-tight font-semibold tracking-[-0.01em] text-balance">
                                {title}
                            </h1>
                            {description && (
                                <p className="text-sm leading-normal text-pretty text-muted-foreground">
                                    {description}
                                </p>
                            )}
                        </header>
                        {children}
                    </div>
                    <LanguageSelect className="mt-4 justify-center" />
                </div>
            </main>
        </div>
    );
}
