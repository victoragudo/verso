import { useId, useState } from 'react';
import { saveToLibrary } from '@/builder/library';
import type { LibraryItem } from '@/builder/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import type { Block } from '@/types';

type Props = {
    blocks: Block[] | null;
    onClose: () => void;
    // Called with the new item. A synced item should take the place of the blocks it was made from.
    onSaved: (item: LibraryItem) => void;
};

// Saves the chosen blocks to the design library, as a synced block or as a pattern.
export function SaveToLibraryDialog({ blocks, onClose, onSaved }: Props) {
    const id = useId();
    const [title, setTitle] = useState('');
    const [category, setCategory] = useState('');
    const [kind, setKind] = useState<LibraryItem['kind']>('synced');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const save = async () => {
        if (!blocks || title.trim() === '') {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            onSaved(
                await saveToLibrary({
                    kind,
                    title: title.trim(),
                    category: category.trim(),
                    blocks,
                }),
            );
            setTitle('');
            setCategory('');
            onClose();
        } catch (failure) {
            setError(
                t('Could not save to the library: {reason}', {
                    reason: failure instanceof Error ? failure.message : '',
                }),
            );
        } finally {
            setBusy(false);
        }
    };

    return (
        <Dialog
            open={blocks !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <form
                    className="grid gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        void save();
                    }}
                >
                    <DialogHeader>
                        <DialogTitle>{t('Save to the library')}</DialogTitle>
                        <DialogDescription>
                            {t(
                                '{count, plural, one {# block} other {# blocks}} will be saved.',
                                { count: blocks?.length ?? 0 },
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-1">
                        <Label htmlFor={`${id}-title`}>{t('Name')}</Label>
                        <Input
                            id={`${id}-title`}
                            value={title}
                            maxLength={120}
                            required
                            autoFocus
                            onChange={(event) => setTitle(event.target.value)}
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label htmlFor={`${id}-category`}>{t('Group')}</Label>
                        <Input
                            id={`${id}-category`}
                            value={category}
                            maxLength={60}
                            placeholder={t('Like "Calls to action"')}
                            onChange={(event) =>
                                setCategory(event.target.value)
                            }
                        />
                    </div>
                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            {t('How it is used')}
                        </legend>
                        <label className="flex items-start gap-2 text-sm">
                            <input
                                type="radio"
                                name={`${id}-kind`}
                                value="synced"
                                checked={kind === 'synced'}
                                onChange={() => setKind('synced')}
                                className="mt-1"
                            />
                            <span>
                                <span className="font-medium">
                                    {t('Synced block')}
                                </span>
                                <span className="block text-muted-foreground">
                                    {t(
                                        'One copy for the whole site. Change it once and every page changes. These blocks become the synced block.',
                                    )}
                                </span>
                            </span>
                        </label>
                        <label className="flex items-start gap-2 text-sm">
                            <input
                                type="radio"
                                name={`${id}-kind`}
                                value="pattern"
                                checked={kind === 'pattern'}
                                onChange={() => setKind('pattern')}
                                className="mt-1"
                            />
                            <span>
                                <span className="font-medium">
                                    {t('Pattern')}
                                </span>
                                <span className="block text-muted-foreground">
                                    {t(
                                        'A starting point. Each time it is added, the page gets its own copy to change.',
                                    )}
                                </span>
                            </span>
                        </label>
                    </fieldset>
                    {error && (
                        <p role="alert" className="text-sm text-destructive">
                            {error}
                        </p>
                    )}
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            {t('Cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={busy || title.trim() === ''}
                        >
                            {t('Save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
