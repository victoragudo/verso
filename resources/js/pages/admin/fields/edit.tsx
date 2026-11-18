import { Head, router, useForm } from '@inertiajs/react';
import { Download, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { FieldBuilderList } from '@/components/verso/fields/field-builder';
import {
    LocationEditor,
    NEW_RULE,
} from '@/components/verso/fields/location-editor';
import { nameFromLabel } from '@/components/verso/fields/logic';
import type {
    ChoiceOption,
    FieldDefinition,
    FieldGroupDefinition,
    FieldPosition,
    LocationRule,
} from '@/components/verso/fields/types';
import { PageHeader, SectionCard } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import {
    create,
    destroy,
    edit,
    exportMethod,
    index,
    store,
    update,
} from '@/routes/admin/fields';

type Props = {
    group:
        | (FieldGroupDefinition & { id: number | null; source: string })
        | null;
    fieldTypes: ChoiceOption[];
    positions: ChoiceOption[];
    locationParams: ChoiceOption[];
    contentTypes: ChoiceOption[];
    taxonomies: ChoiceOption[];
    roles: ChoiceOption[];
    terms: ChoiceOption[];
    maxDepth: number;
};

type GroupForm = {
    title: string;
    key: string;
    description: string;
    position: FieldPosition;
    order: number;
    active: boolean;
    location: LocationRule[][];
    fields: FieldDefinition[];
};

const FORM_ID = 'field-group-form';

// Builds a field group: its title, where it shows and its fields.
export default function FieldGroupEdit({
    group,
    fieldTypes,
    positions,
    locationParams,
    contentTypes,
    taxonomies,
    roles,
    terms,
    maxDepth,
}: Props) {
    const form = useForm<GroupForm>({
        title: group?.title ?? '',
        key: group?.key ?? '',
        description: group?.description ?? '',
        position: group?.position ?? 'main',
        order: group?.order ?? 0,
        active: group?.active ?? true,
        location: group?.location ?? [[{ ...NEW_RULE }]],
        fields: group?.fields ?? [],
    });
    const { data, setData, errors, processing } = form;
    const allErrors = errors as Record<string, string | undefined>;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (group?.id) {
            form.put(update.url(group.id), { preserveScroll: true });
        } else {
            form.post(store.url());
        }
    };

    const title = group ? t('Edit field group') : t('New field group');

    return (
        <>
            <Head title={title} />
            <PageHeader
                icon="fields"
                title={group ? group.title || t('Untitled group') : title}
                description={t(
                    'Add fields to the entry editor and show them in your theme.',
                )}
                actions={
                    <>
                        {group && (
                            <Button variant="outline" asChild>
                                <a
                                    href={exportMethod.url({
                                        query: { key: group.key },
                                    })}
                                >
                                    <Download />
                                    {t('Export')}
                                </a>
                            </Button>
                        )}
                        <Button
                            type="submit"
                            form={FORM_ID}
                            disabled={processing}
                        >
                            {group ? t('Save changes') : t('Create group')}
                        </Button>
                    </>
                }
            />

            <form
                id={FORM_ID}
                onSubmit={submit}
                className="grid items-start gap-6 @3xl/page:grid-cols-[minmax(0,1fr)_clamp(20rem,24cqi,30rem)]"
            >
                <div className="grid min-w-0 gap-4">
                    <SectionCard title={t('Fields')} icon="fields">
                        <FieldBuilderList
                            fields={data.fields}
                            onChange={(fields) => setData('fields', fields)}
                            choices={{
                                fieldTypes,
                                contentTypes,
                                taxonomies,
                                roles,
                            }}
                            maxDepth={maxDepth}
                        />
                        <InputError
                            className="mt-3"
                            message={allErrors.fields}
                        />
                    </SectionCard>

                    <SectionCard
                        title={t('Location')}
                        description={t(
                            'Show this group in the editor when these rules match.',
                        )}
                        icon="site"
                    >
                        <LocationEditor
                            value={data.location}
                            onChange={(location) =>
                                setData('location', location)
                            }
                            choices={{
                                locationParams,
                                contentTypes,
                                roles,
                                terms,
                            }}
                        />
                        <InputError
                            className="mt-3"
                            message={allErrors.location}
                        />
                    </SectionCard>
                </div>

                <aside className="grid min-w-0 grid-cols-[minmax(0,1fr)] gap-4">
                    <SectionCard
                        title={t('Settings')}
                        contentClassName="grid gap-4"
                        footer={
                            <>
                                {group?.id && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="me-auto text-destructive-foreground"
                                        onClick={() => {
                                            if (
                                                window.confirm(
                                                    t(
                                                        'Delete this field group? Values saved in entries stay.',
                                                    ),
                                                )
                                            ) {
                                                router.delete(
                                                    destroy.url(
                                                        group.id as number,
                                                    ),
                                                );
                                            }
                                        }}
                                    >
                                        <Trash2 />
                                        {t('Delete')}
                                    </Button>
                                )}
                                <Button
                                    type="submit"
                                    size="sm"
                                    disabled={processing}
                                >
                                    {group ? t('Save') : t('Create')}
                                </Button>
                            </>
                        }
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="title">{t('Title')}</Label>
                            <Input
                                id="title"
                                value={data.title}
                                required
                                onChange={(event) => {
                                    const value = event.target.value;
                                    setData((current) => ({
                                        ...current,
                                        title: value,
                                        key:
                                            !group &&
                                            (current.key === '' ||
                                                current.key ===
                                                    nameFromLabel(
                                                        current.title,
                                                    ))
                                                ? nameFromLabel(value)
                                                : current.key,
                                    }));
                                }}
                            />
                            <InputError message={errors.title} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="key">{t('Key')}</Label>
                            <Input
                                id="key"
                                value={data.key}
                                className="font-mono"
                                onChange={(event) =>
                                    setData('key', event.target.value)
                                }
                            />
                            <p className="text-xs text-muted-foreground">
                                {t(
                                    'Used in exports. Changing it does not move saved values.',
                                )}
                            </p>
                            <InputError message={errors.key} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="description">
                                {t('Description')}
                            </Label>
                            <Textarea
                                id="description"
                                rows={2}
                                value={data.description}
                                onChange={(event) =>
                                    setData('description', event.target.value)
                                }
                            />
                            <InputError message={errors.description} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="position">
                                {t('Position in the editor')}
                            </Label>
                            <Select
                                value={data.position}
                                onValueChange={(position) =>
                                    setData(
                                        'position',
                                        position as FieldPosition,
                                    )
                                }
                            >
                                <SelectTrigger id="position" className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {positions.map((position) => (
                                        <SelectItem
                                            key={position.value}
                                            value={position.value}
                                        >
                                            {position.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="order">{t('Order')}</Label>
                            <Input
                                id="order"
                                type="number"
                                className="tabular-nums"
                                value={data.order}
                                onChange={(event) =>
                                    setData('order', Number(event.target.value))
                                }
                            />
                        </div>
                        <label className="flex min-h-10 items-center gap-2.5 text-sm md:min-h-8">
                            <Checkbox
                                checked={data.active}
                                onCheckedChange={(checked) =>
                                    setData('active', checked === true)
                                }
                            />
                            {t('Active')}
                        </label>
                    </SectionCard>
                </aside>
            </form>
        </>
    );
}

FieldGroupEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: t('Custom fields'), href: index() },
        {
            title: props.group ? props.group.title : t('New field group'),
            href: props.group?.id ? edit(props.group.id) : create(),
        },
    ],
});
