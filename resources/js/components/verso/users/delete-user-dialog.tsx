import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { SearchableSelect } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import { destroy } from '@/routes/admin/users';
import type { AdminUser } from '@/types';

type Option = { id: number; name: string };

// Confirms a delete and asks who receives the content of the user.
export function DeleteUserDialog({
    user,
    options,
    onClose,
}: {
    user: AdminUser | null;
    options: Option[];
    onClose: () => void;
}) {
    const form = useForm<{ reassign_to: string }>({ reassign_to: '' });
    const choiceOptions = options
        .filter((option) => option.id !== user?.id)
        .map((option) => ({ value: String(option.id), label: option.name }));

    form.transform((values) => ({
        reassign_to: values.reassign_to ? Number(values.reassign_to) : null,
    }));

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!user) {
            return;
        }

        form.delete(destroy.url(user.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <Dialog
            open={user !== null}
            onOpenChange={(open) => {
                if (!open) {
                    form.reset();
                    form.clearErrors();
                    onClose();
                }
            }}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('Delete {name}?', { name: user?.name ?? '' })}
                    </DialogTitle>
                    <DialogDescription>
                        {t(
                            'This cannot be undone. Choose who will own the posts, pages and files of this user.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="reassign-to">
                            {t('Give content to')}
                        </Label>
                        <SearchableSelect
                            id="reassign-to"
                            value={form.data.reassign_to}
                            onValueChange={(value) =>
                                form.setData('reassign_to', value)
                            }
                            options={choiceOptions}
                            placeholder={t('Choose a user')}
                            searchPlaceholder={t('Search users')}
                            emptyText={t('No user matches.')}
                            aria-invalid={Boolean(form.errors.reassign_to)}
                        />
                        <InputError message={form.errors.reassign_to} />
                        <InputError
                            message={
                                (form.errors as Record<string, string>).role
                            }
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            {t('Delete user')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
