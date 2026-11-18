import { router } from '@inertiajs/react';
import { useId, useRef, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { SectionCard } from '@/components/verso/ui';
import { formatBytes, t } from '@/lib/i18n';
import {
    destroy as removeAvatar,
    store as storeAvatar,
} from '@/routes/profile/avatar';
import { update as updatePublic } from '@/routes/profile/public';

export type PublicProfile = {
    bio: string;
    socialLinks: string[];
    avatar: { id: number; url: string } | null;
    canUploadAvatar: boolean;
    authorUrl: string | null;
    limits: { bioMax: number; socialLinksMax: number; avatarMaxKb: number };
};

// Links shown in the form: the saved ones and one empty row to add another, up to the limit.
export function linkRows(links: string[], max: number): string[] {
    const rows = links.slice(0, max);

    return rows.length < max ? [...rows, ''] : rows;
}

// Errors of social_links.<n> by row number.
export function linkErrors(
    errors: Record<string, string | undefined>,
): Record<number, string> {
    const found: Record<number, string> = {};

    Object.entries(errors).forEach(([key, message]) => {
        const match = /^social_links\.(\d+)$/.exec(key);

        if (match && message) {
            found[Number(match[1])] = message;
        }
    });

    return found;
}

// Bio, photo and social links that themes show in the author box and on the author page.
export function PublicProfileCard({ profile }: { profile: PublicProfile }) {
    const { limits } = profile;
    const [bio, setBio] = useState(profile.bio);
    const [links, setLinks] = useState(() =>
        linkRows(profile.socialLinks, limits.socialLinksMax),
    );
    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );
    const [saving, setSaving] = useState(false);
    const [uploading, setUploading] = useState(false);
    const fileInput = useRef<HTMLInputElement>(null);
    const bioHelpId = useId();
    const avatarHelpId = useId();
    const rowErrors = linkErrors(errors);

    const save = (event: FormEvent) => {
        event.preventDefault();
        router.put(
            updatePublic.url(),
            {
                bio,
                social_links: links.filter((link) => link.trim() !== ''),
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onSuccess: () => setErrors({}),
                onError: setErrors,
            },
        );
    };

    const upload = (file: File | undefined) => {
        if (!file) {
            return;
        }

        if (file.size > limits.avatarMaxKb * 1024) {
            setErrors({
                avatar: t('The photo is too big. Use one under {size}.', {
                    size: formatBytes(limits.avatarMaxKb * 1024),
                }),
            });

            return;
        }

        router.post(
            storeAvatar.url(),
            { avatar: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onStart: () => setUploading(true),
                onFinish: () => {
                    setUploading(false);

                    if (fileInput.current) {
                        fileInput.current.value = '';
                    }
                },
                onSuccess: () => setErrors({}),
                onError: setErrors,
            },
        );
    };

    const setLink = (index: number, value: string) =>
        setLinks((current) => {
            const next = current.map((link, row) =>
                row === index ? value : link,
            );
            const last = next[next.length - 1] ?? '';

            return last.trim() !== '' && next.length < limits.socialLinksMax
                ? [...next, '']
                : next;
        });

    return (
        <SectionCard
            title={t('Public profile')}
            description={t(
                'Themes show this next to your posts and on your author page.',
            )}
            className="col-span-full"
            actions={
                profile.authorUrl && (
                    <a
                        href={profile.authorUrl}
                        className="text-sm text-brand underline-offset-4 hover:underline"
                    >
                        {t('View your author page')}
                    </a>
                )
            }
        >
            <div className="grid gap-6 @3xl/page:grid-cols-[minmax(12rem,16rem)_minmax(0,1fr)]">
                <div className="grid content-start gap-3">
                    <Label htmlFor="avatar">{t('Profile photo')}</Label>
                    {profile.avatar ? (
                        <img
                            src={profile.avatar.url}
                            alt={t('Your profile photo')}
                            width={96}
                            height={96}
                            className="size-24 rounded-full border object-cover"
                        />
                    ) : (
                        <span
                            aria-hidden="true"
                            className="flex size-24 items-center justify-center rounded-full border bg-muted text-sm text-muted-foreground"
                        >
                            {t('No photo')}
                        </span>
                    )}
                    {profile.canUploadAvatar ? (
                        <>
                            <Input
                                ref={fileInput}
                                id="avatar"
                                type="file"
                                accept="image/jpeg,image/png,image/webp,image/avif"
                                aria-describedby={avatarHelpId}
                                disabled={uploading}
                                onChange={(event) =>
                                    upload(event.target.files?.[0])
                                }
                            />
                            <p
                                id={avatarHelpId}
                                className="text-xs text-muted-foreground"
                            >
                                {uploading
                                    ? t('Uploading…')
                                    : t(
                                          'A square JPEG, PNG, WebP or AVIF photo works best. It goes to the media library.',
                                      )}
                            </p>
                        </>
                    ) : (
                        <p
                            id={avatarHelpId}
                            className="text-xs text-muted-foreground"
                        >
                            {t(
                                'Your role cannot upload files, so ask an admin to add a photo.',
                            )}
                        </p>
                    )}
                    <InputError message={errors.avatar} />
                    {profile.avatar && (
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-10 md:min-h-9"
                                onClick={() =>
                                    window.confirm(
                                        t('Remove your profile photo?'),
                                    ) &&
                                    router.delete(removeAvatar.url(), {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                {t('Remove photo')}
                            </Button>
                        </div>
                    )}
                </div>

                <form onSubmit={save} className="grid content-start gap-5">
                    <div className="grid gap-2">
                        <Label htmlFor="bio">{t('Bio')}</Label>
                        <Textarea
                            id="bio"
                            value={bio}
                            maxLength={limits.bioMax}
                            rows={5}
                            aria-describedby={bioHelpId}
                            aria-invalid={Boolean(errors.bio)}
                            onChange={(event) => setBio(event.target.value)}
                            className="max-w-prose"
                        />
                        <p
                            id={bioHelpId}
                            className="text-xs text-muted-foreground tabular-nums"
                        >
                            {t(
                                'A few lines about you. {length, number} of {max, number} characters.',
                                { length: bio.length, max: limits.bioMax },
                            )}
                        </p>
                        <InputError message={errors.bio} />
                    </div>

                    <fieldset className="grid gap-2">
                        <legend className="mb-1 text-sm font-medium">
                            {t('Social links')}
                        </legend>
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'Full addresses, like {example}. Up to {max, number}.',
                                {
                                    example: 'https://bsky.app/profile/you',
                                    max: limits.socialLinksMax,
                                },
                            )}
                        </p>
                        <ul className="auto-grid gap-2 [--grid-min:18rem]">
                            {links.map((link, index) => (
                                <li key={index} className="grid gap-1">
                                    <Label
                                        htmlFor={`social-link-${index}`}
                                        className="sr-only"
                                    >
                                        {t('Social link {number, number}', {
                                            number: index + 1,
                                        })}
                                    </Label>
                                    <Input
                                        id={`social-link-${index}`}
                                        type="url"
                                        inputMode="url"
                                        autoComplete="url"
                                        placeholder="https://"
                                        value={link}
                                        aria-invalid={Boolean(rowErrors[index])}
                                        onChange={(event) =>
                                            setLink(index, event.target.value)
                                        }
                                    />
                                    <InputError message={rowErrors[index]} />
                                </li>
                            ))}
                        </ul>
                        <InputError message={errors.social_links} />
                    </fieldset>

                    <div>
                        <Button
                            type="submit"
                            disabled={saving}
                            className="min-h-10 md:min-h-9"
                        >
                            {saving ? t('Saving…') : t('Save public profile')}
                        </Button>
                    </div>
                </form>
            </div>
        </SectionCard>
    );
}
