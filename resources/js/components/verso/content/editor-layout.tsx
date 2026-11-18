import { usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { EditorPanelSlotContext } from '@/components/verso/editor/editor-side-panel';
import { useReturnFocus } from '@/hooks/use-return-focus';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { useContentWidth } from './content-width';
import { EDITOR_ICON_BUTTON, EditorTopBar } from './editor-top-bar';

export const EDITOR_SETTINGS_ID = 'editor-settings';
export const EDITOR_CANVAS_ID = 'editor-canvas';

type Props = {
    backHref: string;
    backLabel: string;
    status: ReactNode;
    actions: ReactNode;
    menu: ReactNode;
    sidebar: ReactNode;
    sidebarOpen: boolean;
    wide: boolean;
    onSidebarOpenChange: (open: boolean) => void;
    children: ReactNode;
};

// Full screen editor frame: a slim top bar, a column for the Add blocks or Outline panel, the canvas and a settings sidebar (a sheet below 1024px).
// The canvas uses the whole width; main gets data-content-width so pages can narrow it when people pick "Reading width".
export function EditorLayout({
    backHref,
    backLabel,
    status,
    actions,
    menu,
    sidebar,
    sidebarOpen,
    wide,
    onSidebarOpenChange,
    children,
}: Props) {
    // The canvas shows site content, so it uses the site language and direction.
    const i18n = usePage().props.i18n;
    const sheetFocus = useReturnFocus();
    const [panelSlot, setPanelSlot] = useState<HTMLDivElement | null>(null);
    const [contentWidth] = useContentWidth();
    const toggleSidebar = () => {
        if (!wide && !sidebarOpen) {
            sheetFocus.remember();
        }

        onSidebarOpenChange(!sidebarOpen);
    };

    return (
        <div
            className="flex h-dvh flex-col bg-background"
            data-test="editor-layout"
        >
            <EditorTopBar
                backHref={backHref}
                backLabel={backLabel}
                status={status}
                actions={actions}
                menu={menu}
                settingsOpen={sidebarOpen}
                settingsControls={wide ? EDITOR_SETTINGS_ID : undefined}
                onToggleSettings={toggleSidebar}
            />

            <div className="flex min-h-0 flex-1">
                <div
                    ref={setPanelSlot}
                    data-test="editor-panel-slot"
                    className="flex min-h-0 shrink-0 empty:hidden"
                />
                <main
                    id={EDITOR_CANVAS_ID}
                    data-content-width={contentWidth}
                    lang={i18n?.contentLang}
                    dir={i18n?.contentDir}
                    className="@container/canvas min-w-0 flex-1 overflow-y-auto overscroll-contain"
                >
                    <EditorPanelSlotContext.Provider value={panelSlot}>
                        {children}
                    </EditorPanelSlotContext.Provider>
                </main>

                {wide ? (
                    <aside
                        id={EDITOR_SETTINGS_ID}
                        aria-label={t('Settings')}
                        hidden={!sidebarOpen}
                        className="w-[clamp(20rem,18vw,26rem)] shrink-0 overflow-y-auto overscroll-contain border-s bg-card"
                    >
                        <SidebarHeaderClose
                            onClose={() => onSidebarOpenChange(false)}
                        />
                        {sidebar}
                    </aside>
                ) : (
                    <Sheet
                        open={sidebarOpen}
                        onOpenChange={onSidebarOpenChange}
                    >
                        <SheetContent
                            side="right"
                            onCloseAutoFocus={sheetFocus.restore}
                            className="w-80 max-w-[90vw] gap-0 overflow-y-auto overscroll-contain p-0 sm:max-w-80 [&>button:last-child]:hidden"
                        >
                            <SheetTitle className="sr-only">
                                {t('Settings')}
                            </SheetTitle>
                            <SheetDescription className="sr-only">
                                {t(
                                    'Status, link, terms and other settings of this entry.',
                                )}
                            </SheetDescription>
                            <SidebarHeaderClose
                                onClose={() => onSidebarOpenChange(false)}
                            />
                            {sidebar}
                        </SheetContent>
                    </Sheet>
                )}
            </div>
        </div>
    );
}

// The close button that stays at the top of the settings while they scroll.
export function SidebarHeaderClose({ onClose }: { onClose: () => void }) {
    return (
        <div className="sticky top-0 z-10 flex justify-end">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className={cn(
                    EDITOR_ICON_BUTTON,
                    'absolute end-1 top-1 size-10',
                )}
                aria-label={t('Close settings')}
                title={t('Close settings')}
                onClick={onClose}
            >
                <X aria-hidden="true" />
            </Button>
        </div>
    );
}
