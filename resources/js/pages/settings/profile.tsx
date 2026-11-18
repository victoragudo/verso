import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Link } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import DeleteUser from '@/components/delete-user';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { SectionCard } from '@/components/verso/ui';
import { PublicProfileCard } from '@/components/verso/users/public-profile-card';
import type { PublicProfile } from '@/components/verso/users/public-profile-card';
import { edit } from '@/routes/profile';
import type { Auth } from '@/types';
import { send } from '@/routes/verification';
import { Slot } from '@/components/verso/slots';
import { LanguageSelect } from '@/components/verso/i18n/language-select';
import { t, Trans } from '@/lib/i18n';

type PageProps = {
    auth: Auth;
};

// Account page to change the name and email, the public profile, or delete the account.
export default function Profile({
    mustVerifyEmail,
    status,
    canViewAdminBar,
    publicProfile,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    canViewAdminBar: boolean;
    publicProfile?: PublicProfile;
}) {
    const { auth } = usePage<PageProps>().props;
    const [showAdminBar, setShowAdminBar] = useState(
        auth.user.show_admin_bar !== false,
    );

    return (
        <>
            <Head title={t('Profile settings')} />

            <SectionCard
                title={t('Profile')}
                description={t('Update your name and email address.')}
            >
                <Form
                    {...ProfileController.update.form()}
                    options={{
                        preserveScroll: true,
                    }}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">{t('Name')}</Label>

                                <Input
                                    id="name"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.name}
                                    name="name"
                                    required
                                    maxLength={255}
                                    autoComplete="name"
                                    placeholder={t('Full name')}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.name}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">
                                    {t('Email address')}
                                </Label>

                                <Input
                                    id="email"
                                    type="email"
                                    className="mt-1 block w-full"
                                    defaultValue={auth.user.email}
                                    name="email"
                                    required
                                    maxLength={255}
                                    spellCheck={false}
                                    autoComplete="username"
                                    placeholder={t('Email address')}
                                />

                                <InputError
                                    className="mt-2"
                                    message={errors.email}
                                />
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="-mt-4 text-sm text-muted-foreground">
                                            <Trans
                                                text={t(
                                                    'Your email address is unverified. <link>Click here to re-send the verification email.</link>',
                                                )}
                                                components={{
                                                    link: (text) => (
                                                        <Link
                                                            href={send()}
                                                            as="button"
                                                            className="text-brand underline-offset-4 hover:underline"
                                                        >
                                                            {text}
                                                        </Link>
                                                    ),
                                                }}
                                            />
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-success-text">
                                                {t(
                                                    'A new verification link has been sent to your email address.',
                                                )}
                                            </div>
                                        )}
                                    </div>
                                )}

                            {canViewAdminBar && (
                                <div className="grid gap-2">
                                    <div className="flex items-center gap-2.5">
                                        <Checkbox
                                            id="show_admin_bar"
                                            checked={showAdminBar}
                                            aria-describedby="show_admin_bar-help"
                                            onCheckedChange={(checked) =>
                                                setShowAdminBar(
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <input
                                            type="hidden"
                                            name="show_admin_bar"
                                            value={showAdminBar ? '1' : '0'}
                                        />
                                        <Label
                                            htmlFor="show_admin_bar"
                                            className="font-normal"
                                        >
                                            {t('Show admin bar on the site')}
                                        </Label>
                                    </div>
                                    <p
                                        id="show_admin_bar-help"
                                        className="text-sm text-muted-foreground"
                                    >
                                        {t(
                                            'A small bar on top of public pages with links to the admin.',
                                        )}
                                    </p>
                                    <InputError
                                        message={errors.show_admin_bar}
                                    />
                                </div>
                            )}

                            <div className="flex items-center gap-4">
                                <Button
                                    disabled={processing}
                                    data-test="update-profile-button"
                                >
                                    {t('Save')}
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </SectionCard>

            {publicProfile && <PublicProfileCard profile={publicProfile} />}

            <SectionCard
                title={t('Language')}
                description={t(
                    'The language of the admin, your emails and notifications. The site keeps its own language.',
                )}
            >
                <LanguageSelect withSiteChoice showLabel />
            </SectionCard>

            <Slot name="profile-sections" context={{}} />

            <SectionCard>
                <DeleteUser />
            </SectionCard>
        </>
    );
}

Profile.layout = () => ({
    breadcrumbs: [
        {
            title: t('Profile settings'),
            href: edit(),
        },
    ],
});
