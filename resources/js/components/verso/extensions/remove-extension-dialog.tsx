import { router } from '@inertiajs/react';
import { useState } from 'react';
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
import { remove } from '@/routes/admin/extensions';

export type RemovableExtension = {
    kind: 'plugin' | 'theme';
    slug: string;
    name: string;
};

type Props = {
    extension: RemovableExtension | null;
    onClose: () => void;
};

// Asks how to remove a plugin or theme: keep its data for a later install, or delete it for good.
export function RemoveExtensionDialog({ extension, onClose }: Props) {
    const [processing, setProcessing] = useState(false);

    const submit = (deleteData: boolean) => {
        if (!extension) {
            return;
        }

        setProcessing(true);
        router.delete(
            remove.url({ kind: extension.kind, slug: extension.slug }),
            {
                data: { delete_data: deleteData },
                preserveScroll: true,
                onFinish: () => {
                    setProcessing(false);
                    onClose();
                },
            },
        );
    };

    return (
        <Dialog
            open={extension !== null}
            onOpenChange={(open) => !open && !processing && onClose()}
        >
            <DialogContent>
                {extension && (
                    <>
                        <DialogHeader>
                            <DialogTitle>
                                {t('Remove {name}?', { name: extension.name })}
                            </DialogTitle>
                            <DialogDescription>
                                {extension.kind === 'plugin'
                                    ? t(
                                          'The plugin is disabled, then its files are deleted. You can keep its database tables and saved settings for a later install, or delete them. Deleted data cannot be restored.',
                                      )
                                    : t(
                                          'Its files are deleted. You can keep its saved customizer settings and its Site Editor changes for a later install, or delete them. Deleted data cannot be restored.',
                                      )}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter className="gap-2 sm:gap-2">
                            <Button
                                variant="outline"
                                disabled={processing}
                                onClick={() => submit(false)}
                                className="min-h-10 md:min-h-9"
                            >
                                {t('Remove, keep data')}
                            </Button>
                            <Button
                                variant="destructive"
                                disabled={processing}
                                onClick={() => submit(true)}
                                className="min-h-10 md:min-h-9"
                            >
                                {t('Remove and delete data')}
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
