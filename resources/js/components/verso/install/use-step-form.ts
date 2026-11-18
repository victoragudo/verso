import type { FormDataType } from '@inertiajs/core';
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useRef } from 'react';

// Wraps Inertia useForm for one wizard step: posts to /install/<step> and moves focus to the first error.
export function useStepForm<T extends FormDataType<T>>(
    step: string,
    initial: T,
) {
    const form = useForm<T>(initial);
    const formRef = useRef<HTMLFormElement>(null);
    const errorCount = Object.keys(form.errors).length;

    useEffect(() => {
        if (errorCount === 0) {
            return;
        }

        const invalid = formRef.current?.querySelector<HTMLElement>(
            '[aria-invalid="true"], [data-error-summary]',
        );
        invalid?.focus();
    }, [errorCount]);

    const submit = (event?: FormEvent) => {
        event?.preventDefault();
        form.post(`/install/${step}`, { preserveScroll: true });
    };

    const serverErrors = form.errors as Record<string, string | undefined>;

    return { form, formRef, submit, serverErrors };
}
