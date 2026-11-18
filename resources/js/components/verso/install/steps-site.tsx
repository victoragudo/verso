import { HardDrive, Mail, Server } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { OptionSelect } from '@/components/verso/ui/option-select';
import { SearchableSelect } from '@/components/verso/ui/searchable-select';
import { msg, t } from '@/lib/i18n';
import {
    ChoiceCards,
    Field,
    FormSection,
    ResultBox,
    SecretInput,
} from './fields';
import { installRequest } from './install-api';
import type { TestResult } from './install-api';
import { PasswordStrengthMeter } from './password-strength-meter';
import { StepFrame } from './step-frame';
import { useStepForm } from './use-step-form';

type Values = Record<string, unknown>;

const text = (value: unknown): string =>
    typeof value === 'string' || typeof value === 'number' ? String(value) : '';

// Step 4: site details and the first admin account.
export function SiteStep({
    values,
    savedSecrets,
    locales,
    languages,
    timezones,
    passwordMinLength,
}: {
    values: Values;
    savedSecrets: string[];
    locales: string[];
    languages: Record<string, string>;
    timezones: string[];
    passwordMinLength: number;
}) {
    const { form, formRef, submit } = useStepForm('site', {
        site_name: text(values.site_name),
        tagline: text(values.tagline),
        site_url: text(values.site_url),
        locale: text(values.locale) || 'en',
        timezone: text(values.timezone) || 'UTC',
        admin_name: text(values.admin_name),
        admin_email: text(values.admin_email),
        admin_password: '',
        admin_password_confirmation: '',
        keep: savedSecrets,
    });
    const timezoneOptions = useMemo(
        () =>
            timezones.map((zone) => ({
                value: zone,
                label: zone.replaceAll('_', ' '),
                group: zone.includes('/') ? zone.split('/')[0] : t('Other'),
            })),
        [timezones],
    );
    const context = useMemo(
        () => [
            form.data.admin_email,
            form.data.admin_name,
            form.data.site_name,
        ],
        [form.data.admin_email, form.data.admin_name, form.data.site_name],
    );
    const passwordSaved = savedSecrets.includes('admin_password');

    return (
        <StepFrame
            title={t('Site and admin account')}
            description={t(
                'Name your site and create the account you will use to manage it.',
            )}
            back="database"
            formRef={formRef}
            onSubmit={submit}
            processing={form.processing}
        >
            <FormSection title={t('Your site')}>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label={t('Site name')} error={form.errors.site_name}>
                        <Input
                            name="site_name"
                            value={form.data.site_name}
                            onChange={(event) =>
                                form.setData('site_name', event.target.value)
                            }
                            autoComplete="organization"
                            placeholder={t('Harbor Lane Bakery')}
                            maxLength={120}
                            required
                        />
                    </Field>
                    <Field
                        label={t('Tagline')}
                        optional
                        error={form.errors.tagline}
                    >
                        <Input
                            name="tagline"
                            value={form.data.tagline}
                            onChange={(event) =>
                                form.setData('tagline', event.target.value)
                            }
                            placeholder={t('Fresh bread every morning')}
                            maxLength={200}
                        />
                    </Field>
                    <Field
                        label={t('Site address')}
                        error={form.errors.site_url}
                        hint={t(
                            'We filled in the address you used to open this page.',
                        )}
                        className="sm:col-span-2"
                    >
                        <Input
                            name="site_url"
                            type="url"
                            inputMode="url"
                            value={form.data.site_url}
                            onChange={(event) =>
                                form.setData('site_url', event.target.value)
                            }
                            autoComplete="url"
                            spellCheck={false}
                            required
                        />
                    </Field>
                    <Field
                        label={t('Site language')}
                        error={form.errors.locale}
                    >
                        <SearchableSelect
                            value={form.data.locale}
                            onValueChange={(locale) =>
                                form.setData('locale', locale)
                            }
                            options={locales.map((locale) => ({
                                value: locale,
                                label: languages[locale] ?? locale,
                                keywords: [locale],
                            }))}
                            searchPlaceholder={t('Search languages')}
                        />
                    </Field>
                    <Field
                        label={t('Time zone')}
                        error={form.errors.timezone}
                        hint={t('Dates on the site are shown in this zone.')}
                    >
                        <SearchableSelect
                            value={form.data.timezone}
                            onValueChange={(timezone) =>
                                form.setData('timezone', timezone)
                            }
                            options={timezoneOptions}
                            searchPlaceholder={t('Search a city or region')}
                        />
                    </Field>
                </div>
            </FormSection>
            <FormSection
                title={t('Admin account')}
                description={t('You sign in with this email and password.')}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label={t('Your name')}
                        error={form.errors.admin_name}
                    >
                        <Input
                            name="admin_name"
                            value={form.data.admin_name}
                            onChange={(event) =>
                                form.setData('admin_name', event.target.value)
                            }
                            autoComplete="name"
                            required
                        />
                    </Field>
                    <Field label={t('Email')} error={form.errors.admin_email}>
                        <Input
                            name="admin_email"
                            type="email"
                            value={form.data.admin_email}
                            onChange={(event) =>
                                form.setData('admin_email', event.target.value)
                            }
                            autoComplete="username"
                            spellCheck={false}
                            required
                        />
                    </Field>
                    <Field
                        label={t('Password')}
                        error={form.errors.admin_password}
                    >
                        <SecretInput
                            name="admin_password"
                            value={form.data.admin_password}
                            saved={passwordSaved}
                            autoComplete="new-password"
                            onChange={(value) =>
                                form.setData('admin_password', value)
                            }
                        />
                    </Field>
                    <Field label={t('Confirm password')}>
                        <SecretInput
                            name="admin_password_confirmation"
                            value={form.data.admin_password_confirmation}
                            saved={passwordSaved}
                            autoComplete="new-password"
                            onChange={(value) =>
                                form.setData(
                                    'admin_password_confirmation',
                                    value,
                                )
                            }
                        />
                    </Field>
                    <div className="sm:col-span-2">
                        <PasswordStrengthMeter
                            id="admin-password-strength"
                            password={form.data.admin_password}
                            minLength={passwordMinLength}
                            context={context}
                        />
                    </div>
                </div>
            </FormSection>
        </StepFrame>
    );
}

