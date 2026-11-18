import { useEffect, useId, useRef } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

export type EditorSettingsTab = 'document' | 'block';

type Props = {
    tab: EditorSettingsTab;
    onTabChange: (tab: EditorSettingsTab) => void;
    documentLabel: string;
    document: ReactNode;
    block: ReactNode;
};

const TABS: EditorSettingsTab[] = ['document', 'block'];

// The two tabs of the settings sidebar: the entry ("Post", "Page") and the selected block.
export function EditorSettingsTabs({
    tab,
    onTabChange,
    documentLabel,
    document,
    block,
}: Props) {
    const id = useId();
    const tabRefs = useRef<Record<EditorSettingsTab, HTMLButtonElement | null>>(
        { document: null, block: null },
    );
    const labels: Record<EditorSettingsTab, string> = {
        document: documentLabel,
        block: t('Block'),
    };
    const panels: Record<EditorSettingsTab, ReactNode> = { document, block };

    // Arrow keys, Home and End move to another tab and select it.
    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const index = TABS.indexOf(tab);
        const rtl = getComputedStyle(event.currentTarget).direction === 'rtl';
        const step: Record<string, number> = {
            ArrowRight: rtl ? -1 : 1,
            ArrowLeft: rtl ? 1 : -1,
        };
        let next: number | null = null;

        if (event.key in step) {
            next = (index + step[event.key] + TABS.length) % TABS.length;
        } else if (event.key === 'Home') {
            next = 0;
        } else if (event.key === 'End') {
            next = TABS.length - 1;
        }

        if (next === null) {
            return;
        }

        event.preventDefault();
        onTabChange(TABS[next]);
        tabRefs.current[TABS[next]]?.focus();
    };

    return (
        <div>
            <div
                role="tablist"
                aria-label={t('Settings')}
                onKeyDown={onKeyDown}
                className="sticky top-0 z-[5] flex h-12 items-stretch gap-1 border-b bg-card ps-2 pe-12"
            >
                {TABS.map((name) => (
                    <button
                        key={name}
                        ref={(node) => {
                            tabRefs.current[name] = node;
                        }}
                        type="button"
                        role="tab"
                        id={`${id}-${name}-tab`}
                        aria-selected={tab === name}
                        tabIndex={tab === name ? 0 : -1}
                        onClick={() => onTabChange(name)}
                        className={cn(
                            'relative px-3 text-sm font-medium text-muted-foreground transition-colors outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:ring-inset',
                            'aria-selected:text-foreground aria-selected:after:absolute aria-selected:after:inset-x-2 aria-selected:after:bottom-0 aria-selected:after:border-b-2 aria-selected:after:border-zinc-900 dark:aria-selected:after:border-zinc-100',
                        )}
                    >
                        {labels[name]}
                    </button>
                ))}
            </div>
            {TABS.map((name) => (
                <TabPanel
                    key={name}
                    labelledBy={`${id}-${name}-tab`}
                    selected={tab === name}
                    onFound={() => onTabChange(name)}
                >
                    {panels[name]}
                </TabPanel>
            ))}
        </div>
    );
}

// A tab panel. Hidden panels use hidden="until-found", so find in page can still open them.
function TabPanel({
    labelledBy,
    selected,
    onFound,
    children,
}: {
    labelledBy: string;
    selected: boolean;
    onFound: () => void;
    children: ReactNode;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const found = useRef(onFound);

    useEffect(() => {
        found.current = onFound;
    });

    useEffect(() => {
        const panel = ref.current;

        if (!panel) {
            return;
        }

        if (selected) {
            panel.removeAttribute('hidden');
        } else {
            panel.setAttribute(
                'hidden',
                'onbeforematch' in panel ? 'until-found' : '',
            );
        }
    }, [selected]);

    useEffect(() => {
        const panel = ref.current;
        const reveal = () => found.current();
        panel?.addEventListener('beforematch', reveal);

        return () => panel?.removeEventListener('beforematch', reveal);
    }, []);

    return (
        <div
            ref={ref}
            role="tabpanel"
            aria-labelledby={labelledBy}
            hidden={!selected}
        >
            <div className="grid grid-cols-[minmax(0,1fr)] gap-4 p-4">
                {children}
            </div>
        </div>
    );
}
