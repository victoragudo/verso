import { Head, useForm } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    FormField,
    MoneyPrefix,
    ToggleField,
} from '@/components/verso/shop/form-field';
import type { ShopSettingsForm } from '@/components/verso/shop/types';
import { PageHeader, SectionCard, StatusBadge } from '@/components/verso/ui';
import { formatList, t } from '@/lib/i18n';

type Props = {
    settings: ShopSettingsForm;
    stripe: {
        hasSecretKey: boolean;
        hasWebhookSecret: boolean;
        webhookUrl: string;
        events: string[];
    };
    currencies: string[];
    updateUrl: string;
};

const SAVED_SECRET = '••••••••';
const STRIPE_SECRET_KEY_PREFIX = 'sk_live_';
const STRIPE_WEBHOOK_SECRET_PREFIX = 'whsec_';

type Form = ShopSettingsForm & {
    stripe_secret_key: string;
    stripe_webhook_secret: string;
    remove_stripe_keys: boolean;
};

// Shop settings: currency, tax, shipping, order emails and payment methods.
export default function ShopSettingsPage({
    settings,
    stripe,
    currencies,
    updateUrl,
}: Props) {
    const form = useForm<Form>({
        ...settings,
        stripe_secret_key: '',
        stripe_webhook_secret: '',
        remove_stripe_keys: false,
    });
    const { data, setData, errors, processing } = form;
    const [copied, setCopied] = useState(false);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(updateUrl, {
            preserveScroll: true,
            onSuccess: () =>
                form.reset(
                    'stripe_secret_key',
                    'stripe_webhook_secret',
                    'remove_stripe_keys',
                ),
        });
    };

    const copyWebhookUrl = () => {
        void navigator.clipboard.writeText(stripe.webhookUrl).then(() => {
            setCopied(true);
            window.setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <>
            <Head title={t('shop::Shop settings')} />
            <PageHeader
                icon="shop"
                title={t('shop::Shop settings')}
                description={t(
                    'shop::Prices, shipping, order emails and how customers pay.',
                )}
            />

            <form onSubmit={submit} className="section-grid">
                <SectionCard
                    icon="shop"
                    title={t('shop::Prices and tax')}
                    description={t(
                        'shop::Prices are saved without tax. Tax is added at the checkout.',
                    )}
                    contentClassName="grid gap-4 md:grid-cols-2"
                >
                    <FormField
                        label={t('shop::Currency')}
                        id="currency"
                        error={errors.currency}
                        hint={t(
                            'shop::Changing it does not convert saved prices.',
                        )}
                    >
                        <Select
                            value={data.currency}
                            onValueChange={(value) =>
                                setData('currency', value)
                            }
                        >
                            <SelectTrigger id="currency" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {currencies.map((currency) => (
                                    <SelectItem key={currency} value={currency}>
                                        {currency}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </FormField>
                    <FormField
                        label={t('shop::Tax rate (%)')}
                        id="tax_rate"
                        error={errors.tax_rate}
                        hint={t('shop::Use 0 when you do not charge tax.')}
                    >
                        <Input
                            id="tax_rate"
                            inputMode="decimal"
                            value={data.tax_rate}
                            onChange={(event) =>
                                setData('tax_rate', event.target.value)
                            }
                            placeholder="21"
                            className="tabular-nums"
                            required
                        />
                    </FormField>
                    <ToggleField
                        className="md:col-span-2"
                        label={t('shop::Charge tax on shipping')}
                        hint={t(
                            'shop::Tax is worked out on the products and the shipping price.',
                        )}
                        checked={data.tax_shipping}
                        onChange={(checked) => setData('tax_shipping', checked)}
                    />
                </SectionCard>

                <SectionCard
                    icon="orders"
                    title={t('shop::Shipping')}
                    description={t('shop::One flat price for every order.')}
                    contentClassName="grid gap-4 md:grid-cols-2"
                >
                    <FormField
                        label={t('shop::Flat shipping price')}
                        id="shipping_rate"
                        error={errors.shipping_rate}
                    >
                        <MoneyPrefix currency={data.currency}>
                            <Input
                                id="shipping_rate"
                                inputMode="decimal"
                                value={data.shipping_rate}
                                onChange={(event) =>
                                    setData('shipping_rate', event.target.value)
                                }
                                className="ps-12 tabular-nums"
                                required
                            />
                        </MoneyPrefix>
                    </FormField>
                    <FormField
                        label={t('shop::Free shipping from')}
                        id="free_shipping_over"
                        error={errors.free_shipping_over}
                        hint={t('shop::Leave empty to always charge shipping.')}
                    >
                        <MoneyPrefix currency={data.currency}>
                            <Input
                                id="free_shipping_over"
                                inputMode="decimal"
                                value={data.free_shipping_over}
                                onChange={(event) =>
                                    setData(
                                        'free_shipping_over',
                                        event.target.value,
                                    )
                                }
                                className="ps-12 tabular-nums"
                            />
                        </MoneyPrefix>
                    </FormField>
                </SectionCard>

                <SectionCard
                    icon="users"
                    title={t('shop::Order emails')}
                    description={t(
                        'shop::Customers always get an email for new orders and status changes.',
                    )}
                >
                    <FormField
                        label={t('shop::Send a copy of new orders to')}
                        id="notify_email"
                        error={errors.notify_email}
                        hint={t('shop::Leave empty if you do not want a copy.')}
                    >
                        <Input
                            id="notify_email"
                            type="email"
                            value={data.notify_email}
                            onChange={(event) =>
                                setData('notify_email', event.target.value)
                            }
                            placeholder="orders@example.com"
                            autoComplete="email"
                            spellCheck={false}
                        />
                    </FormField>
                </SectionCard>

                <SectionCard
                    icon="settings"
                    title={t('shop::Bank transfer')}
                    description={t(
                        'shop::Customers send the money themselves. You mark the order as paid.',
                    )}
                    contentClassName="grid gap-4"
                >
                    <ToggleField
                        label={t('shop::Offer bank transfer')}
                        hint={t(
                            'shop::Shown as a payment method at the checkout.',
                        )}
                        checked={data.bank_transfer_enabled}
                        onChange={(checked) =>
                            setData('bank_transfer_enabled', checked)
                        }
                    />
                    <FormField
                        label={t('shop::Payment instructions')}
                        id="bank_transfer_instructions"
                        error={errors.bank_transfer_instructions}
                        hint={t(
                            'shop::Shown on the order page and in the order email, for example your IBAN.',
                        )}
                    >
                        <Textarea
                            id="bank_transfer_instructions"
                            rows={4}
                            value={data.bank_transfer_instructions}
                            onChange={(event) =>
                                setData(
                                    'bank_transfer_instructions',
                                    event.target.value,
                                )
                            }
                        />
                    </FormField>
                </SectionCard>

                <SectionCard
                    icon="api"
                    title={t('shop::Stripe Checkout')}
                    description={t(
                        'shop::Card payments on a secure Stripe page.',
                    )}
                    actions={
                        <StatusBadge
                            tone={
                                settings.stripe_enabled &&
                                stripe.hasSecretKey &&
                                stripe.hasWebhookSecret
                                    ? 'success'
                                    : 'neutral'
                            }
                        >
                            {settings.stripe_enabled &&
                            stripe.hasSecretKey &&
                            stripe.hasWebhookSecret
                                ? t('shop::Active')
                                : t('shop::Off')}
                        </StatusBadge>
                    }
                    contentClassName="grid gap-4 md:grid-cols-2"
                >
                    <ToggleField
                        className="md:col-span-2"
                        label={t('shop::Offer card payments with Stripe')}
                        hint={t(
                            'shop::Needs the secret key and the webhook signing secret.',
                        )}
                        checked={data.stripe_enabled}
                        onChange={(checked) =>
                            setData('stripe_enabled', checked)
                        }
                    />
                    <FormField
                        label={t('shop::Secret key')}
                        id="stripe_secret_key"
                        error={errors.stripe_secret_key}
                        hint={
                            stripe.hasSecretKey
                                ? t(
                                      'shop::A key is saved. Leave empty to keep it.',
                                  )
                                : t('shop::Starts with sk_test_ or sk_live_.')
                        }
                    >
                        <Input
                            id="stripe_secret_key"
                            type="password"
                            autoComplete="off"
                            value={data.stripe_secret_key}
                            onChange={(event) =>
                                setData('stripe_secret_key', event.target.value)
                            }
                            placeholder={
                                stripe.hasSecretKey
                                    ? SAVED_SECRET
                                    : STRIPE_SECRET_KEY_PREFIX
                            }
                        />
                    </FormField>
                    <FormField
                        label={t('shop::Webhook signing secret')}
                        id="stripe_webhook_secret"
                        error={errors.stripe_webhook_secret}
                        hint={
                            stripe.hasWebhookSecret
                                ? t(
                                      'shop::A secret is saved. Leave empty to keep it.',
                                  )
                                : t('shop::Starts with whsec_.')
                        }
                    >
                        <Input
                            id="stripe_webhook_secret"
                            type="password"
                            autoComplete="off"
                            value={data.stripe_webhook_secret}
                            onChange={(event) =>
                                setData(
                                    'stripe_webhook_secret',
                                    event.target.value,
                                )
                            }
                            placeholder={
                                stripe.hasWebhookSecret
                                    ? SAVED_SECRET
                                    : STRIPE_WEBHOOK_SECRET_PREFIX
                            }
                        />
                    </FormField>
                    <div className="grid gap-2 md:col-span-2">
                        <p className="text-sm font-medium text-foreground">
                            {t('shop::Webhook endpoint')}
                        </p>
                        <div className="flex min-w-0 items-center gap-2">
                            <code className="min-w-0 flex-1 truncate rounded-md border bg-muted/50 px-3 py-2 font-mono text-xs">
                                {stripe.webhookUrl}
                            </code>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                onClick={copyWebhookUrl}
                                aria-label={t('shop::Copy the webhook URL')}
                                className="size-10 md:size-9"
                            >
                                {copied ? (
                                    <Check className="size-4" />
                                ) : (
                                    <Copy className="size-4" />
                                )}
                            </Button>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            {t(
                                'shop::Add this URL in your Stripe dashboard with these events: {events}.',
                                { events: formatList(stripe.events) },
                            )}
                        </p>
                    </div>
                    {(stripe.hasSecretKey || stripe.hasWebhookSecret) && (
                        <ToggleField
                            className="md:col-span-2"
                            label={t('shop::Remove the saved Stripe keys')}
                            hint={t(
                                'shop::Card payments stop until new keys are added.',
                            )}
                            checked={data.remove_stripe_keys}
                            onChange={(checked) =>
                                setData('remove_stripe_keys', checked)
                            }
                        />
                    )}
                </SectionCard>

                <div className="flex justify-end">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="min-h-10 md:min-h-9"
                    >
                        {t('Save settings')}
                    </Button>
                </div>
            </form>
        </>
    );
}

ShopSettingsPage.layout = (props: Props) => ({
    breadcrumbs: [{ title: t('shop::Shop settings'), href: props.updateUrl }],
});
