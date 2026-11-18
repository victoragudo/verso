import { msg, t } from '@/lib/i18n';
import { router } from '@inertiajs/react';
import { useId, useState } from 'react';
import type { ChangeEvent } from 'react';
import { cn } from '@/lib/utils';
import { update } from '@/routes/admin/easy/simple-mode';

type Props = {
    enabled: boolean;
    label?: string;
    description?: string;
    className?: string;
};

export const SIMPLE_MODE_LABEL = msg('Simple mode');
export const SIMPLE_MODE_DESCRIPTION = msg(
    'Show only the tools most people need, with plain words. Turn it off to show all tools.',
);

// Saves the simple mode of the signed in person. The page props (menu included) come back with the answer.
export function saveSimpleMode(
    enabled: boolean,
    callbacks: { onStart?: () => void; onFinish?: () => void } = {},
): void {
    router.put(
        update.url(),
        { enabled },
        { preserveScroll: true, ...callbacks },
    );
}

// The on and off switch of the simple admin mode. A native checkbox with the switch look.
export function SimpleModeSwitch({
    enabled,
    label = t(SIMPLE_MODE_LABEL),
    description = t(SIMPLE_MODE_DESCRIPTION),
    className,
}: Props) {
    const id = useId();
    const [saving, setSaving] = useState(false);

    const change = (event: ChangeEvent<HTMLInputElement>) => {
        saveSimpleMode(event.target.checked, {
            onStart: () => setSaving(true),
            onFinish: () => setSaving(false),
        });
    };

    return (
        <div className={cn('flex items-start gap-3', className)}>
            <input
                id={id}
                type="checkbox"
                switch=""
                role="switch"
                checked={enabled}
                disabled={saving}
                onChange={change}
                aria-describedby={description ? `${id}-help` : undefined}
                className="relative mt-0.5 h-6 w-10 shrink-0 cursor-pointer appearance-none rounded-full bg-zinc-300 transition-colors duration-150 outline-none before:absolute before:start-0.5 before:top-0.5 before:size-5 before:rounded-full before:bg-white before:shadow-xs before:transition-transform before:duration-150 checked:bg-brand checked:before:translate-x-4 focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-wait disabled:opacity-60 motion-reduce:transition-none motion-reduce:before:transition-none rtl:checked:before:-translate-x-4 dark:bg-zinc-600"
            />
            <div className="min-w-0">
                <label
                    htmlFor={id}
                    className="block cursor-pointer text-sm font-medium text-foreground"
                >
                    {label}
                </label>
                {description && (
                    <p
                        id={`${id}-help`}
                        className="mt-0.5 max-w-prose text-sm text-muted-foreground"
                    >
                        {description}
                    </p>
                )}
            </div>
        </div>
    );
}
