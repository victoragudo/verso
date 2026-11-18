import { useEffect, useRef } from 'react';
import { msg } from '@/lib/i18n';

type Handlers = {
    onSave: () => void;
    onToggleSidebar: () => void;
};

// The editor keyboard shortcuts, shown in the shortcuts dialog.
export const EDITOR_SHORTCUTS = [
    { keys: ['Mod', 'S'], label: msg('Save') },
    { keys: ['Mod', 'Shift', ','], label: msg('Show or hide the settings') },
    { keys: ['/'], label: msg('Choose a block in an empty paragraph') },
    { keys: ['Esc'], label: msg('Close a menu, dialog or the settings sheet') },
] as const;

// True when the key event is the editor shortcut with Cmd (Mac) or Ctrl (others).
function withModifier(event: KeyboardEvent): boolean {
    return (event.metaKey || event.ctrlKey) && !event.altKey;
}

// Listens for Cmd/Ctrl+S (save) and Cmd/Ctrl+Shift+, (settings sidebar) on the whole page.
export function useEditorShortcuts(handlers: Handlers): void {
    const latest = useRef(handlers);

    useEffect(() => {
        latest.current = handlers;
    });

    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if (event.isComposing || !withModifier(event)) {
                return;
            }

            if (!event.shiftKey && event.key.toLowerCase() === 's') {
                event.preventDefault();
                latest.current.onSave();

                return;
            }

            if (event.shiftKey && event.code === 'Comma') {
                event.preventDefault();
                latest.current.onToggleSidebar();
            }
        };

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, []);
}
