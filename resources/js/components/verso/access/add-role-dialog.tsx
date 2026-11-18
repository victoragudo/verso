import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { t } from '@/lib/i18n';
import { store } from '@/routes/admin/roles';
import type { RoleSummary } from '@/types';

const EMPTY_START = 'empty';

// Asks a name, a description and a role to copy, then creates the role.
export function AddRoleDialog({
    open,
    roles,
    onClose,
}: {
    open: boolean;
    roles: RoleSummary[];
    onClose: () => void;
}) {
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [from, setFrom] = useState(EMPTY_START);
    const [processing, setProcessing] = useState(false);

    const close = () => {
        setName('');
        setDescription('');
        setFrom(EMPTY_START);
        onClose();
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        router.post(
            store.url(),
            {
                name,
                description: description || null,
                from: from === EMPTY_START ? null : from,
            },
            {
                onSuccess: close,
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={(next) => !next && close()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('Add role')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Start empty or copy the capabilities of a role you already have.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="new-role-name">{t('Name')}</Label>
                        <Input
                            id="new-role-name"
                            value={name}
                            required
                            maxLength={120}
                            onChange={(event) => setName(event.target.value)}
                            aria-invalid={Boolean(errors.name)}
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="new-role-description">
                            {t('Description (optional)')}
                        </Label>
                        <Textarea
                            id="new-role-description"
                            value={description}
                            maxLength={500}
                            rows={3}
                            onChange={(event) =>
                                setDescription(event.target.value)
                            }
                        />
                        <InputError message={errors.description} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="new-role-from">{t('Start from')}</Label>
                        <Select value={from} onValueChange={setFrom}>
                            <SelectTrigger id="new-role-from">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={EMPTY_START}>
                                    {t('An empty role')}
                                </SelectItem>
                                {roles.map((role) => (
                                    <SelectItem
                                        key={role.slug}
                                        value={role.slug}
                                    >
                                        {role.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.from ?? errors.slug} />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={close}>
                            {t('Cancel')}
                        </Button>
                        <Button
                            type="submit"
                            disabled={processing || !name.trim()}
                        >
                            {t('Add role')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
