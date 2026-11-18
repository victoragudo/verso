import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { inlineLabel } from '@/components/verso/content/inline-label';
import { Pagination } from '@/components/verso/content/pagination';
import {
    DataTable,
    EmptyState,
    PageHeader,
    SectionCard,
    SearchableSelect,
} from '@/components/verso/ui';
import type { DataTableColumn, RowAction } from '@/components/verso/ui';
import { formatNumber, t } from '@/lib/i18n';
import { destroy, index, store, update } from '@/routes/admin/terms';
import type { Paginated, TaxonomyInfo, Term } from '@/types';

type ParentOption = { id: number; name: string; parent_id: number | null };

type Props = {
    taxonomy: TaxonomyInfo;
    terms: Paginated<Term>;
    parents: ParentOption[];
    filters: { search: string };
};

type TermForm = {
    name: string;
    slug: string;
    description: string;
    parent_id: string;
};

const NO_PARENT = 'none';
const NEW_TERM_PREFIX = 'new-term';

const focusNewTerm = () =>
    document.getElementById(`${NEW_TERM_PREFIX}-name`)?.focus();

// Lists the terms of one taxonomy, with forms to add, edit and delete them.
export default function TermsIndex({
    taxonomy,
    terms,
    parents,
    filters,
}: Props) {
    const [search, setSearch] = useState(filters.search);
    const [editing, setEditing] = useState<Term | null>(null);
    const parentName = (id: number | null) =>
        parents.find((parent) => parent.id === id)?.name ?? '';

    const submitSearch = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            index.url(taxonomy.name, { query: { search } }),
            {},
            { preserveState: true },
        );
    };

    const remove = (term: Term) => {
        if (window.confirm(t('Delete “{name}”?', { name: term.name }))) {
            router.delete(
                destroy.url({ taxonomy: taxonomy.name, term: term.id }),
                { preserveScroll: true },
            );
        }
    };

    const singular = inlineLabel(taxonomy.label);
    const plural = inlineLabel(taxonomy.pluralLabel);
    const addLabel = t('Add {type}', { type: singular });

    const columns: DataTableColumn<Term>[] = [
        {
            key: 'name',
            header: t('Name'),
            primary: true,
            className: 'max-w-0 w-full',
            cell: (term) => (
                <button
                    type="button"
                    onClick={() => setEditing(term)}
                    className="block max-w-full truncate text-start font-medium text-foreground hover:text-brand"
                >
                    {term.name}
                </button>
            ),
        },
        {
            key: 'slug',
            header: t('Slug'),
            className: 'whitespace-nowrap text-muted-foreground',
            cell: (term) => term.slug,
        },
        ...(taxonomy.hierarchical
            ? [
                  {
                      key: 'parent',
                      header: t('Parent'),
                      className: 'whitespace-nowrap',
                      cell: (term: Term) =>
                          parentName(term.parent_id) || (
                              <span className="text-muted-foreground">
                                  {t('None')}
                              </span>
                          ),
                  },
              ]
            : []),
        {
            key: 'entries',
            header: t('Entries'),
            className: 'whitespace-nowrap md:text-end',
            cell: (term) => formatNumber(term.entries_count ?? 0),
        },
    ];

    const rowActions = (term: Term): RowAction[] => [
        { label: t('Edit'), onSelect: () => setEditing(term) },
        {
            label: t('Delete'),
            onSelect: () => remove(term),
            destructive: true,
        },
    ];

    return (
        <>
            <Head title={taxonomy.pluralLabel} />
            <PageHeader
                icon={taxonomy.icon}
                title={taxonomy.pluralLabel}
                description={t('Group your entries with {type}.', {
                    type: plural,
                })}
            />

            <div className="grid gap-6 @3xl/page:grid-cols-[minmax(0,1fr)_clamp(20rem,24cqi,30rem)] @3xl/page:items-start">
                <SectionCard
                    title={addLabel}
                    className="@3xl/page:sticky @3xl/page:top-20 @3xl/page:order-last"
                >
                    <TermFormFields
                        taxonomy={taxonomy}
                        parents={parents}
                        term={null}
                    />
                </SectionCard>

                <div className="flex min-w-0 flex-col gap-4">
                    <form
                        onSubmit={submitSearch}
                        role="search"
                        className="relative w-full sm:max-w-72"
                    >
                        <Search className="pointer-events-none absolute start-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('Search {type}', { type: plural })}
                            aria-label={t('Search {type}', { type: plural })}
                            className="ps-9"
                        />
                    </form>

                    <DataTable
                        rows={terms.data}
                        columns={columns}
                        rowKey={(term) => term.id}
                        caption={taxonomy.pluralLabel}
                        rowLabel={(term) => term.name}
                        rowActions={rowActions}
                        empty={
                            <EmptyState
                                icon={taxonomy.icon}
                                title={
                                    filters.search
                                        ? t('No {type} match “{search}”.', {
                                              type: plural,
                                              search: filters.search,
                                          })
                                        : t('No {type} yet.', { type: plural })
                                }
                                action={
                                    !filters.search && (
                                        <Button
                                            variant="outline"
                                            onClick={focusNewTerm}
                                        >
                                            <Plus />
                                            {addLabel}
                                        </Button>
                                    )
                                }
                            />
                        }
                    />

                    <Pagination page={terms} />
                </div>
            </div>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('Edit {type}', { type: singular })}
                        </DialogTitle>
                    </DialogHeader>
                    {editing && (
                        <TermFormFields
                            key={editing.id}
                            taxonomy={taxonomy}
                            parents={parents.filter(
                                (parent) => parent.id !== editing.id,
                            )}
                            term={editing}
                            onSaved={() => setEditing(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

// Fields to add a new term or edit one.
function TermFormFields({
    taxonomy,
    parents,
    term,
    onSaved,
}: {
    taxonomy: TaxonomyInfo;
    parents: ParentOption[];
    term: Term | null;
    onSaved?: () => void;
}) {
    const form = useForm<TermForm>({
        name: term?.name ?? '',
        slug: term?.slug ?? '',
        description: term?.description ?? '',
        parent_id: term?.parent_id ? String(term.parent_id) : NO_PARENT,
    });
    const { data, setData, errors, processing } = form;
    const prefix = term ? `term-${term.id}` : NEW_TERM_PREFIX;
    const parentOptions = useMemo(
        () => [
            { value: NO_PARENT, label: t('No parent') },
            ...parents.map((parent) => ({
                value: String(parent.id),
                label: parent.name,
            })),
        ],
        [parents],
    );

    form.transform((values) => ({
        ...values,
        parent_id:
            !taxonomy.hierarchical || values.parent_id === NO_PARENT
                ? null
                : Number(values.parent_id),
    }));

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (term) {
            form.put(update.url({ taxonomy: taxonomy.name, term: term.id }), {
                preserveScroll: true,
                onSuccess: () => onSaved?.(),
            });
        } else {
            form.post(store.url(taxonomy.name), {
                preserveScroll: true,
                onSuccess: () => form.reset(),
            });
        }
    };

    return (
        <form onSubmit={submit} className="grid gap-4">
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-name`}>{t('Name')}</Label>
                <Input
                    id={`${prefix}-name`}
                    value={data.name}
                    onChange={(event) => setData('name', event.target.value)}
                    required
                />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-slug`}>{t('Slug')}</Label>
                <Input
                    id={`${prefix}-slug`}
                    value={data.slug}
                    onChange={(event) => setData('slug', event.target.value)}
                    placeholder={t('Made from the name when empty')}
                />
                <InputError message={errors.slug} />
            </div>
            {taxonomy.hierarchical && (
                <div className="grid gap-2">
                    <Label htmlFor={`${prefix}-parent`}>{t('Parent')}</Label>
                    <SearchableSelect
                        id={`${prefix}-parent`}
                        value={data.parent_id}
                        onValueChange={(value) => setData('parent_id', value)}
                        options={parentOptions}
                        searchPlaceholder={t('Search terms')}
                        emptyText={t('No term matches.')}
                        aria-invalid={Boolean(errors.parent_id)}
                    />
                    <InputError message={errors.parent_id} />
                </div>
            )}
            <div className="grid gap-2">
                <Label htmlFor={`${prefix}-description`}>
                    {t('Description')}
                </Label>
                <Textarea
                    id={`${prefix}-description`}
                    value={data.description}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                    rows={3}
                />
                <InputError message={errors.description} />
            </div>
            <Button type="submit" disabled={processing}>
                {term
                    ? t('Save')
                    : t('Add {type}', { type: inlineLabel(taxonomy.label) })}
            </Button>
        </form>
    );
}

TermsIndex.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: props.taxonomy.pluralLabel,
            href: index(props.taxonomy.name),
        },
    ],
});
