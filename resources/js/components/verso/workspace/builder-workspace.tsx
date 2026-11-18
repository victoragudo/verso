import { usePage } from '@inertiajs/react';
import { Lightbulb, Maximize2, Minimize2, X } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import type { ComponentProps, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useContentWidth } from '@/components/verso/content/content-width';
import { SidebarHeaderClose } from '@/components/verso/content/editor-layout';
import {
    EDITOR_ICON_BUTTON,
    EditorTopBar,
} from '@/components/verso/content/editor-top-bar';
import {
    EditorPanelDefaultContext,
    EditorPanelSlotContext,
} from '@/components/verso/editor/editor-side-panel';
import { useAdminNavWidth } from '@/hooks/use-admin-nav-width';
import { useElementSize } from '@/hooks/use-element-size';
import { useReturnFocus } from '@/hooks/use-return-focus';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { useFocusMode } from './focus-mode';
import { SplitPane } from './split-pane';
import {
    blocksPanelOpenByDefault,
    settingsInline,
    settingsOpenByDefault,
    workspaceMode,
} from './workspace-mode';
import { readStored, useWorkspaceKey, writeStored } from './workspace-storage';

export const WORKSPACE_SETTINGS_ID = 'workspace-settings';

type TopBarProps = Pick<
    ComponentProps<typeof EditorTopBar>,
    'backHref' | 'backLabel' | 'status' | 'actions' | 'menu'
>;

type Props = TopBarProps & {
    // Name of the saved sizes and choices, like "theme-builder".
    name: string;
    canvasLabel: string;
    canvas: ReactNode;
    // The live preview. Without it the canvas takes the whole width.
    preview?: ReactNode;
    settings: ReactNode;
    settingsDescription: string;
    // A short help text shown above the canvas until people close it.
    tip?: { id: string; text: string };
};

const OPEN = 'open';

// From this window width the admin sidebar is a column (lg).
const LARGE_SCREEN = 1024;
const CLOSED = 'closed';

