import { Monitor, Smartphone, Tablet } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useId } from 'react';
import { builder, featureEnabled } from '@/builder';
import type { EditorManifest } from '@/builder';
import { useEditorDevice } from '@/builder/device';
import { labelInSentence } from '@/components/verso/editor/blocks/shared';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import type { Block } from '@/types';

const DEVICE_ICONS: Record<string, LucideIcon> = {
    base: Monitor,
    tablet: Tablet,
    mobile: Smartphone,
};

const NO_LIMITS: EditorManifest['limits'] = {
    colors: null,
    fonts: null,
    spacing: null,
};

type Props = {
    block: Block | null;
    onChange: (block: Block) => void;
    editor?: EditorManifest;
};

// The "Block" tab of the editor sidebar: the panels registered for the selected block.
export function BlockInspector({ block, onChange, editor }: Props) {
    // Shared with the canvas, so the canvas previews the values of this screen size.
    const [device, setDevice] = useEditorDevice();
    const name = useId();

    if (block === null) {
        return (
            <p className="text-sm text-muted-foreground">
                {t(
                    'Click a block in the page to change how it looks. Its menu can move, copy or remove it.',
                )}
            </p>
        );
    }

    const definition = builder.blocks.get(block.type);
    const label = definition ? t(definition.label) : block.type;
    const panels = builder.inspectorPanels
        .all()
        .filter(
            (panel) =>
                (!panel.feature || featureEnabled(editor, panel.feature)) &&
                panel.appliesTo(block),
        );

    return (
        <div className="grid gap-4" data-test="block-inspector">
            <div className="flex items-center justify-between gap-2">
                <p className="text-sm font-semibold">{label}</p>
                <fieldset className="flex rounded-md border p-0.5">
                    <legend className="sr-only">{t('Screen size')}</legend>
                    {builder.breakpoints.all().map((breakpoint) => {
                        const Icon = DEVICE_ICONS[breakpoint.name] ?? Monitor;

                        return (
                            <label
                                key={breakpoint.name}
                                title={t(breakpoint.label)}
                                className={cn(
                                    'flex size-7 cursor-pointer items-center justify-center rounded text-muted-foreground transition-colors hover:bg-accent/60 hover:text-foreground has-[:checked]:bg-accent has-[:checked]:text-foreground has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-ring',
                                )}
                            >
                                <input
                                    type="radio"
                                    name={name}
                                    value={breakpoint.name}
                                    checked={device === breakpoint.name}
                                    onChange={() => setDevice(breakpoint.name)}
                                    className="sr-only"
                                />
                                <Icon className="size-4" aria-hidden />
                                <span className="sr-only">
                                    {t(breakpoint.label)}
                                </span>
                            </label>
                        );
                    })}
                </fieldset>
            </div>
            {device !== 'base' && (
                <p className="text-xs text-muted-foreground">
                    {t(
                        'Changes here only apply to {size} screens and smaller.',
                        {
                            size: labelInSentence(
                                builder.breakpoints.get(device)?.label ??
                                    device,
                            ),
                        },
                    )}
                </p>
            )}
            {panels.map((panel) => {
                const Panel = panel.component;

                return (
                    <section key={panel.name} className="grid gap-2">
                        <h3 className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                            {t(panel.title)}
                        </h3>
                        <Panel
                            block={block}
                            onChange={onChange}
                            device={device}
                            limits={editor?.limits ?? NO_LIMITS}
                        />
                    </section>
                );
            })}
            {panels.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t('This block has no extra settings.')}
                </p>
            )}
        </div>
    );
}
