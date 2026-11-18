import { useCallback, useEffect, useRef } from 'react';
import { attachBridge } from '@/lib/easy/frame-bridge';
import type {
    FrameBridge,
    FrameBridgeOptions,
    FrameFocus,
} from '@/lib/easy/frame-bridge';

type Props = {
    // The page HTML from the server, or null before the first drawing.
    html: string | null;
    width: number | null;
    title: string;
    bridge: Omit<FrameBridgeOptions, 'focus' | 'selected'>;
    // What to focus after the next drawing, asked once per drawing.
    takeFocus: () => FrameFocus | null;
    onUndo: () => void;
    onRedo: () => void;
    onSave?: () => void;
};

// Undo, redo and save keys pressed inside the frame. Typing in a text keeps the browser's own undo.
export function frameShortcut(
    event: Pick<KeyboardEvent, 'key' | 'metaKey' | 'ctrlKey' | 'shiftKey'>,
    editing: boolean,
): 'undo' | 'redo' | 'save' | null {
    if (!(event.metaKey || event.ctrlKey)) {
        return null;
    }

    const key = event.key.toLowerCase();

    if (key === 's') {
        return 'save';
    }

    if (editing) {
        return null;
    }

    if (key === 'z') {
        return event.shiftKey ? 'redo' : 'undo';
    }

    return key === 'y' ? 'redo' : null;
}

// The page in a same origin frame (srcdoc). Keeps the scroll position and the tapped section when the page
// is drawn again, and waits while a text is being edited so typing is never lost.
export function EditorFrame({
    html,
    width,
    title,
    bridge,
    takeFocus,
    onUndo,
    onRedo,
    onSave,
}: Props) {
    const frameRef = useRef<HTMLIFrameElement>(null);
    const bridgeRef = useRef<FrameBridge | null>(null);
    const optionsRef = useRef({ bridge, takeFocus, onUndo, onRedo, onSave });
    const scrollRef = useRef(0);
    const pendingRef = useRef<string | null>(null);
    const selectedRef = useRef<string | null>(null);

    useEffect(() => {
        optionsRef.current = { bridge, takeFocus, onUndo, onRedo, onSave };
    });

    // Swaps the page and remembers where the person had scrolled.
    const show = useCallback((next: string) => {
        const frame = frameRef.current;

        if (!frame) {
            return;
        }

        scrollRef.current = frame.contentWindow?.scrollY ?? scrollRef.current;
        bridgeRef.current?.detach();
        bridgeRef.current = null;
        frame.srcdoc = next;
    }, []);

    useEffect(() => {
        if (html === null) {
            return;
        }

        if (bridgeRef.current?.isEditing()) {
            pendingRef.current = html;

            return;
        }

        show(html);
    }, [html, show]);

    useEffect(() => () => bridgeRef.current?.detach(), []);

    const attach = () => {
        const frame = frameRef.current;
        const doc = frame?.contentDocument;

        if (!frame || !frame.srcdoc || !doc?.body) {
            return;
        }

        frame.contentWindow?.scrollTo({
            top: scrollRef.current,
            behavior: 'instant',
        });
        doc.addEventListener('keydown', (event) => {
            const shortcut = frameShortcut(
                event,
                bridgeRef.current?.isEditing() ?? false,
            );

            if (!shortcut) {
                return;
            }

            event.preventDefault();
            const {
                onUndo: undo,
                onRedo: redo,
                onSave: save,
            } = optionsRef.current;

            if (shortcut === 'save') {
                if (bridgeRef.current?.isEditing()) {
                    (doc.activeElement as HTMLElement | null)?.blur();
                }

                save?.();
            } else if (shortcut === 'redo') {
                redo();
            } else {
                undo();
            }
        });

        const latest = () => optionsRef.current.bridge;
        bridgeRef.current = attachBridge(doc, {
            ...latest(),
            focus: optionsRef.current.takeFocus(),
            selected: selectedRef.current,
            onSelect: (sectionId) => {
                selectedRef.current = sectionId;
                latest().onSelect?.(sectionId);
            },
            onText: (...args) => latest().onText(...args),
            onImage: (...args) => latest().onImage(...args),
            onSectionAction: (...args) => latest().onSectionAction(...args),
            onAddSection: (...args) => latest().onAddSection(...args),
            onEditingChange: (editing) => {
                latest().onEditingChange?.(editing);

                if (!editing && pendingRef.current !== null) {
                    const next = pendingRef.current;
                    pendingRef.current = null;
                    window.setTimeout(() => {
                        if (!bridgeRef.current?.isEditing()) {
                            show(next);
                        }
                    });
                }
            },
        });
    };

    return (
        <iframe
            ref={frameRef}
            title={title}
            onLoad={attach}
            className="h-full max-w-full border-0 bg-white shadow-sm transition-[width] duration-200 motion-reduce:transition-none"
            style={{ width: width ? `${width}px` : '100%' }}
            data-test="page-frame"
        />
    );
}