type ServiceOptions = {
    mailers: string[];
    smtp_encryptions: string[];
    cache_drivers: string[];
    session_drivers: string[];
    queue_drivers: string[];
    redis_available: boolean;
    s3_available: boolean;
};

const MAILERS: Record<string, { label: string; description: string }> = {
    log: {
        label: msg('Log only'),
        description: msg(
            'Emails are written to the log. Good while you build the site.',
        ),
    },
    smtp: {
        label: msg('SMTP server'),
        description: msg('Send through your mail provider.'),
    },
    sendmail: {
        label: msg('Sendmail'),
        description: msg('Use the mail program of this server.'),
    },
};

// Step 5: mail, cache, sessions, queue and file storage. Everything has a safe default.
export function ServicesStep({
    values,
    savedSecrets,
    services,
}: {
    values: Values;
    savedSecrets: string[];
    services: ServiceOptions;
}) {
    const { form, formRef, submit } = useStepForm('services', {
        mailer: text(values.mailer) || 'log',
        mail_host: text(values.mail_host),
        mail_port: text(values.mail_port),
        mail_encryption: text(values.mail_encryption) || 'tls',
        mail_username: text(values.mail_username),
        mail_password: '',
        mail_from_address: text(values.mail_from_address),
        mail_from_name: text(values.mail_from_name),
        cache_driver: text(values.cache_driver) || 'file',
        session_driver: text(values.session_driver) || 'file',
        queue_driver: text(values.queue_driver) || 'sync',
        redis_host: text(values.redis_host),
        redis_port: text(values.redis_port),
        redis_password: '',
        storage: text(values.storage) || 'local',
        s3_key: text(values.s3_key),
        s3_secret: '',
        s3_region: text(values.s3_region),
        s3_bucket: text(values.s3_bucket),
        s3_endpoint: text(values.s3_endpoint),
        s3_path_style: Boolean(values.s3_path_style),
        keep: savedSecrets,
        test_email: '',
    });
    const [busy, setBusy] = useState<string | null>(null);
    const [results, setResults] = useState<Record<string, TestResult>>({});
    const usesRedis = [
        form.data.cache_driver,
        form.data.session_driver,
        form.data.queue_driver,
    ].includes('redis');

    const runTest = async (key: 'mail' | 'redis' | 'storage') => {
        setBusy(key);

        try {
            const result = await installRequest<TestResult>(
                `/install/test/${key}`,
                'POST',
                form.data,
            );
            setResults((current) => ({ ...current, [key]: result }));
        } catch (error) {
            setResults((current) => ({
                ...current,
                [key]: { ok: false, message: (error as Error).message },
            }));
        } finally {
            setBusy(null);
        }
    };

    const skip = () => {
        form.transform(() => ({
            mailer: 'log',
            cache_driver: 'file',
            session_driver: 'file',
            queue_driver: 'sync',
            storage: 'local',
        }));
        submit();
    };

    const driverSelect = (
        field: 'cache_driver' | 'session_driver' | 'queue_driver',
        label: string,
        options: string[],
    ) => (
        <Field label={label} error={form.errors[field]}>
            <OptionSelect
                name={field}
                value={form.data[field]}
                onValueChange={(option) => form.setData(field, option)}
                options={options.map((option) => {
                    const missing =
                        option === 'redis' && !services.redis_available;

                    return {
                        value: option,
                        label: missing
                            ? t('{driver} (needs a Redis client)', {
                                  driver: option,
                              })
                            : option,
                        disabled: missing,
                    };
                })}
            />
        </Field>
    );

    return (
        <StepFrame
            title={t('Services')}
            description={t(
                'Optional. The defaults work everywhere: mail goes to the log, and cache, sessions and queue use files.',
            )}
            back="site"
            formRef={formRef}
            onSubmit={(event) => {
                form.transform((data) => data);
                submit(event);
            }}
            processing={form.processing}
            extraActions={
                <Button
                    type="button"
                    variant="outline"
                    onClick={skip}
                    disabled={form.processing}
                >
                    {t('Skip, use defaults')}
                </Button>
            }
        >
            <FormSection
                title={t('Email')}
                description={t(
                    'Used for password resets, invitations and notifications.',
                )}
            >
                <ChoiceCards
                    legend={t('How Verso sends email')}
                    name="mailer"
                    value={form.data.mailer}
                    onChange={(mailer) => form.setData('mailer', mailer)}
                    columns={3}
                    error={form.errors.mailer}
                    options={services.mailers.map((mailer) => ({
                        value: mailer,
                        label: MAILERS[mailer]
                            ? t(MAILERS[mailer].label)
                            : mailer,
                        description: MAILERS[mailer]
                            ? t(MAILERS[mailer].description)
                            : undefined,
                    }))}
                />
                {form.data.mailer === 'smtp' && (
                    <div className="grid gap-4 sm:grid-cols-6">
                        <Field
                            label={t('SMTP host')}
                            error={form.errors.mail_host}
                            className="sm:col-span-3"
                        >
                            <Input
                                name="mail_host"
                                value={form.data.mail_host}
                                onChange={(event) =>
                                    form.setData(
                                        'mail_host',
                                        event.target.value,
                                    )
                                }
                                placeholder="smtp.example.com"
                                spellCheck={false}
                                autoCapitalize="none"
                            />
                        </Field>
                        <Field
                            label={t('Port')}
                            error={form.errors.mail_port}
                            className="sm:col-span-1"
                        >
                            <Input
                                name="mail_port"
                                inputMode="numeric"
                                value={form.data.mail_port}
                                onChange={(event) =>
                                    form.setData(
                                        'mail_port',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label={t('Encryption')}
                            error={form.errors.mail_encryption}
                            className="sm:col-span-2"
                        >
                            <OptionSelect
                                name="mail_encryption"
                                value={form.data.mail_encryption}
                                onValueChange={(option) =>
                                    form.setData('mail_encryption', option)
                                }
                                options={services.smtp_encryptions.map(
                                    (option) => ({
                                        value: option,
                                        label:
                                            option === 'none'
                                                ? t('None')
                                                : option.toUpperCase(),
                                    }),
                                )}
                            />
                        </Field>
                        <Field
                            label={t('Username')}
                            optional
                            error={form.errors.mail_username}
                            className="sm:col-span-3"
                        >
                            <Input
                                name="mail_username"
                                value={form.data.mail_username}
                                onChange={(event) =>
                                    form.setData(
                                        'mail_username',
                                        event.target.value,
                                    )
                                }
                                autoComplete="off"
                                spellCheck={false}
                                autoCapitalize="none"
                            />
                        </Field>
                        <Field
                            label={t('Password')}
                            optional
                            error={form.errors.mail_password}
                            className="sm:col-span-3"
                        >
                            <SecretInput
                                name="mail_password"
                                value={form.data.mail_password}
                                saved={savedSecrets.includes('mail_password')}
                                autoComplete="off"
                                onChange={(value) =>
                                    form.setData('mail_password', value)
                                }
                            />
                        </Field>
                        <Field
                            label={t('Sender address')}
                            error={form.errors.mail_from_address}
                            className="sm:col-span-3"
                        >
                            <Input
                                name="mail_from_address"
                                type="email"
                                value={form.data.mail_from_address}
                                onChange={(event) =>
                                    form.setData(
                                        'mail_from_address',
                                        event.target.value,
                                    )
                                }
                                placeholder="hello@example.com"
                                autoComplete="off"
                                spellCheck={false}
                            />
                        </Field>
                        <Field
                            label={t('Sender name')}
                            optional
                            error={form.errors.mail_from_name}
                            className="sm:col-span-3"
                        >
                            <Input
                                name="mail_from_name"
                                value={form.data.mail_from_name}
                                onChange={(event) =>
                                    form.setData(
                                        'mail_from_name',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    </div>
                )}
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <Field
                        label={t('Send a test email to')}
                        optional
                        className="sm:flex-1"
                    >
                        <Input
                            name="test_email"
                            type="email"
                            value={form.data.test_email}
                            onChange={(event) =>
                                form.setData('test_email', event.target.value)
                            }
                            placeholder="you@example.com"
                            autoComplete="email"
                            spellCheck={false}
                        />
                    </Field>
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => runTest('mail')}
                        disabled={busy !== null || form.data.test_email === ''}
                    >
                        {busy === 'mail' ? (
                            <Spinner />
                        ) : (
                            <Mail aria-hidden="true" />
                        )}
                        {t('Send test email')}
                    </Button>
                </div>
                {results.mail && (
                    <ResultBox ok={results.mail.ok}>
                        {results.mail.message}
                    </ResultBox>
                )}
            </FormSection>

            <FormSection
                title={t('Cache, sessions and queue')}
                description={t(
                    'Files work on every host. Use the database or Redis for busy sites.',
                )}
            >
                <div className="grid gap-4 sm:grid-cols-3">
                    {driverSelect(
                        'cache_driver',
                        t('Cache'),
                        services.cache_drivers,
                    )}
                    {driverSelect(
                        'session_driver',
                        t('Sessions'),
                        services.session_drivers,
                    )}
                    {driverSelect(
                        'queue_driver',
                        t('Queue'),
                        services.queue_drivers,
                    )}
                </div>
                {usesRedis && (
                    <div className="grid gap-4 sm:grid-cols-6">
                        <Field
                            label={t('Redis host')}
                            error={form.errors.redis_host}
                            className="sm:col-span-3"
                        >
                            <Input
                                name="redis_host"
                                value={form.data.redis_host}
                                onChange={(event) =>
                                    form.setData(
                                        'redis_host',
                                        event.target.value,
                                    )
                                }
                                autoComplete="off"
                                spellCheck={false}
                            />
                        </Field>
                        <Field
                            label={t('Port')}
                            error={form.errors.redis_port}
                            className="sm:col-span-1"
                        >
                            <Input
                                name="redis_port"
                                inputMode="numeric"
                                value={form.data.redis_port}
                                onChange={(event) =>
                                    form.setData(
                                        'redis_port',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                        <Field
                            label={t('Password')}
                            optional
                            className="sm:col-span-2"
                        >
                            <SecretInput
                                name="redis_password"
                                value={form.data.redis_password}
                                saved={savedSecrets.includes('redis_password')}
                                autoComplete="off"
                                onChange={(value) =>
                                    form.setData('redis_password', value)
                                }
                            />
                        </Field>
                        <div className="sm:col-span-6">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => runTest('redis')}
                                disabled={busy !== null}
                            >
                                {busy === 'redis' ? (
                                    <Spinner />
                                ) : (
                                    <Server aria-hidden="true" />
                                )}
                                {t('Test Redis')}
                            </Button>
                        </div>
                        {results.redis && (
                            <div className="sm:col-span-6">
                                <ResultBox ok={results.redis.ok}>
                                    {results.redis.message}
                                </ResultBox>
                            </div>
                        )}
                    </div>
                )}
            </FormSection>

            <FormSection
                title={t('File storage')}
                description={t('Where uploaded images and files are kept.')}
            >
                <ChoiceCards
                    legend={t('Storage')}
                    name="storage"
                    value={form.data.storage}
                    onChange={(storage) => form.setData('storage', storage)}
                    error={form.errors.storage}
                    options={[
                        {
                            value: 'local',
                            label: t('This server'),
                            description: t(
                                'Files go to storage/app/public, served from /storage.',
                            ),
                        },
                        {
                            value: 's3',
                            label: t('S3 compatible bucket'),
                            description: services.s3_available
                                ? t(
                                      'Amazon S3, Cloudflare R2, MinIO and others.',
                                  )
                                : t('Needs the {package} package.', {
                                      package: 'league/flysystem-aws-s3-v3',
                                  }),
                            disabled: !services.s3_available,
                        },
                    ]}
                />
                {form.data.storage === 's3' && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field
                            label={t('Access key')}
                            error={form.errors.s3_key}
                        >
                            <Input
                                name="s3_key"
                                value={form.data.s3_key}
                                onChange={(event) =>
                                    form.setData('s3_key', event.target.value)
                                }
                                autoComplete="off"
                                spellCheck={false}
                            />
                        </Field>
                        <Field
                            label={t('Secret key')}
                            error={form.errors.s3_secret}
                        >
                            <SecretInput
                                name="s3_secret"
                                value={form.data.s3_secret}
                                saved={savedSecrets.includes('s3_secret')}
                                autoComplete="off"
                                onChange={(value) =>
                                    form.setData('s3_secret', value)
                                }
                            />
                        </Field>
                        <Field
                            label={t('Bucket')}
                            error={form.errors.s3_bucket}
                        >
                            <Input
                                name="s3_bucket"
                                value={form.data.s3_bucket}
                                onChange={(event) =>
                                    form.setData(
                                        's3_bucket',
                                        event.target.value,
                                    )
                                }
                                spellCheck={false}
                            />
                        </Field>
                        <Field
                            label={t('Region')}
                            error={form.errors.s3_region}
                        >
                            <Input
                                name="s3_region"
                                value={form.data.s3_region}
                                onChange={(event) =>
                                    form.setData(
                                        's3_region',
                                        event.target.value,
                                    )
                                }
                                spellCheck={false}
                            />
                        </Field>
                        <Field
                            label={t('Endpoint')}
                            optional
                            error={form.errors.s3_endpoint}
                            hint={t('Leave empty for Amazon S3.')}
                            className="sm:col-span-2"
                        >
                            <Input
                                name="s3_endpoint"
                                type="url"
                                value={form.data.s3_endpoint}
                                onChange={(event) =>
                                    form.setData(
                                        's3_endpoint',
                                        event.target.value,
                                    )
                                }
                                autoComplete="off"
                                spellCheck={false}
                            />
                        </Field>
                        <label className="flex items-center gap-2 text-sm sm:col-span-2">
                            <input
                                type="checkbox"
                                checked={form.data.s3_path_style}
                                onChange={(event) =>
                                    form.setData(
                                        's3_path_style',
                                        event.target.checked,
                                    )
                                }
                            />
                            {t('Use path style addresses (needed by MinIO)')}
                        </label>
                        <div className="sm:col-span-2">
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => runTest('storage')}
                                disabled={busy !== null}
                            >
                                {busy === 'storage' ? (
                                    <Spinner />
                                ) : (
                                    <HardDrive aria-hidden="true" />
                                )}
                                {t('Test write')}
                            </Button>
                        </div>
                        {results.storage && (
                            <div className="sm:col-span-2">
                                <ResultBox ok={results.storage.ok}>
                                    {results.storage.message}
                                </ResultBox>
                            </div>
                        )}
                    </div>
                )}
            </FormSection>
        </StepFrame>
    );
}

type Choice = {
    slug: string;
    name: string;
    description: string;
    version?: string;
    recommended?: boolean;
};

// Step 6: demo content or an empty site, the theme and plugins.
export function ContentStep({
    values,
    themes,
    plugins,
}: {
    values: Values;
    themes: Choice[];
    plugins: Choice[];
}) {
    const { form, formRef, submit } = useStepForm('content', {
        demo: Boolean(values.demo),
        theme:
            text(values.theme) ||
            themes.find((theme) => theme.recommended)?.slug ||
            'default',
        plugins: Array.isArray(values.plugins)
            ? values.plugins.map(String)
            : ([] as string[]),
    });

    const togglePlugin = (slug: string, checked: boolean) =>
        form.setData(
            'plugins',
            checked
                ? [...form.data.plugins, slug]
                : form.data.plugins.filter((item) => item !== slug),
        );

    return (
        <StepFrame
            title={t('Content')}
            description={t(
                'Start with demo content to see Verso at work, or with an empty site.',
            )}
            back="services"
            formRef={formRef}
            onSubmit={submit}
            processing={form.processing}
        >
            <ChoiceCards
                legend={t('Starting content')}
                name="demo"
                value={form.data.demo ? 'demo' : 'empty'}
                onChange={(choice) => form.setData('demo', choice === 'demo')}
                error={form.errors.demo}
                options={[
                    {
                        value: 'empty',
                        label: t('Start empty'),
                        description: t(
                            'A clean site. Add your own posts and pages.',
                        ),
                    },
                    {
                        value: 'demo',
                        label: t('Add demo content'),
                        description: t(
                            'Posts, pages, photos, menus and comments you can edit or delete.',
                        ),
                    },
                ]}
            />
            <ChoiceCards
                legend={t('Theme')}
                name="theme"
                value={form.data.theme}
                onChange={(theme) => form.setData('theme', theme)}
                columns={3}
                error={form.errors.theme}
                options={themes.map((theme) => ({
                    value: theme.slug,
                    label: theme.name,
                    description: theme.description,
                    badge: theme.recommended ? t('Recommended') : undefined,
                }))}
            />
            <fieldset className="grid gap-2">
                <legend className="mb-1.5 text-sm font-medium">
                    {t('Plugins')}
                </legend>
                {plugins.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        {t('No plugins found.')}
                    </p>
                )}
                <div className="grid gap-2 sm:grid-cols-2">
                    {plugins.map((plugin) => (
                        <label
                            key={plugin.slug}
                            className="flex min-h-11 cursor-pointer gap-3 rounded-lg border border-zinc-200 bg-white p-3 text-sm shadow-xs transition-colors hover:border-zinc-400 dark:hover:border-zinc-600 has-[:checked]:border-zinc-900 has-[:focus-visible]:ring-[3px] has-[:focus-visible]:ring-ring/50 dark:border-zinc-800 dark:bg-zinc-900 dark:has-[:checked]:border-zinc-100"
                        >
                            <input
                                type="checkbox"
                                name="plugins[]"
                                value={plugin.slug}
                                checked={form.data.plugins.includes(
                                    plugin.slug,
                                )}
                                onChange={(event) =>
                                    togglePlugin(
                                        plugin.slug,
                                        event.target.checked,
                                    )
                                }
                                className="mt-0.5 size-4 shrink-0 accent-zinc-900 dark:accent-zinc-100"
                            />
                            <span className="grid gap-0.5">
                                <span className="font-medium text-zinc-900 dark:text-zinc-100">
                                    {plugin.name}
                                </span>
                                <span className="text-xs text-muted-foreground">
                                    {plugin.description}
                                </span>
                            </span>
                        </label>
                    ))}
                </div>
                {form.errors.plugins && (
                    <p className="text-sm text-red-700 dark:text-red-400">
                        {form.errors.plugins}
                    </p>
                )}
            </fieldset>
        </StepFrame>
    );
}
