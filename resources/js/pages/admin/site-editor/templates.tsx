import { Head } from '@inertiajs/react';
import { TemplatePartsCard } from '@/components/verso/site/template-parts-card';
import { partNameInSentence } from '@/components/verso/site/site-part-status';
import { PageHeader, SectionCard } from '@/components/verso/ui';
import { formatList, msg, t } from '@/lib/i18n';
import { siteEditor } from '@/routes/admin';
import { templates } from '@/routes/admin/site-editor';
import type { PartChoice, TemplatePartsRow } from '@/types';

type TemplateRow = {
    label: string;
    kind: string;
    template: string | null;
    tried: string[];
};

type Props = {
    theme: { name: string; slug: string };
    rows: TemplateRow[];
    parts: { name: string; label: string; style: string }[];
    templateParts?: { parts: PartChoice[]; templates: TemplatePartsRow[] };
};

// Which theme file each kind of page uses, and the header and footer styles every page gets.
export default function SiteEditorTemplates({
    theme,
    rows,
    parts,
    templateParts,
}: Props) {
    return (
        <>
            <Head title={t('Templates')} />
            <PageHeader
                title={t('Templates')}
                icon="siteEditor"
                description={t(
                    'How {theme} builds each kind of page. The first file that exists in the theme is used.',
                    { theme: theme.name },
                )}
            />

            {parts.length > 0 && (
                <p className="mb-6 text-sm text-muted-foreground">
                    {t(
                        'Every page gets {parts}. Change them in the Site Editor.',
                        {
                            parts: formatList(
                                parts.map((part) =>
                                    t('the {style} {part}', {
                                        style: part.style,
                                        part: partNameInSentence(part.label),
                                    }),
                                ),
                            ),
                        },
                    )}
                </p>
            )}

            <SectionCard contentClassName="p-0 sm:p-0">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <caption className="sr-only">
                            {t('Templates used by each kind of page')}
                        </caption>
                        <thead className="border-b text-start text-muted-foreground">
                            <tr>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    {t('Kind of page')}
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    {t('Template used')}
                                </th>
                                <th
                                    scope="col"
                                    className="px-4 py-3 font-medium"
                                >
                                    {t('Files looked for, in order')}
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((row) => (
                                <tr
                                    key={row.label}
                                    className="border-b last:border-b-0"
                                >
                                    <th
                                        scope="row"
                                        className="px-4 py-3 text-start font-medium"
                                    >
                                        {row.label}
                                    </th>
                                    <td className="px-4 py-3">
                                        {row.template ? (
                                            <code className="rounded bg-muted px-1.5 py-0.5">
                                                {row.template}
                                            </code>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                {t('Built-in fallback')}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {formatList(row.tried, 'unit')}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </SectionCard>

            {templateParts && (
                <TemplatePartsCard
                    parts={templateParts.parts}
                    templates={templateParts.templates}
                />
            )}
        </>
    );
}

SiteEditorTemplates.layout = {
    breadcrumbs: [
        { title: msg('Site Editor'), href: siteEditor() },
        { title: msg('Templates'), href: templates() },
    ],
};
