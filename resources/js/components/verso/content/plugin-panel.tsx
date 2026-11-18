import { PluginBlockFields } from '@/components/verso/editor/blocks/plugin-block';
import { SectionCard } from '@/components/verso/ui';
import type { PluginEntryPanel } from '@/types';

type Props = {
    panel: PluginEntryPanel;
    value: Record<string, unknown>;
    onChange: (value: Record<string, unknown>) => void;
};

// A box added by a plugin to the entry side panel. Its values go to entry.meta[panel.key].
export function PluginPanel({ panel, value, onChange }: Props) {
    return (
        <SectionCard
            title={panel.title}
            description={panel.description || undefined}
            icon="plugins"
        >
            <PluginBlockFields
                block={{
                    id: `panel-${panel.key}`,
                    type: panel.key,
                    attrs: value,
                }}
                fields={panel.fields}
                onChange={(block) => onChange(block.attrs)}
                className="grid gap-3"
            />
        </SectionCard>
    );
}

// Starting values of every panel, taken from the entry meta or the field defaults.
export function panelValuesFromMeta(
    panels: PluginEntryPanel[],
    meta: Record<string, unknown> | null | undefined,
): Record<string, Record<string, unknown>> {
    return Object.fromEntries(
        panels.map((panel) => {
            const saved = meta?.[panel.key];
            const defaults = Object.fromEntries(
                panel.fields
                    .filter((field) => field.default !== null)
                    .map((field) => [field.name, field.default]),
            );

            return [
                panel.key,
                saved && typeof saved === 'object' && !Array.isArray(saved)
                    ? { ...defaults, ...(saved as Record<string, unknown>) }
                    : defaults,
            ];
        }),
    );
}
