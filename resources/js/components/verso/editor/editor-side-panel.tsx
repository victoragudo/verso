import { X } from 'lucide-react';
import { createContext, useContext } from 'react';
import type { ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetTitle,
} from '@/components/ui/sheet';
import { useMediaQuery } from '@/hooks/use-media-query';
import { useResizableWidth } from '@/hooks/use-resizable-width';
import type { WidthLimits } from '@/hooks/use-resizable-width';
import { t } from '@/lib/i18n';
import { ResizeHandle } from '@/components/verso/shell/resize-handle';

// From this width the panel is a column next to the canvas. Below it, it opens as a sheet over the page.
export const SIDE_PANEL_COLUMN_QUERY = '(min-width: 1024px)';

// Width of the panel column on wide screens. People can drag its edge.
export const SIDE_PANEL_WIDTH: WidthLimits = {
    min: 288,
    max: 640,
    initial: 320,
    storageKey: 'verso.editor.side-panel-width',
};

// The place in the editor frame, left of the canvas, where the panel column goes. Null outside the full screen editor.
export const EditorPanelSlotContext = createContext<HTMLElement | null>(null);

// The panel the block editor opens with. The builder workspace opens "Add blocks" when every pane fits.
export const EditorPanelDefaultContext = createContext<
    'add' | 'outline' | null
>(null);

type Props = {
    id: string;
    title: string;
    description: string;
    open: boolean;
    onClose: () => void;
    children: ReactNode;
};

// The "Add blocks" and "Outline" panel. On wide screens it takes its own column and pushes the canvas,
// so the canvas and the top bar stay fully visible. On small screens it is a sheet with a backdrop.
export function EditorSidePanel({
    id,
    title,
    description,
    open,
    onClose,
    children,
}: Props) {
    const slot = useContext(EditorPanelSlotContext);
    const wide = useMediaQuery(SIDE_PANEL_COLUMN_QUERY);
    const { width, setWidth, reset } = useResizableWidth(SIDE_PANEL_WIDTH);

    if (wide && slot) {
        return open
            ? createPortal(
                  // The handle sits on the wrapper, so it stays put while the panel scrolls.
                  <div className="relative flex shrink-0" style={{ width }}>
                      <aside
                          id={id}
                          aria-label={title}
                          data-test="editor-side-panel"
                          className="flex w-full min-w-0 flex-col gap-3 overflow-y-auto overscroll-contain border-e bg-card p-4"
                          onKeyDown={(event) => {
                              if (event.key === 'Escape') {
                                  onClose();
                              }
                          }}
                      >
                          <PanelHeader title={title} onClose={onClose} />
                          {children}
                      </aside>
                      <ResizeHandle
                          width={width}
                          limits={SIDE_PANEL_WIDTH}
                          label={t(
                              'Resize the panel. Use the arrow keys, or double click to reset.',
                          )}
                          onResize={setWidth}
                          onReset={reset}
                          slot="editor-side-panel-resizer"
                      />
                  </div>,
                  slot,
              )
            : null;
    }

    return (
        <Sheet open={open} onOpenChange={(next) => !next && onClose()}>
            <SheetContent
                id={id}
                side="left"
                data-test="editor-side-panel"
                className="w-80 max-w-[90vw] gap-3 overflow-y-auto overscroll-contain p-4 sm:max-w-80 [&>button:last-child]:hidden"
            >
                <SheetDescription className="sr-only">
                    {description}
                </SheetDescription>
                <PanelHeader title={title} onClose={onClose} sheet />
                {children}
            </SheetContent>
        </Sheet>
    );
}

function PanelHeader({
    title,
    onClose,
    sheet = false,
}: {
    title: string;
    onClose: () => void;
    sheet?: boolean;
}) {
    const Title = sheet ? SheetTitle : 'h2';

    return (
        <div className="flex items-center justify-between">
            <Title className="text-sm font-semibold">{title}</Title>
            <Button type="button" variant="ghost" size="sm" onClick={onClose}>
                <X />
                {t('Close')}
            </Button>
        </div>
    );
}
