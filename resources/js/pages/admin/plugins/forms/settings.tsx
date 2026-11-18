import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    PageHeader,
    SearchableSelect,
    SectionCard,
} from '@/components/verso/ui';
import { msg, t } from '@/lib/i18n';

type Settings = {
    retention_days: number;
    max_links: number;
    min_seconds: number;
    blocked_words: string;
    captcha_provider: string;
    captcha_site_key: string;
    captcha_secret: string;
    has_captcha_secret: boolean;
};

type Props = {
    settings: Settings;
    providers: { value: string; label: string }[];
    urls: { update: string; forms: string };
};

type NumberKey = 'retention_days' | 'max_links' | 'min_seconds';

const NUMBER_FIELDS: {
    key: NumberKey;
    label: string;
    help: string;
    max: number;
}[] = [
    {
        key: 'retention_days',
        label: msg('forms::Keep messages for (days)'),
        help: msg('forms::0 keeps messages until you delete them.'),
        max: 3650,
    },
    {
        key: 'min_seconds',
        label: msg('forms::Fastest human send (seconds)'),
        help: msg('forms::Faster sends are treated as bots.'),
        max: 120,
    },
    {
        key: 'max_links',
        label: msg('forms::Links that mark spam'),
        help: msg(
            'forms::A message with this many links goes to spam. 0 turns it off.',
        ),
        max: 100,
    },
];

// Settings shared by every form: how long messages are kept and how spam is caught.
export default function FormsSettings({ settings, providers, urls }: Props) {
    const form = useForm({ ...settings, forget_secret: false });
    const { data, setData, errors, processing } = form;
    const usesCaptcha = data.captcha_provider !== 'none';

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(urls.update, {
            preserveScroll: true,
            onSuccess: () => setData('captcha_secret', ''),
        });
    };

    return (
        <>
            <Head title={t('forms::Form settings')} />
            <PageHeader
                icon="settings"
                title={t('forms::Form settings')}
                description={t(
                    'forms::Privacy and spam protection for all forms.',
                )}
                actions={
                    <Button asChild variant="outline">
                        <Link href={urls.forms}>{t('forms::Forms')}</Link>
                    </Button>
                }
            />
            <form onSubmit={submit} className="section-grid">
                <SectionCard
                    title={t('forms::Privacy and spam checks')}
                    description={t(
                        'forms::These checks run on your own site. Nothing is sent to other companies.',
                    )}
                >
                    <div className="grid gap-x-4 gap-y-2 sm:grid-cols-3">
                        {NUMBER_FIELDS.map((field) => (
                            <div
                                key={field.key}
                                className="row-span-3 grid grid-rows-subgrid max-sm:pb-3"
                            >
                                <Label htmlFor={field.key}>
                                    {t(field.label)}
                                </Label>
                                <Input
                                    id={field.key}
                                    type="number"
                                    inputMode="numeric"
                                    min={0}
                                    max={field.max}
                                    className="tabular-nums"
                                    value={data[field.key]}
                                    onChange={(event) =>
                                        setData(
                                            field.key,
                                            Number(event.target.value),
                                        )
                                    }
                                    aria-describedby={`${field.key}-help`}
                                    aria-invalid={Boolean(errors[field.key])}
                                />
                                <p
                                    id={`${field.key}-help`}
                                    className="text-xs text-muted-foreground"
                                >
                                    {t(field.help)}
                                </p>
                                <InputError message={errors[field.key]} />
                            </div>
                        ))}
                        <div className="grid gap-2 sm:col-span-3">
                            <Label htmlFor="blocked_words">
                                {t('forms::Blocked words')}
                            </Label>
                            <Textarea
                                id="blocked_words"
                                rows={5}
                                value={data.blocked_words}
                                onChange={(event) =>
                                    setData('blocked_words', event.target.value)
                                }
                                aria-describedby="blocked_words-help"
                            />
                            <p
                                id="blocked_words-help"
                                className="text-xs text-muted-foreground"
                            >
                                {t(
                                    'forms::One word or phrase per line. Messages that contain one go to spam.',
                                )}
                            </p>
                            <InputError message={errors.blocked_words} />
                        </div>
                    </div>
                </SectionCard>

                <SectionCard
                    title={t('forms::Captcha (optional)')}
                    description={t(
                        'forms::Add Cloudflare Turnstile or hCaptcha for sites with a lot of spam. The visitor’s browser then talks to that service.',
                    )}
                >
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2 sm:col-span-2">
                            <Label htmlFor="captcha_provider">
                                {t('forms::Service')}
                            </Label>
                            <SearchableSelect
                                id="captcha_provider"
                                value={data.captcha_provider}
                                onValueChange={(value) =>
                                    setData('captcha_provider', value)
                                }
                                options={providers}
                                className="w-full"
                            />
                        </div>
                        {usesCaptcha && (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="captcha_site_key">
                                        {t('forms::Site key')}
                                    </Label>
                                    <Input
                                        id="captcha_site_key"
                                        value={data.captcha_site_key}
                                        autoComplete="off"
                                        spellCheck={false}
                                        className="font-mono text-sm"
                                        onChange={(event) =>
                                            setData(
                                                'captcha_site_key',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            errors.captcha_site_key,
                                        )}
                                    />
                                    <InputError
                                        message={errors.captcha_site_key}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="captcha_secret">
                                        {t('forms::Secret key')}
                                    </Label>
                                    <Input
                                        id="captcha_secret"
                                        type="password"
                                        value={data.captcha_secret}
                                        autoComplete="new-password"
                                        spellCheck={false}
                                        className="font-mono text-sm"
                                        placeholder={
                                            settings.has_captcha_secret
                                                ? t(
                                                      'forms::Saved. Type to replace it.',
                                                  )
                                                : ''
                                        }
                                        onChange={(event) =>
                                            setData(
                                                'captcha_secret',
                                                event.target.value,
                                            )
                                        }
                                        aria-invalid={Boolean(
                                            errors.captcha_secret,
                                        )}
                                    />
                                    <InputError
                                        message={errors.captcha_secret}
                                    />
                                </div>
                            </>
                        )}
                        {!usesCaptcha && settings.has_captcha_secret && (
                            <label className="flex min-h-10 items-center gap-2.5 text-sm sm:col-span-2">
                                <Checkbox
                                    checked={data.forget_secret}
                                    onCheckedChange={(value) =>
                                        setData('forget_secret', value === true)
                                    }
                                />
                                {t('forms::Also delete the saved secret key')}
                            </label>
                        )}
                    </div>
                </SectionCard>

                <div>
                    <Button type="submit" disabled={processing}>
                        {t('Save settings')}
                    </Button>
                </div>
            </form>
        </>
    );
}

FormsSettings.layout = {
    breadcrumbs: [
        { title: msg('forms::Forms'), href: '/admin/forms' },
        { title: msg('Settings'), href: '/admin/forms/settings' },
    ],
};
