import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { t } from '@/lib/i18n';

type Props = {
    open: boolean;
    title: ReactNode;
    description: ReactNode;
    confirmLabel: string;
    destructive?: boolean;
    onConfirm: () => void;
    onClose: () => void;
};

// Asks "are you sure?" before an action that is hard to undo.
export function ConfirmDialog({
    open,
    title,
    description,
    confirmLabel,
    destructive = false,
    onConfirm,
    onClose,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <Button variant="outline" onClick={onClose}>
                        {t('Cancel')}
                    </Button>
                    <Button
                        variant={destructive ? 'destructive' : 'default'}
                        onClick={onConfirm}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
