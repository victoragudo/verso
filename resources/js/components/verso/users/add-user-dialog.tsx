import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
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
import { RoleSelect } from '@/components/verso/users/role-select';
import { t } from '@/lib/i18n';
import { invite, store } from '@/routes/admin/users';
import type { RoleOption, UserRole } from '@/types';

type Mode = 'create' | 'invite';

type UserForm = {
    name: string;
    email: string;
    role: UserRole;
    password: string;
    password_confirmation: string;
};

// Dialog to invite a user by email or to create one with a password.
export function AddUserDialog({
    mode,
    roles,
    onClose,
}: {
    mode: Mode | null;
    roles: RoleOption[];
    onClose: () => void;
}) {
    const form = useForm<UserForm>({
        name: '',
        email: '',
        role: 'author',
        password: '',
        password_confirmation: '',
    });
    const { data, setData, errors, processing } = form;
    const inviting = mode === 'invite';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        };

        if (inviting) {
            form.transform(({ name, email, role }) => ({ name, email, role }));
            form.post(invite.url(), options);
        } else {
            form.transform((values) => values);
            form.post(store.url(), options);
        }
    };

    return (
        <Dialog
            open={mode !== null}
            onOpenChange={(open) => !open && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {inviting ? t('Invite a user') : t('Add a user')}
                    </DialogTitle>
                    <DialogDescription>
                        {inviting
                            ? t('We will email a link to choose a password.')
                            : t(
                                  'The user can sign in with this password right away.',
                              )}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="grid gap-4">
                    <div className="grid gap-2">
                        <Label htmlFor="user-name">{t('Name')}</Label>
                        <Input
                            id="user-name"
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            required
                            maxLength={255}
                            autoComplete="off"
                        />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="user-email">{t('Email')}</Label>
                        <Input
                            id="user-email"
                            type="email"
                            value={data.email}
                            onChange={(event) =>
                                setData('email', event.target.value)
                            }
                            required
                            maxLength={255}
                            autoComplete="off"
                            spellCheck={false}
                        />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="user-role">{t('Role')}</Label>
                        <RoleSelect
                            id="user-role"
                            roles={roles}
                            value={data.role}
                            onChange={(role) => setData('role', role)}
                        />
                        <InputError message={errors.role} />
                    </div>
                    {!inviting && (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="user-password">
                                    {t('Password')}
                                </Label>
                                <PasswordInput
                                    id="user-password"
                                    value={data.password}
                                    onChange={(event) =>
                                        setData('password', event.target.value)
                                    }
                                    autoComplete="new-password"
                                    required
                                />
                                <InputError message={errors.password} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="user-password-confirmation">
                                    {t('Confirm password')}
                                </Label>
                                <PasswordInput
                                    id="user-password-confirmation"
                                    value={data.password_confirmation}
                                    onChange={(event) =>
                                        setData(
                                            'password_confirmation',
                                            event.target.value,
                                        )
                                    }
                                    autoComplete="new-password"
                                    required
                                />
                            </div>
                        </>
                    )}
                    <DialogFooter>
                        <Button type="submit" disabled={processing}>
                            {inviting ? t('Send invitation') : t('Add user')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
