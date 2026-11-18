import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { EDITOR_SHORTCUTS } from '@/hooks/use-editor-shortcuts';
import { t } from '@/lib/i18n';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCloseAutoFocus?: (event: Event) => void;
};

// True on Apple computers, where shortcuts use Cmd instead of Ctrl.
export function isApplePlatform(): boolean {
    if (typeof navigator === 'undefined') {
        return false;
    }

    return /mac|iphone|ipad/i.test(navigator.userAgent);
}

// The name of a key as people see it on their keyboard.
export function keyLabel(key: string): string {
    if (key === 'Mod') {
        return isApplePlatform() ? '⌘' : 'Ctrl';
    }

    return key;
}

// The shortcut as one line of text, like "Ctrl+Shift+,".
export function shortcutText(keys: readonly string[]): string {
    return keys.map(keyLabel).join('+');
}

// A dialog that lists the editor keyboard shortcuts.
export function EditorShortcutsDialog({
    open,
    onOpenChange,
    onCloseAutoFocus,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent onCloseAutoFocus={onCloseAutoFocus}>
                <DialogHeader>
                    <DialogTitle>{t('Keyboard shortcuts')}</DialogTitle>
                    <DialogDescription>
                        {t('Work faster in the editor.')}
                    </DialogDescription>
                </DialogHeader>
                <dl className="divide-y text-sm">
                    {EDITOR_SHORTCUTS.map((shortcut) => (
                        <div
                            key={shortcut.label}
                            className="flex items-center justify-between gap-4 py-2.5"
                        >
                            <dt className="text-foreground">
                                {t(shortcut.label)}
                            </dt>
                            <dd className="flex shrink-0 items-center gap-1">
                                {shortcut.keys.map((key) => (
                                    <kbd
                                        key={key}
                                        className="min-w-7 rounded border bg-muted px-1.5 py-0.5 text-center font-sans text-xs font-medium text-foreground"
                                    >
                                        {keyLabel(key)}
                                    </kbd>
                                ))}
                            </dd>
                        </div>
                    ))}
                </dl>
            </DialogContent>
        </Dialog>
    );
}
