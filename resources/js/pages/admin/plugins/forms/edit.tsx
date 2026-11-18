import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useId, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    moveField,
    newField,
    parseEmails,
    placeholders,
    slugFromTitle,
} from '@/components/verso/forms/builder';
import { FieldEditor } from '@/components/verso/forms/field-editor';
import type {
    BuilderLimits,
    FormField,
    FormFieldType,
    FormSettings,
} from '@/components/verso/forms/types';
import { EntryStatusBadge } from '@/components/verso/content/status-badge';
import {
    CopyShortcode,
    EmptyState,
    PageHeader,
    SearchableSelect,
    SectionCard,
} from '@/components/verso/ui';
import { msg, t, Trans } from '@/lib/i18n';
import type { EntryStatus } from '@/types';

type Props = {
    form: {
        id: number;
        title: string;
        slug: string;
        fields: FormField[];
        settings: FormSettings;
    } | null;
    defaults: FormSettings;
    fieldTypes: { value: string; label: string }[];
    limits: BuilderLimits;
    embed: string | null;
    shortcode: string | null;
    bladeSnippet: string | null;
    usedIn: UsedInEntry[];
    urls: {
        index: string;
        save: string;
        destroy: string | null;
        submissions: string | null;
    };
};

type UsedInEntry = {
    id: number;
    title: string;
    type: string;
    status: string;
    edit_url: string;
    url: string;
};

type BuilderData = {
    title: string;
    slug: string;
    fields: FormField[];
    settings: FormSettings;
};

