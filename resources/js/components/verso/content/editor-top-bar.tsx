import { Link } from '@inertiajs/react';
import { ArrowLeft, MoreVertical, PanelRight } from 'lucide-react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { VersoLogo } from '@/components/verso/brand/verso-logo';
import { t } from '@/lib/i18n';
import { CONTENT_WIDTHS, useContentWidth } from './content-width';
import type { ContentWidth } from './content-width';
import { shortcutText } from './editor-shortcuts-dialog';

export const EDITOR_ICON_BUTTON =
    'size-10 shrink-0 text-muted-foreground hover:text-foreground aria-expanded:bg-accent aria-expanded:text-foreground aria-pressed:bg-accent aria-pressed:text-foreground';

type Props = {
    backHref: string;
    backLabel: string;
    // "logo" for full screen editors, "arrow" inside the admin frame, where the sidebar already shows the logo.
    back?: 'logo' | 'arrow';
    status: ReactNode;
    actions: ReactNode;
    menu: ReactNode;
    // Buttons between the actions and the settings button, like focus mode.
    tools?: ReactNode;
    settingsOpen: boolean;
    // Id of the settings column when it is part of the page, not a sheet.
    settingsControls?: string;
    onToggleSettings: () => void;
};

// The slim bar on top of every editor: back to the list, name and status, actions, settings and the More menu.
export function EditorTopBar({
    backHref,
    backLabel,
    back = 'logo',
    status,
    actions,
    menu,
    tools,
    settingsOpen,
    settingsControls,
    onToggleSettings,
}: Props) {
    const [contentWidth, setContentWidth] = useContentWidth();

    return (
        <header className="flex h-14 shrink-0 items-center gap-2 border-b bg-card px-2 sm:gap-3 sm:px-3">
            {back === 'arrow' ? (
                <Button
                    asChild
                    variant="ghost"
                    size="icon"
                    className={EDITOR_ICON_BUTTON}
                >
                    <Link
                        href={backHref}
                        aria-label={backLabel}
                        title={backLabel}
                    >
                        <ArrowLeft
                            aria-hidden="true"
                            className="rtl:-scale-x-100"
                        />
                    </Link>
                </Button>
            ) : (
                <Link
                    href={backHref}
                    aria-label={backLabel}
                    title={backLabel}
                    className="flex size-10 shrink-0 items-center justify-center rounded-md bg-zinc-900 text-white transition-colors hover:bg-zinc-700 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-300"
                >
                    <VersoLogo
                        variant="mark"
                        title=""
                        aria-hidden="true"
                        className="size-6"
                    />
                </Link>
            )}

            <div className="flex min-w-0 flex-1 items-center gap-2 overflow-hidden sm:gap-3 [&>h1]:min-w-16">
                {status}
            </div>

            <div className="flex shrink-0 items-center gap-1.5 sm:gap-2">
                {actions}
                {tools}
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className={EDITOR_ICON_BUTTON}
                    aria-label={t('Settings')}
                    title={t('Settings ({shortcut})', {
                        shortcut: shortcutText(['Mod', 'Shift', ',']),
                    })}
                    aria-expanded={settingsOpen}
                    aria-controls={settingsControls}
                    onClick={onToggleSettings}
                >
                    <PanelRight aria-hidden="true" />
                </Button>
                <DropdownMenu modal={false}>
                    <DropdownMenuTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className={EDITOR_ICON_BUTTON}
                            aria-label={t('More actions')}
                            title={t('More actions')}
                        >
                            <MoreVertical aria-hidden="true" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end" className="w-60">
                        {menu}
                        <DropdownMenuSeparator />
                        <DropdownMenuLabel className="text-xs font-normal text-muted-foreground">
                            {t('Content width')}
                        </DropdownMenuLabel>
                        <DropdownMenuRadioGroup
                            value={contentWidth}
                            onValueChange={(value) =>
                                setContentWidth(value as ContentWidth)
                            }
                        >
                            {CONTENT_WIDTHS.map((width) => (
                                <DropdownMenuRadioItem
                                    key={width.value}
                                    value={width.value}
                                >
                                    {t(width.label)}
                                </DropdownMenuRadioItem>
                            ))}
                        </DropdownMenuRadioGroup>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>
    );
}
