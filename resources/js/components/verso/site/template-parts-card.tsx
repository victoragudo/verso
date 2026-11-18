import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { PartChoiceFields } from '@/components/verso/content/part-choice-fields';
import {
    INHERIT_STYLE,
    inheritedLabel,
} from '@/components/verso/content/part-choices';
import { partNameInSentence } from '@/components/verso/site/site-part-status';
import { SectionCard } from '@/components/verso/ui';
import { formatList, t } from '@/lib/i18n';
import { parts as saveParts } from '@/routes/admin/site-editor/templates';
import type { PartChoice, TemplatePartsRow } from '@/types';

type Props = {
    parts: PartChoice[];
    templates: TemplatePartsRow[];
};

// The select values of one template: the saved style, or "inherit" for the theme.json or site style.
function pickedOf(row: TemplatePartsRow, parts: PartChoice[]) {
    return Object.fromEntries(
        parts.map((part) => [part.name, row.saved[part.name] ?? INHERIT_STYLE]),
    );
}

// Header and footer styles per page template, like a minimal header for landing pages.
export function TemplatePartsCard({ parts, templates }: Props) {
    if (parts.length === 0 || templates.length === 0) {
        return null;
    }

    return (
        <SectionCard
            title={t('Header and footer per page template')}
            description={t(
                'Pages that use a template get these styles. A page can still pick its own in the editor.',
            )}
            className="mt-6"
        >
            <ul className="auto-grid gap-4 [--grid-min:18rem]" role="list">
                {templates.map((row) => (
                    <TemplatePartsItem key={row.name} row={row} parts={parts} />
                ))}
            </ul>
        </SectionCard>
    );
}

function TemplatePartsItem({
    row,
    parts,
}: {
    row: TemplatePartsRow;
    parts: PartChoice[];
}) {
    const [picked, setPicked] = useState(() => pickedOf(row, parts));
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const changed =
        JSON.stringify(picked) !== JSON.stringify(pickedOf(row, parts));
    const themeNote = formatList(
        parts
            .filter((part) => row.themeParts[part.name])
            .map((part) =>
                t('{style} {part}', {
                    style: inheritedLabel(part, row.themeParts[part.name]),
                    part: partNameInSentence(part.label),
                }),
            ),
    );

    const save = () =>
        router.put(
            saveParts.url(row.name),
            {
                parts: Object.fromEntries(
                    Object.entries(picked).map(([name, style]) => [
                        name,
                        style === INHERIT_STYLE ? null : style,
                    ]),
                ),
            },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onError: (found) =>
                    setErrors(
                        Object.fromEntries(
                            Object.entries(found).map(([key, message]) => [
                                key.replace(/^parts\./, ''),
                                message,
                            ]),
                        ),
                    ),
            },
        );

    return (
        <li className="grid content-start gap-3 rounded-lg border p-4">
            <div>
                <h3 className="font-medium text-foreground">{row.label}</h3>
                <p className="text-sm text-muted-foreground">
                    {themeNote
                        ? t('The theme gives it the {parts}.', {
                              parts: themeNote,
                          })
                        : t('The theme uses the site styles.')}
                </p>
            </div>
            <PartChoiceFields
                choices={parts}
                value={picked}
                onChange={setPicked}
                errors={errors}
                inheritLabel={t('As the theme says')}
                idPrefix={`template-${row.name}`}
            />
            <div>
                <Button
                    type="button"
                    variant="outline"
                    disabled={!changed || saving}
                    onClick={save}
                    className="min-h-10 md:min-h-9"
                >
                    {saving ? t('Saving…') : t('Save')}
                </Button>
            </div>
        </li>
    );
}
