import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    Plus,
    Trash2,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { EntryStatusBadge } from '@/components/verso/content/status-badge';
import { MediaPicker } from '@/components/verso/media/media-picker';
import {
    FormField,
    MoneyPrefix,
    ToggleField,
} from '@/components/verso/shop/form-field';
import type {
    ShopGalleryItem,
    ShopProductForm,
    ShopVariantForm,
} from '@/components/verso/shop/types';
import {
    CopyShortcode,
    EmptyState,
    PageHeader,
    SectionCard,
} from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import type { EntryStatus } from '@/types';

type Props = {
    entry: {
        id: number;
        title: string;
        status: string;
        contentUrl: string;
        viewUrl: string | null;
    };
    product: ShopProductForm;
    embed?: {
        shortcode: string;
        blade: string;
        usedIn: { id: number; title: string; edit_url: string }[];
    } | null;
    currency: string;
    updateUrl: string;
    indexUrl: string;
};

const EMPTY_VARIANT: ShopVariantForm = {
    id: null,
    option_name: '',
    option_value: '',
    sku: '',
    price: '',
    stock: 0,
};

// Price, SKU, stock, gallery and options of one product.
export default function ShopProductEdit({
    entry,
    product,
    embed = null,
    currency,
    updateUrl,
}: Props) {
    const form = useForm<ShopProductForm>(product);
    const { data, setData, errors, processing } = form;
    const [pickerOpen, setPickerOpen] = useState(false);
    const fieldErrors = errors as Record<string, string | undefined>;

    form.transform((values) => ({
        ...values,
        gallery: values.gallery.map((item) => item.id),
    }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(updateUrl, { preserveScroll: true });
    };

    const setVariant = (
        index: number,
        key: keyof ShopVariantForm,
        value: string | number,
    ) =>
        setData(
            'variants',
            data.variants.map((variant, current) =>
                current === index ? { ...variant, [key]: value } : variant,
            ),
        );

    const addVariant = () =>
        setData('variants', [
            ...data.variants,
            {
                ...EMPTY_VARIANT,
                option_name: data.variants.at(-1)?.option_name ?? '',
            },
        ]);

    const removeVariant = (index: number) =>
        setData(
            'variants',
            data.variants.filter((_, current) => current !== index),
        );

    const addImage = (image: ShopGalleryItem) => {
        if (!data.gallery.some((item) => item.id === image.id)) {
            setData('gallery', [...data.gallery, image]);
        }
    };

    const moveImage = (index: number, step: -1 | 1) => {
        const gallery = [...data.gallery];
        const [image] = gallery.splice(index, 1);
        gallery.splice(index + step, 0, image);
        setData('gallery', gallery);
    };

    return (
        <>
            <Head
                title={t('shop::{title} · Price and stock', {
                    title: entry.title,
                })}
            />
            <PageHeader
                icon="product"
                title={
                    <span className="flex flex-wrap items-center gap-2">
                        {entry.title}
                        <EntryStatusBadge
                            status={entry.status as EntryStatus}
                        />
                    </span>
                }
                description={t(
                    'shop::Price, stock, photos and options. The name and description live in the content editor.',
                )}
                actions={
                    <>
                        <Button
                            variant="outline"
                            asChild
                            className="min-h-10 md:min-h-9"
                        >
                            <Link href={entry.contentUrl}>
                                {t('shop::Edit description')}
                            </Link>
                        </Button>
                        {entry.viewUrl && (
                            <Button
                                variant="outline"
                                asChild
                                className="min-h-10 md:min-h-9"
                            >
                                <a
                                    href={entry.viewUrl}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink className="size-4" />
                                    {t('View')}
                                </a>
                            </Button>
                        )}
                    </>
                }
            />

            <form
                onSubmit={submit}
                className="grid items-start gap-4 @3xl/page:grid-cols-[minmax(0,1fr)_clamp(20rem,24cqi,30rem)]"
            >
                <div className="flex min-w-0 flex-col gap-4">
                    <SectionCard
                        title={t('shop::Options')}
                        icon="categories"
                        description={t(
                            'shop::Sizes, colours or other choices. Each option has its own stock and can have its own price.',
                        )}
                        actions={
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addVariant}
                                className="min-h-10 md:min-h-8"
                            >
                                <Plus className="size-4" />
                                {t('shop::Add option')}
                            </Button>
                        }
                        contentClassName={
                            data.variants.length === 0
                                ? 'p-0 sm:p-0'
                                : undefined
                        }
                    >
                        {data.variants.length === 0 ? (
                            <EmptyState
                                icon="categories"
                                title={t('shop::No options')}
                                description={t(
                                    'shop::Customers buy the product as it is.',
                                )}
                            />
                        ) : (
                            <ul className="grid gap-4">
                                {data.variants.map((variant, index) => (
                                    <li
                                        key={variant.id ?? `new-${index}`}
                                        className="grid gap-3 rounded-md border p-3 sm:grid-cols-2 xl:grid-cols-[1fr_1fr_1fr_8rem_6rem_auto]"
                                    >
                                        <VariantInput
                                            label={t('Name')}
                                            id={`variant-${index}-name`}
                                            value={variant.option_name}
                                            placeholder={t('shop::Size')}
                                            error={
                                                fieldErrors[
                                                    `variants.${index}.option_name`
                                                ]
                                            }
                                            onChange={(value) =>
                                                setVariant(
                                                    index,
                                                    'option_name',
                                                    value,
                                                )
                                            }
                                        />
                                        <VariantInput
                                            label={t('shop::Value')}
                                            id={`variant-${index}-value`}
                                            value={variant.option_value}
                                            placeholder={t('shop::Medium')}
                                            error={
                                                fieldErrors[
                                                    `variants.${index}.option_value`
                                                ]
                                            }
                                            onChange={(value) =>
                                                setVariant(
                                                    index,
                                                    'option_value',
                                                    value,
                                                )
                                            }
                                        />
                                        <VariantInput
                                            label={t('shop::SKU')}
                                            id={`variant-${index}-sku`}
                                            value={variant.sku}
                                            error={
                                                fieldErrors[
                                                    `variants.${index}.sku`
                                                ]
                                            }
                                            onChange={(value) =>
                                                setVariant(index, 'sku', value)
                                            }
                                        />
                                        <VariantInput
                                            label={t(
                                                'shop::Price ({currency})',
                                                {
                                                    currency,
                                                },
                                            )}
                                            id={`variant-${index}-price`}
                                            value={variant.price}
                                            placeholder={data.price}
                                            inputMode="decimal"
                                            error={
                                                fieldErrors[
                                                    `variants.${index}.price`
                                                ]
                                            }
                                            onChange={(value) =>
                                                setVariant(
                                                    index,
                                                    'price',
                                                    value,
                                                )
                                            }
                                        />
                                        <VariantInput
                                            label={t('shop::Stock')}
                                            id={`variant-${index}-stock`}
                                            value={String(variant.stock)}
                                            type="number"
                                            error={
                                                fieldErrors[
                                                    `variants.${index}.stock`
                                                ]
                                            }
                                            onChange={(value) =>
                                                setVariant(
                                                    index,
                                                    'stock',
                                                    Number(value),
                                                )
                                            }
                                        />
                                        <div className="flex items-end">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    removeVariant(index)
                                                }
                                                aria-label={t(
                                                    'shop::Remove option {option}',
                                                    {
                                                        option:
                                                            variant.option_value ||
                                                            index + 1,
                                                    },
                                                )}
                                                className="size-10 text-muted-foreground md:size-9"
                                            >
                                                <Trash2 className="size-4" />
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <InputError message={errors.variants} />
                    </SectionCard>

                    <SectionCard
                        title={t('shop::Photos')}
                        icon="media"
                        description={t(
                            'shop::Shown under the featured image on the product page.',
                        )}
                        actions={
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => setPickerOpen(true)}
                                className="min-h-10 md:min-h-8"
                            >
                                <Plus className="size-4" />
                                {t('shop::Add photo')}
                            </Button>
                        }
                    >
                        {data.gallery.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('shop::No extra photos yet.')}
                            </p>
                        ) : (
                            <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                                {data.gallery.map((image, index) => (
                                    <li
                                        key={image.id}
                                        className="group relative overflow-hidden rounded-md border bg-muted"
                                    >
                                        <img
                                            src={image.thumbnail}
                                            alt={image.alt || image.filename}
                                            className="aspect-square w-full object-cover"
                                        />
                                        <div className="absolute inset-x-0 bottom-0 flex justify-between gap-1 bg-background/90 p-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                disabled={index === 0}
                                                onClick={() =>
                                                    moveImage(index, -1)
                                                }
                                                aria-label={t(
                                                    'shop::Move {name} earlier',
                                                    {
                                                        name: image.filename,
                                                    },
                                                )}
                                            >
                                                <ArrowUp className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                disabled={
                                                    index ===
                                                    data.gallery.length - 1
                                                }
                                                onClick={() =>
                                                    moveImage(index, 1)
                                                }
                                                aria-label={t(
                                                    'shop::Move {name} later',
                                                    {
                                                        name: image.filename,
                                                    },
                                                )}
                                            >
                                                <ArrowDown className="size-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="size-8"
                                                onClick={() =>
                                                    setData(
                                                        'gallery',
                                                        data.gallery.filter(
                                                            (item) =>
                                                                item.id !==
                                                                image.id,
                                                        ),
                                                    )
                                                }
                                                aria-label={t(
                                                    'shop::Remove {name}',
                                                    {
                                                        name: image.filename,
                                                    },
                                                )}
                                            >
                                                <X className="size-4" />
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                        <InputError message={errors.gallery} />
                    </SectionCard>
                </div>

                <div className="flex min-w-0 flex-col gap-4 @3xl/page:sticky @3xl/page:top-20">
                    <SectionCard
                        title={t('shop::Price and stock')}
                        icon="shop"
                        contentClassName="grid gap-4"
                        footer={
                            <Button
                                type="submit"
                                disabled={processing}
                                className="min-h-10 md:min-h-9"
                            >
                                {t('shop::Save product')}
                            </Button>
                        }
                    >
                        <FormField
                            label={t('shop::Price')}
                            id="price"
                            error={errors.price}
                            hint={t('shop::Without tax, like 24.90.')}
                        >
                            <MoneyPrefix currency={currency}>
                                <Input
                                    id="price"
                                    inputMode="decimal"
                                    value={data.price}
                                    onChange={(event) =>
                                        setData('price', event.target.value)
                                    }
                                    className="ps-12 tabular-nums"
                                    required
                                />
                            </MoneyPrefix>
                        </FormField>
                        <FormField
                            label={t('shop::SKU')}
                            id="sku"
                            error={errors.sku}
                        >
                            <Input
                                id="sku"
                                value={data.sku}
                                onChange={(event) =>
                                    setData('sku', event.target.value)
                                }
                                className="font-mono"
                            />
                        </FormField>
                        <ToggleField
                            label={t('shop::Track stock')}
                            hint={t('shop::Stop selling when nothing is left.')}
                            checked={data.track_stock}
                            onChange={(checked) =>
                                setData('track_stock', checked)
                            }
                        />
                        {data.track_stock && data.variants.length === 0 && (
                            <FormField
                                label={t('shop::In stock')}
                                id="stock"
                                error={errors.stock}
                            >
                                <Input
                                    id="stock"
                                    type="number"
                                    min={0}
                                    value={data.stock}
                                    onChange={(event) =>
                                        setData(
                                            'stock',
                                            Number(event.target.value),
                                        )
                                    }
                                    className="tabular-nums"
                                />
                            </FormField>
                        )}
                        {data.track_stock && data.variants.length > 0 && (
                            <p className="text-sm text-muted-foreground">
                                {t('shop::Stock is counted for each option.')}
                            </p>
                        )}
                    </SectionCard>

                    {embed && (
                        <SectionCard
                            title={t('shop::Show in any text')}
                            icon="product"
                        >
                            <CopyShortcode
                                value={embed.shortcode}
                                blade={embed.blade}
                                label={t(
                                    'shop::Copy the shortcode of this product',
                                )}
                            />
                            {embed.usedIn.length > 0 && (
                                <p className="mt-2 text-xs text-muted-foreground">
                                    {t('shop::Used in:')}{' '}
                                    {embed.usedIn.map((usage, index) => (
                                        <span key={usage.id}>
                                            {index > 0 && ', '}
                                            <Link
                                                href={usage.edit_url}
                                                className="hover:underline"
                                            >
                                                {usage.title}
                                            </Link>
                                        </span>
                                    ))}
                                </p>
                            )}
                        </SectionCard>
                    )}
                </div>
            </form>

            <MediaPicker
                open={pickerOpen}
                onOpenChange={setPickerOpen}
                accept="image"
                onSelect={(media) => {
                    addImage({
                        id: media.id,
                        url: media.url,
                        thumbnail: media.url,
                        alt: media.alt ?? '',
                        filename: media.filename,
                    });
                    setPickerOpen(false);
                }}
            />
        </>
    );
}

function VariantInput({
    label,
    id,
    value,
    onChange,
    error,
    placeholder,
    type = 'text',
    inputMode,
}: {
    label: string;
    id: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    placeholder?: string;
    type?: string;
    inputMode?: 'decimal';
}) {
    return (
        <div className="grid min-w-0 content-start gap-1.5">
            <Label htmlFor={id} className="text-xs text-muted-foreground">
                {label}
            </Label>
            <Input
                id={id}
                type={type}
                min={type === 'number' ? 0 : undefined}
                inputMode={inputMode}
                value={value}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
                className="tabular-nums"
            />
            <InputError message={error} />
        </div>
    );
}

ShopProductEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: t('shop::Products'), href: props.indexUrl },
        { title: props.entry.title, href: props.updateUrl },
    ],
});