// The frame of every builder: a bar on top, then panes that fill the rest of the screen and scroll on their own.
// From left to right: the Add blocks or Outline panel, the block canvas, the preview and the settings.
export function BuilderWorkspace({
    name,
    canvasLabel,
    canvas,
    preview,
    settings,
    settingsDescription,
    tip,
    ...topBar
}: Props) {
    const id = useId();
    const i18n = usePage().props.i18n;
    const [root, size] = useElementSize<HTMLDivElement>();
    const [focusMode, setFocusMode] = useFocusMode();
    const navWidth = useAdminNavWidth().width;
    // Before the first measure, guess the width from the window minus the admin sidebar.
    const estimate =
        window.innerWidth -
        (focusMode || window.innerWidth < LARGE_SCREEN ? 0 : navWidth);
    const mode = workspaceMode(size.width > 0 ? size.width : estimate);
    const [contentWidth] = useContentWidth();
    const settingsKey = useWorkspaceKey(`settings.${name}`);
    const [savedSettings, setSavedSettings] = useState(() =>
        readStored(settingsKey),
    );
    const [sheetOpen, setSheetOpen] = useState(false);
    const [shown, setShown] = useState<'start' | 'end'>('start');
    const [panelSlot, setPanelSlot] = useState<HTMLDivElement | null>(null);
    const sheetFocus = useReturnFocus();
    const inline = settingsInline(mode);
    const settingsOpen = inline
        ? savedSettings === null
            ? settingsOpenByDefault(mode)
            : savedSettings === OPEN
        : sheetOpen;

    const setSettingsOpen = (open: boolean) => {
        if (inline) {
            const value = open ? OPEN : CLOSED;
            setSavedSettings(value);
            writeStored(settingsKey, value);

            return;
        }

        if (open) {
            sheetFocus.remember();
        }

        setSheetOpen(open);
    };

    // Mod+Shift+, opens and closes the settings, as in the other editors.
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (
                (event.metaKey || event.ctrlKey) &&
                event.shiftKey &&
                event.key === ','
            ) {
                event.preventDefault();
                document
                    .querySelector<HTMLButtonElement>(
                        '[data-test="workspace-settings-toggle"]',
                    )
                    ?.click();
            }
        };
        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);

    const canvasPane = (
        <section
            aria-label={canvasLabel}
            data-content-width={contentWidth}
            lang={i18n?.contentLang}
            dir={i18n?.contentDir}
            className="@container/canvas flex min-h-0 min-w-0 flex-1 [scrollbar-gutter:stable] flex-col overflow-y-auto overscroll-contain bg-background"
            data-test="workspace-canvas"
        >
            {tip && <WorkspaceTip id={tip.id} text={tip.text} />}
            <div className="flex min-w-0 flex-1 flex-col p-3 @3xl/canvas:p-4 [&>[data-test=block-editor]]:flex-1">
                {canvas}
            </div>
        </section>
    );

    return (
        <div
            ref={root}
            data-mode={mode}
            data-test="builder-workspace"
            className="@container/workspace flex min-h-0 flex-1 flex-col bg-background"
        >
            <EditorTopBar
                {...topBar}
                back={focusMode ? 'logo' : 'arrow'}
                tools={
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className={cn(
                            EDITOR_ICON_BUTTON,
                            'hidden lg:inline-flex',
                        )}
                        aria-label={t('Focus mode')}
                        title={t('Focus mode: hide the admin menu')}
                        aria-pressed={focusMode}
                        onClick={() => setFocusMode(!focusMode)}
                        data-test="focus-mode"
                    >
                        {focusMode ? (
                            <Minimize2 aria-hidden="true" />
                        ) : (
                            <Maximize2 aria-hidden="true" />
                        )}
                    </Button>
                }
                settingsOpen={settingsOpen}
                settingsControls={inline ? WORKSPACE_SETTINGS_ID : undefined}
                onToggleSettings={() => setSettingsOpen(!settingsOpen)}
            />

            {preview && (
                <div
                    role="tablist"
                    aria-label={t('Edit or preview')}
                    className="flex shrink-0 border-b bg-card @5xl/workspace:hidden"
                >
                    {(['start', 'end'] as const).map((side) => (
                        <button
                            key={side}
                            type="button"
                            role="tab"
                            id={`${id}-${side}-tab`}
                            aria-selected={shown === side}
                            onClick={() => setShown(side)}
                            className="relative min-h-11 flex-1 px-3 text-sm font-medium text-muted-foreground outline-none hover:text-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:ring-inset aria-selected:text-foreground aria-selected:after:absolute aria-selected:after:inset-x-3 aria-selected:after:bottom-0 aria-selected:after:border-b-2 aria-selected:after:border-foreground"
                        >
                            {side === 'start' ? t('Edit') : t('Preview')}
                        </button>
                    ))}
                </div>
            )}

            <div className="flex min-h-0 flex-1">
                <div
                    ref={setPanelSlot}
                    data-test="editor-panel-slot"
                    className="flex min-h-0 shrink-0 empty:hidden [&>aside]:w-80"
                />
                <EditorPanelSlotContext.Provider value={panelSlot}>
                    <EditorPanelDefaultContext.Provider
                        value={blocksPanelOpenByDefault(mode) ? 'add' : null}
                    >
                        {preview ? (
                            <SplitPane
                                name={name}
                                startId={`${id}-canvas`}
                                start={canvasPane}
                                end={preview}
                                shown={shown}
                            />
                        ) : (
                            canvasPane
                        )}
                    </EditorPanelDefaultContext.Provider>
                </EditorPanelSlotContext.Provider>

                {inline ? (
                    <aside
                        id={WORKSPACE_SETTINGS_ID}
                        aria-label={t('Settings')}
                        hidden={!settingsOpen}
                        data-test="workspace-settings"
                        className="w-[22rem] shrink-0 overflow-y-auto overscroll-contain border-s bg-card"
                    >
                        <SidebarHeaderClose
                            onClose={() => setSettingsOpen(false)}
                        />
                        {settings}
                    </aside>
                ) : (
                    <Sheet open={sheetOpen} onOpenChange={setSettingsOpen}>
                        <SheetContent
                            side="right"
                            onCloseAutoFocus={sheetFocus.restore}
                            className="w-[22rem] max-w-[90vw] gap-0 overflow-y-auto overscroll-contain p-0 sm:max-w-[22rem] [&>button:last-child]:hidden"
                        >
                            <SheetTitle className="sr-only">
                                {t('Settings')}
                            </SheetTitle>
                            <SheetDescription className="sr-only">
                                {settingsDescription}
                            </SheetDescription>
                            <SidebarHeaderClose
                                onClose={() => setSettingsOpen(false)}
                            />
                            {settings}
                        </SheetContent>
                    </Sheet>
                )}
            </div>
        </div>
    );
}

// A help text over the canvas. Closing it hides it for good for this user.
function WorkspaceTip({ id, text }: { id: string; text: string }) {
    const key = useWorkspaceKey(`tip.${id}`);
    const [closed, setClosed] = useState(() => readStored(key) === CLOSED);

    if (closed) {
        return null;
    }

    return (
        <div
            role="note"
            className="mx-3 mt-3 flex items-start gap-2 rounded-md border bg-card px-3 py-2 text-sm text-muted-foreground @3xl/canvas:mx-4"
            data-test="workspace-tip"
        >
            <Lightbulb
                className="mt-0.5 size-4 shrink-0 text-foreground"
                aria-hidden="true"
            />
            <p className="min-w-0 flex-1">{text}</p>
            <Button
                type="button"
                variant="ghost"
                size="icon"
                className="-my-1 -me-2 size-8 shrink-0"
                aria-label={t('Close this tip')}
                title={t('Close this tip')}
                onClick={() => {
                    setClosed(true);
                    writeStored(key, CLOSED);
                }}
            >
                <X aria-hidden="true" />
            </Button>
        </div>
    );
}
