import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { builder } from '@/builder';
import { setupBuilder } from '@/builder/setup';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { NoticeBanner, PageHeader, SectionCard } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import { update } from '@/routes/admin/editor/settings';

export type EditorFeatureSetting = {
    key: string;
    label: string;
    description: string;
    roles: string[];
    types: string[] | null;
};

type Props = {
    features: EditorFeatureSetting[];
    roles: { value: string; label: string }[];
    contentTypes: { name: string; label: string; blocks: string[] | null }[];
    blocks: string[];
    themeBlocks: string[] | null;
};

type FormData = {
    features: Record<string, { roles: string[]; types: string[] | null }>;
    blocks: Record<string, string[] | null>;
};

// Adds the value when it is missing, removes it when it is there.
export function toggleValue(list: string[], value: string): string[] {
    return list.includes(value)
        ? list.filter((item) => item !== value)
        : [...list, value];
}

// A readable block name: the label core or a plugin registered, or the name with spaces.
export function blockLabel(name: string): string {
    setupBuilder({});

    return (
        builder.blocks.get(name)?.label ??
        name.replace(/[-_/]+/g, ' ').replace(/^\w/, (c) => c.toUpperCase())
    );
}

// One checkbox with its label, used for roles, content types and blocks.
function CheckOption({
    id,
    label,
    checked,
    onToggle,
}: {
    id: string;
    label: string;
    checked: boolean;
    onToggle: () => void;
}) {
    return (
        <div className="flex items-center gap-2">
            <Checkbox id={id} checked={checked} onCheckedChange={onToggle} />
            <Label htmlFor={id} className="font-normal">
                {label}
            </Label>
        </div>
    );
}

// "Editor settings": which editor tools each role gets and which blocks each content type allows.
export default function EditorSettingsPage({
    features,
    roles,
    contentTypes,
    blocks,
    themeBlocks,
}: Props) {
    const form = useForm<FormData>({
        features: Object.fromEntries(
            features.map((feature) => [
                feature.key,
                { roles: feature.roles, types: feature.types },
            ]),
        ),
        blocks: Object.fromEntries(
            contentTypes.map((type) => [type.name, type.blocks]),
        ),
    });
    const { data, setData, processing } = form;

    const setFeature = (
        key: string,
        value: { roles: string[]; types: string[] | null },
    ) => setData('features', { ...data.features, [key]: value });

    const setBlocks = (type: string, value: string[] | null) =>
        setData('blocks', { ...data.blocks, [type]: value });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(), { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('Editor settings')} />
            <PageHeader
                icon="settings"
                title={t('Editor settings')}
                description={t(
                    'Choose which editor tools people get and which blocks they can add.',
                )}
            />

            <form onSubmit={submit} className="flex flex-col gap-4">
                <SectionCard
                    title={t('Editor tools')}
                    description={t(
                        'Turn tools on for the roles that need them. Fewer tools keep the editor simple.',
                    )}
                    contentClassName="divide-y"
                >
                    {features.map((feature) => {
                        const value = data.features[feature.key];

                        return (
                            <div
                                key={feature.key}
                                className="grid gap-3 py-4 first:pt-0 last:pb-0 md:grid-cols-[minmax(0,1fr)_minmax(0,2fr)]"
                                data-test={`feature-${feature.key}`}
                            >
                                <div>
                                    <p className="font-medium">
                                        {feature.label}
                                    </p>
                                    <p className="text-sm text-pretty text-muted-foreground">
                                        {feature.description}
                                    </p>
                                </div>
                                <div className="flex flex-col gap-3">
                                    <fieldset>
                                        <legend className="mb-2 text-sm font-medium">
                                            {t('Roles')}
                                        </legend>
                                        <div className="flex flex-wrap gap-x-5 gap-y-2">
                                            {roles.map((role) => (
                                                <CheckOption
                                                    key={role.value}
                                                    id={`${feature.key}-role-${role.value}`}
                                                    label={role.label}
                                                    checked={value.roles.includes(
                                                        role.value,
                                                    )}
                                                    onToggle={() =>
                                                        setFeature(
                                                            feature.key,
                                                            {
                                                                ...value,
                                                                roles: toggleValue(
                                                                    value.roles,
                                                                    role.value,
                                                                ),
                                                            },
                                                        )
                                                    }
                                                />
                                            ))}
                                        </div>
                                    </fieldset>
                                    <fieldset>
                                        <legend className="mb-2 text-sm font-medium">
                                            {t('Where')}
                                        </legend>
                                        <div className="flex flex-wrap gap-x-5 gap-y-2">
                                            <CheckOption
                                                id={`${feature.key}-all-types`}
                                                label={t('Everywhere')}
                                                checked={value.types === null}
                                                onToggle={() =>
                                                    setFeature(feature.key, {
                                                        ...value,
                                                        types:
                                                            value.types === null
                                                                ? contentTypes.map(
                                                                      (type) =>
                                                                          type.name,
                                                                  )
                                                                : null,
                                                    })
                                                }
                                            />
                                            {value.types !== null &&
                                                contentTypes.map((type) => (
                                                    <CheckOption
                                                        key={type.name}
                                                        id={`${feature.key}-type-${type.name}`}
                                                        label={type.label}
                                                        checked={value.types!.includes(
                                                            type.name,
                                                        )}
                                                        onToggle={() =>
                                                            setFeature(
                                                                feature.key,
                                                                {
                                                                    ...value,
                                                                    types: toggleValue(
                                                                        value.types!,
                                                                        type.name,
                                                                    ),
                                                                },
                                                            )
                                                        }
                                                    />
                                                ))}
                                        </div>
                                    </fieldset>
                                </div>
                            </div>
                        );
                    })}
                </SectionCard>

                <SectionCard
                    title={t('Blocks')}
                    description={t(
                        'Pick the blocks people can add to each kind of content.',
                    )}
                    contentClassName="flex flex-col gap-5"
                >
                    {themeBlocks !== null && (
                        <NoticeBanner
                            tone="info"
                            title={t(
                                '{count, plural, one {Your theme allows # block} other {Your theme allows # blocks}}',
                                { count: themeBlocks.length },
                            )}
                            description={t(
                                'Blocks outside the theme list stay hidden, even when they are ticked here.',
                            )}
                        />
                    )}
                    {contentTypes.map((type) => {
                        const selected = data.blocks[type.name] ?? null;

                        return (
                            <fieldset
                                key={type.name}
                                data-test={`blocks-${type.name}`}
                            >
                                <legend className="mb-2 font-medium">
                                    {type.label}
                                </legend>
                                <CheckOption
                                    id={`blocks-${type.name}-all`}
                                    label={t('All blocks')}
                                    checked={selected === null}
                                    onToggle={() =>
                                        setBlocks(
                                            type.name,
                                            selected === null ? blocks : null,
                                        )
                                    }
                                />
                                {selected !== null && (
                                    <div className="mt-3 grid grid-cols-[repeat(auto-fill,minmax(10rem,1fr))] gap-2">
                                        {blocks.map((name) => (
                                            <CheckOption
                                                key={name}
                                                id={`blocks-${type.name}-${name}`}
                                                label={blockLabel(name)}
                                                checked={selected.includes(
                                                    name,
                                                )}
                                                onToggle={() =>
                                                    setBlocks(
                                                        type.name,
                                                        toggleValue(
                                                            selected,
                                                            name,
                                                        ),
                                                    )
                                                }
                                            />
                                        ))}
                                    </div>
                                )}
                            </fieldset>
                        );
                    })}
                </SectionCard>

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing}>
                        {t('Save settings')}
                    </Button>
                </div>
            </form>
        </>
    );
}
