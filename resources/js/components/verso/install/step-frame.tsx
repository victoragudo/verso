import { Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight } from 'lucide-react';
import type { FormEvent, ReactNode, RefObject } from 'react';
import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { t } from '@/lib/i18n';

type Props = {
    title: string;
    description: ReactNode;
    back?: string | null;
    submitLabel?: string;
    processing?: boolean;
    formRef?: RefObject<HTMLFormElement | null>;
    onSubmit?: (event: FormEvent) => void;
    extraActions?: ReactNode;
    errors?: string[];
    children: ReactNode;
};

// The card around one step: a focused heading, the form, and Back and Next buttons.
export function StepFrame({
    title,
    description,
    back,
    submitLabel,
    processing = false,
    formRef,
    onSubmit,
    extraActions,
    errors = [],
    children,
}: Props) {
    const headingRef = useRef<HTMLHeadingElement>(null);

    useEffect(() => {
        headingRef.current?.focus({ preventScroll: true });
    }, []);

    return (
        <form
            ref={formRef}
            method="post"
            noValidate={false}
            onSubmit={onSubmit}
            aria-labelledby="wizard-step-title"
            className="rounded-lg border border-zinc-200 bg-white shadow-xs dark:border-zinc-800 dark:bg-zinc-900"
        >
            <div className="grid gap-1 border-b border-zinc-200 px-5 py-4 sm:px-6 dark:border-zinc-800">
                <h2
                    id="wizard-step-title"
                    ref={headingRef}
                    tabIndex={-1}
                    className="text-lg font-semibold text-zinc-900 outline-none dark:text-zinc-100"
                >
                    {title}
                </h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
            <div className="grid gap-6 px-5 py-5 sm:px-6">
                {errors.length > 0 && (
                    <div
                        data-error-summary
                        tabIndex={-1}
                        role="alert"
                        className="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-900 outline-none dark:border-red-900 dark:bg-red-950 dark:text-red-100"
                    >
                        {errors.map((error) => (
                            <p key={error}>{error}</p>
                        ))}
                    </div>
                )}
                {children}
            </div>
            {onSubmit && (
                <div className="flex flex-wrap items-center justify-between gap-3 border-t border-zinc-200 px-5 py-4 sm:px-6 dark:border-zinc-800">
                    {back ? (
                        <Button variant="ghost" asChild>
                            <Link href={`/install/${back}`}>
                                <ArrowLeft aria-hidden="true" />
                                {t('Back')}
                            </Link>
                        </Button>
                    ) : (
                        <span />
                    )}
                    <div className="flex flex-wrap items-center gap-2">
                        {extraActions}
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel ?? t('Continue')}
                            {!processing && <ArrowRight aria-hidden="true" />}
                        </Button>
                    </div>
                </div>
            )}
        </form>
    );
}