// The form builder: fields on the left, what happens after sending on the right.
export default function FormBuilder({
    form: saved,
    defaults,
    fieldTypes,
    limits,
    embed,
    shortcode,
    bladeSnippet,
    usedIn,
    urls,
}: Props) {
    const idPrefix = useId();
    const [nextId, setNextId] = useState(1);
    const [newType, setNewType] = useState<string>('text');
    const [slugTouched, setSlugTouched] = useState(saved !== null);
    const form = useForm<BuilderData>({
        title: saved?.title ?? '',
        slug: saved?.slug ?? '',
        fields: saved?.fields ?? [],
        settings: saved?.settings ?? defaults,
    });
    const { data, setData, errors, processing } = form;
    const allErrors = errors as Record<string, string | undefined>;

    const setFields = (fields: FormField[]) => setData('fields', fields);
    const setSetting = <K extends keyof FormSettings>(
        key: K,
        value: FormSettings[K],
    ) => setData('settings', { ...data.settings, [key]: value });

    const addField = () => {
        const field = newField(
            newType as FormFieldType,
            data.fields.map((item) => item.name),
            `f_${idPrefix.replace(/[^a-z0-9]/gi, '')}_${nextId}`,
        );
        setNextId(nextId + 1);
        setFields([...data.fields, field]);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (saved) {
            form.put(urls.save, { preserveScroll: true });
        } else {
            form.post(urls.save);
        }
    };

    const remove = () => {
        if (
            urls.destroy &&
            window.confirm(
                t(
                    'forms::Delete {title}? Its messages and uploaded files are deleted too.',
                    { title: saved?.title ?? '' },
                ),
            )
        ) {
            router.delete(urls.destroy);
        }
    };

    const title = saved
        ? t('forms::Edit {title}', { title: saved.title })
        : t('forms::New form');

    return (
        <>
            <Head title={title} />
            <PageHeader
                icon="forms"
                title={title}
                description={t(
                    'forms::Fields people fill in, and what happens when they send the form.',
                )}
                actions={
                    <>
                        {urls.submissions && (
                            <Button asChild variant="outline">
                                <Link href={urls.submissions}>
                                    {t('forms::Messages')}
                                </Link>
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href={urls.index}>
                                {t('forms::All forms')}
                            </Link>
                        </Button>
                    </>
                }
            />

            <form
                onSubmit={submit}
                className="grid gap-6 @3xl/page:grid-cols-[minmax(0,1fr)_clamp(20rem,24cqi,30rem)]"
            >
                <div className="grid min-w-0 content-start gap-4">
                    <SectionCard title={t('forms::Details')}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="form-title">{t('Title')}</Label>
                                <Input
                                    id="form-title"
                                    value={data.title}
                                    required
                                    maxLength={120}
                                    onChange={(event) => {
                                        setData((current) => ({
                                            ...current,
                                            title: event.target.value,
                                            slug: slugTouched
                                                ? current.slug
                                                : slugFromTitle(
                                                      event.target.value,
                                                  ),
                                        }));
                                    }}
                                    aria-invalid={Boolean(errors.title)}
                                />
                                <InputError message={errors.title} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="form-slug">{t('Slug')}</Label>
                                <Input
                                    id="form-slug"
                                    value={data.slug}
                                    required
                                    maxLength={80}
                                    className="font-mono text-sm"
                                    autoCapitalize="none"
                                    autoCorrect="off"
                                    spellCheck={false}
                                    onChange={(event) => {
                                        setSlugTouched(true);
                                        setData('slug', event.target.value);
                                    }}
                                    aria-invalid={Boolean(errors.slug)}
                                />
                                <InputError message={errors.slug} />
                            </div>
                        </div>
                    </SectionCard>

                    <SectionCard
                        title={t('forms::Fields')}
                        description={t(
                            'forms::Half width fields sit side by side on wide screens.',
                        )}
                        footer={
                            <div className="flex w-full flex-col gap-2 sm:flex-row sm:items-center sm:justify-end">
                                <Label
                                    htmlFor="new-field-type"
                                    className="sr-only"
                                >
                                    {t('forms::New field type')}
                                </Label>
                                <SearchableSelect
                                    id="new-field-type"
                                    value={newType}
                                    onValueChange={setNewType}
                                    options={fieldTypes}
                                    searchPlaceholder={t(
                                        'forms::Search field types',
                                    )}
                                    className="w-full sm:w-56"
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={addField}
                                    disabled={
                                        data.fields.length >= limits.max_fields
                                    }
                                >
                                    <Plus className="size-4" />
                                    {t('forms::Add field')}
                                </Button>
                            </div>
                        }
                    >
                        {data.fields.length === 0 ? (
                            <EmptyState
                                icon="forms"
                                title={t('forms::This form has no fields yet.')}
                                description={t(
                                    'forms::Pick a field type below and add it.',
                                )}
                            />
                        ) : (
                            <ol className="grid grid-cols-[minmax(0,1fr)] gap-3">
                                {data.fields.map((field, index) => (
                                    <FieldEditor
                                        key={field.id}
                                        field={field}
                                        index={index}
                                        count={data.fields.length}
                                        fieldTypes={fieldTypes}
                                        limits={limits}
                                        errors={allErrors}
                                        onChange={(changed) =>
                                            setFields(
                                                data.fields.map(
                                                    (item, position) =>
                                                        position === index
                                                            ? changed
                                                            : item,
                                                ),
                                            )
                                        }
                                        onMove={(step) =>
                                            setFields(
                                                moveField(
                                                    data.fields,
                                                    index,
                                                    step,
                                                ),
                                            )
                                        }
                                        onRemove={() =>
                                            setFields(
                                                data.fields.filter(
                                                    (_, position) =>
                                                        position !== index,
                                                ),
                                            )
                                        }
                                    />
                                ))}
                            </ol>
                        )}
                        <InputError message={errors.fields} className="mt-2" />
                    </SectionCard>
                </div>

                <div className="grid content-start gap-4">
                    <SectionCard title={t('forms::After sending')}>
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="submit-label">
                                    {t('forms::Button label')}
                                </Label>
                                <Input
                                    id="submit-label"
                                    value={data.settings.submit_label}
                                    maxLength={40}
                                    onChange={(event) =>
                                        setSetting(
                                            'submit_label',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={allErrors['settings.submit_label']}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="success-message">
                                    {t('forms::Thank you message')}
                                </Label>
                                <Textarea
                                    id="success-message"
                                    rows={3}
                                    value={data.settings.success_message}
                                    onChange={(event) =>
                                        setSetting(
                                            'success_message',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        allErrors['settings.success_message']
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="redirect-url">
                                    {t('forms::Or send people to')}
                                </Label>
                                <Input
                                    id="redirect-url"
                                    value={data.settings.redirect_url}
                                    placeholder="/thank-you"
                                    autoCapitalize="none"
                                    autoCorrect="off"
                                    spellCheck={false}
                                    onChange={(event) =>
                                        setSetting(
                                            'redirect_url',
                                            event.target.value,
                                        )
                                    }
                                    aria-describedby="redirect-url-help"
                                />
                                <p
                                    id="redirect-url-help"
                                    className="text-xs text-muted-foreground"
                                >
                                    {t(
                                        'forms::Leave empty to show the message on the same page.',
                                    )}
                                </p>
                                <InputError
                                    message={allErrors['settings.redirect_url']}
                                />
                            </div>
                        </div>
                    </SectionCard>

                    <SectionCard
                        title={t('forms::Emails')}
                        description={t(
                            'forms::Sent in the background. Replies go to the sender.',
                        )}
                    >
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="notify-emails">
                                    {t('forms::Send each message to')}
                                </Label>
                                <Textarea
                                    id="notify-emails"
                                    rows={2}
                                    defaultValue={data.settings.notify_emails.join(
                                        ', ',
                                    )}
                                    placeholder={t(
                                        'forms::hello@example.com, sales@example.com',
                                    )}
                                    onBlur={(event) =>
                                        setSetting(
                                            'notify_emails',
                                            parseEmails(event.target.value),
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        Object.entries(allErrors).find(
                                            ([key]) =>
                                                key.startsWith(
                                                    'settings.notify_emails',
                                                ),
                                        )?.[1]
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="notify-subject">
                                    {t('forms::Subject')}
                                </Label>
                                <Input
                                    id="notify-subject"
                                    value={data.settings.notify_subject}
                                    onChange={(event) =>
                                        setSetting(
                                            'notify_subject',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    message={
                                        allErrors['settings.notify_subject']
                                    }
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="notify-body">
                                    {t('forms::Message')}
                                </Label>
                                <Textarea
                                    id="notify-body"
                                    rows={4}
                                    value={data.settings.notify_body}
                                    onChange={(event) =>
                                        setSetting(
                                            'notify_body',
                                            event.target.value,
                                        )
                                    }
                                    aria-describedby="placeholders-help"
                                />
                                <InputError
                                    message={allErrors['settings.notify_body']}
                                />
                            </div>
                            <label className="flex min-h-10 items-center gap-2.5 text-sm">
                                <Checkbox
                                    checked={data.settings.confirmation_enabled}
                                    onCheckedChange={(value) =>
                                        setSetting(
                                            'confirmation_enabled',
                                            value === true,
                                        )
                                    }
                                />
                                {t(
                                    'forms::Send a copy to the person who wrote',
                                )}
                            </label>
                            {data.settings.confirmation_enabled && (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="confirmation-subject">
                                            {t('forms::Copy subject')}
                                        </Label>
                                        <Input
                                            id="confirmation-subject"
                                            value={
                                                data.settings
                                                    .confirmation_subject
                                            }
                                            onChange={(event) =>
                                                setSetting(
                                                    'confirmation_subject',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <InputError
                                            message={
                                                allErrors[
                                                    'settings.confirmation_subject'
                                                ]
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="confirmation-body">
                                            {t('forms::Copy message')}
                                        </Label>
                                        <Textarea
                                            id="confirmation-body"
                                            rows={4}
                                            value={
                                                data.settings.confirmation_body
                                            }
                                            onChange={(event) =>
                                                setSetting(
                                                    'confirmation_body',
                                                    event.target.value,
                                                )
                                            }
                                            aria-describedby="placeholders-help"
                                        />
                                        <InputError
                                            message={
                                                allErrors[
                                                    'settings.confirmation_body'
                                                ]
                                            }
                                        />
                                    </div>
                                </>
                            )}
                            <p
                                id="placeholders-help"
                                className="text-xs text-muted-foreground"
                            >
                                <Trans
                                    text={t(
                                        'forms::Placeholders: <list>{placeholders}</list>',
                                        {
                                            placeholders: placeholders(
                                                data.fields,
                                            ).join(' '),
                                        },
                                    )}
                                    components={{
                                        list: (text) => (
                                            <span className="font-mono break-words">
                                                {text}
                                            </span>
                                        ),
                                    }}
                                />
                            </p>
                        </div>
                    </SectionCard>

                    {shortcode && (
                        <SectionCard title={t('forms::Show the form')}>
                            <p className="mb-2 text-sm text-muted-foreground">
                                {t(
                                    'forms::Paste this shortcode in any text, or add the Form block.',
                                )}
                            </p>
                            <CopyShortcode
                                value={shortcode}
                                blade={bladeSnippet}
                                label={t(
                                    'forms::Copy the shortcode of this form',
                                )}
                            />
                            {embed && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    <Trans
                                        text={t(
                                            'forms::Forms plugin component: <code>{embed}</code>',
                                            { embed },
                                        )}
                                        components={{
                                            code: (text) => (
                                                <code className="font-mono break-all">
                                                    {text}
                                                </code>
                                            ),
                                        }}
                                    />
                                </p>
                            )}
                            <h3 className="mt-3 mb-1 text-sm font-medium">
                                {t('forms::Used in')}
                            </h3>
                            {usedIn.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    {t('forms::No entry shows this form yet.')}
                                </p>
                            ) : (
                                <ul className="grid gap-1 text-sm">
                                    {usedIn.map((entry) => (
                                        <li
                                            key={entry.id}
                                            className="flex min-w-0 items-center gap-2"
                                        >
                                            <Link
                                                href={entry.edit_url}
                                                className="truncate hover:underline"
                                            >
                                                {entry.title}
                                            </Link>
                                            <EntryStatusBadge
                                                status={
                                                    entry.status as EntryStatus
                                                }
                                            />
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </SectionCard>
                    )}

                    <div className="flex flex-wrap items-center gap-2">
                        <Button type="submit" disabled={processing}>
                            {saved
                                ? t('forms::Save form')
                                : t('forms::Create form')}
                        </Button>
                        {urls.destroy && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="text-danger-text"
                                onClick={remove}
                            >
                                {t('forms::Delete form')}
                            </Button>
                        )}
                    </div>
                </div>
            </form>
        </>
    );
}

FormBuilder.layout = {
    breadcrumbs: [
        { title: msg('forms::Forms'), href: '/admin/forms' },
        { title: msg('forms::Form builder'), href: '#' },
    ],
};
