import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Field } from '@/components/verso/site/settings-fields';
import { PageHeader, SectionCard, StatusBadge } from '@/components/verso/ui';
import { formatNumber, t } from '@/lib/i18n';
import { details } from '@/routes/admin/easy';
import { update } from '@/routes/admin/easy/details';
import { TOUCH_TARGET } from '@/components/verso/easy/touch';

type SiteDetailsValues = {
    email: string;
    phone: string;
    address: string;
    hours: string;
    tagline: string;
    description: string;
    social: string[];
};

type Props = {
    details: SiteDetailsValues;
    limits: {
        phone: number;
        address: number;
        hours: number;
        description: number;
        social: number;
    };
};

type DetailsForm = Omit<SiteDetailsValues, 'social'> & { social: string };

// Turns the "one address per line" box into a list without empty lines.
export function socialLines(text: string): string[] {
    return text
        .split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line !== '');
}

// "Your site details": how people reach you, your social profiles and what search engines show.
export default function SiteDetailsPage({ details: values, limits }: Props) {
    const form = useForm<DetailsForm>({
        ...values,
        social: values.social.join('\n'),
    });
    const { data, setData, errors, processing } = form;
    const socialError = Object.entries(errors).find(([key]) =>
        key.startsWith('social'),
    )?.[1];

    form.transform((current) => ({
        ...current,
        social: socialLines(current.social),
    }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(), { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('Your site details')} />
            <PageHeader
                icon="site"
                title={t('Your site details')}
                description={t(
                    'How people reach you, where they follow you and what search engines show about your site.',
                )}
            />

            <form
                onSubmit={submit}
                className="section-grid [--grid-min:26rem]"
                aria-busy={processing}
            >
                <div id="contact" className="scroll-mt-20">
                    <SectionCard
                        title={t('Contact details')}
                        description={t(
                            'Shown in the contact and map sections you add to your pages.',
                        )}
                        contentClassName="grid gap-4 @md/page:grid-cols-2"
                    >
                        <Field
                            label={t('Email address')}
                            id="email"
                            error={errors.email}
                        >
                            <Input
                                id="email"
                                className="pointer-coarse:min-h-11"
                                type="email"
                                autoComplete="email"
                                value={data.email}
                                onChange={(event) =>
                                    setData('email', event.target.value)
                                }
                                placeholder="hello@yourbusiness.com"
                            />
                        </Field>
                        <Field
                            label={t('Phone number')}
                            id="phone"
                            error={errors.phone}
                        >
                            <Input
                                id="phone"
                                className="pointer-coarse:min-h-11"
                                type="tel"
                                autoComplete="tel"
                                maxLength={limits.phone}
                                value={data.phone}
                                onChange={(event) =>
                                    setData('phone', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label={t('Address')}
                            id="address"
                            error={errors.address}
                            hint={t(
                                'The street, number, postcode and town, like on a letter.',
                            )}
                        >
                            <Textarea
                                id="address"
                                aria-describedby="address-hint"
                                autoComplete="street-address"
                                rows={3}
                                maxLength={limits.address}
                                value={data.address}
                                onChange={(event) =>
                                    setData('address', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label={t('Opening hours')}
                            id="hours"
                            error={errors.hours}
                            hint={t('Leave it empty if you have none.')}
                        >
                            <Input
                                id="hours"
                                className="pointer-coarse:min-h-11"
                                aria-describedby="hours-hint"
                                maxLength={limits.hours}
                                value={data.hours}
                                onChange={(event) =>
                                    setData('hours', event.target.value)
                                }
                                placeholder={t(
                                    'Monday to Friday, 9:00 to 18:00',
                                )}
                            />
                        </Field>
                    </SectionCard>
                </div>

                <div id="social" className="scroll-mt-20">
                    <SectionCard
                        title={t('Social profiles')}
                        description={t(
                            'Your social links can show in the footer of your site, and they help search engines know these profiles are yours.',
                        )}
                    >
                        <Field
                            label={t('Profile addresses')}
                            id="social-links"
                            error={socialError}
                            hint={t(
                                'One address per line, up to {count}. Like {example}.',
                                {
                                    count: formatNumber(limits.social),
                                    example: 'https://instagram.com/yourname',
                                },
                            )}
                        >
                            <Textarea
                                id="social-links"
                                aria-describedby="social-links-hint"
                                rows={4}
                                value={data.social}
                                onChange={(event) =>
                                    setData('social', event.target.value)
                                }
                                inputMode="url"
                                spellCheck={false}
                            />
                        </Field>
                    </SectionCard>
                </div>

                <div id="search" className="scroll-mt-20">
                    <SectionCard
                        title={t('Search engines')}
                        description={t(
                            'What people see about your site in Google and other search results.',
                        )}
                        contentClassName="grid gap-4"
                    >
                        <Field
                            label={t('Tagline')}
                            id="tagline"
                            error={errors.tagline}
                            hint={t(
                                'A few words under your site name, like “Fresh bread every morning”.',
                            )}
                        >
                            <Input
                                id="tagline"
                                className="pointer-coarse:min-h-11"
                                aria-describedby="tagline-hint"
                                value={data.tagline}
                                onChange={(event) =>
                                    setData('tagline', event.target.value)
                                }
                            />
                        </Field>
                        <Field
                            label={t('Short description')}
                            id="description"
                            error={errors.description}
                            hint={t(
                                '{limit, plural, one {One or two sentences about what you do. {length} of # character.} other {One or two sentences about what you do. {length} of # characters.}}',
                                {
                                    length: formatNumber(
                                        data.description.length,
                                    ),
                                    limit: limits.description,
                                },
                            )}
                        >
                            <Textarea
                                id="description"
                                aria-describedby="description-hint"
                                rows={3}
                                maxLength={limits.description}
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                            />
                        </Field>
                    </SectionCard>
                </div>

                <div className="sticky bottom-4 z-10 flex flex-wrap items-center justify-end gap-3 rounded-lg border bg-card px-4 py-3 shadow-xs">
                    {form.isDirty && (
                        <StatusBadge tone="warning" className="me-auto">
                            {t('Unsaved changes')}
                        </StatusBadge>
                    )}
                    <Button
                        type="submit"
                        disabled={processing}
                        className={TOUCH_TARGET}
                    >
                        {t('Save details')}
                    </Button>
                </div>
            </form>
        </>
    );
}

SiteDetailsPage.layout = () => ({
    breadcrumbs: [{ title: t('Your site details'), href: details() }],
});
