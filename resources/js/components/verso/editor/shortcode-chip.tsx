import {
    Node,
    NodeViewWrapper,
    ReactNodeViewRenderer,
    type NodeViewProps,
} from '@tiptap/react';
import { Puzzle } from 'lucide-react';
import { createContext, useContext, useState } from 'react';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { t } from '@/lib/i18n';
import type { Block, PluginBlockDefinition } from '@/types';
import { PluginBlockFields } from './blocks/plugin-block';
import {
    attrsToShortcode,
    shortcodeToAttrs,
    singleShortcode,
} from './shortcodes';

// Plugin blocks that can be written as shortcodes, shared with every text editor of the entry.
export const ShortcodeDefinitionsContext = createContext<
    PluginBlockDefinition[]
>([]);

export function useShortcodeDefinitions(): PluginBlockDefinition[] {
    return useContext(ShortcodeDefinitionsContext);
}

// A shortcode inside text, shown as a chip that cannot be edited letter by letter.
// It is stored as plain shortcode text, so the HTML of the paragraph keeps "[form slug=...]".
export function createShortcodeNode(definitions: PluginBlockDefinition[]) {
    return Node.create({
        name: 'shortcode',
        group: 'inline',
        inline: true,
        atom: true,
        selectable: true,

        addAttributes() {
            return { raw: { default: '' } };
        },

        parseHTML() {
            return [
                {
                    tag: 'span[data-shortcode]',
                    getAttrs: (element) => ({
                        raw: (element as HTMLElement).textContent ?? '',
                    }),
                },
            ];
        },

        renderHTML({ node }) {
            return ['span', { 'data-shortcode': '' }, String(node.attrs.raw)];
        },

        renderText({ node }) {
            return String(node.attrs.raw);
        },

        addNodeView() {
            return ReactNodeViewRenderer((props: NodeViewProps) => (
                <ShortcodeChip {...props} definitions={definitions} />
            ));
        },
    });
}

function ShortcodeChip({
    node,
    updateAttributes,
    definitions,
}: NodeViewProps & { definitions: PluginBlockDefinition[] }) {
    const [open, setOpen] = useState(false);
    const raw = String(node.attrs.raw);
    const parsed = singleShortcode(raw);
    const definition = definitions.find((item) => item.name === parsed?.name);
    const attrs =
        parsed && definition ? shortcodeToAttrs(parsed, definition) : null;
    const label = definition?.label ?? parsed?.name ?? t('Shortcode');
    const block: Block = {
        id: `chip-${parsed?.name ?? 'shortcode'}`,
        type: parsed?.name ?? '',
        attrs: attrs ?? {},
        content: '',
        children: [],
    };

    const change = (next: Block) => {
        if (parsed) {
            updateAttributes({
                raw: attrsToShortcode(
                    parsed.name,
                    next.attrs ?? {},
                    definition,
                    parsed,
                ),
            });
        }
    };

    return (
        <NodeViewWrapper as="span" className="inline-block align-baseline">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger
                    type="button"
                    contentEditable={false}
                    title={raw}
                    data-invalid={attrs === null ? '' : undefined}
                    className="mx-0.5 inline-flex max-w-60 items-center gap-1 rounded-md border border-border bg-muted px-1.5 py-0.5 align-baseline text-xs font-medium text-foreground hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-[invalid]:border-warning-text/40 data-[invalid]:bg-warning-soft data-[invalid]:text-warning-text"
                >
                    <Puzzle aria-hidden="true" className="size-3 shrink-0" />
                    <span className="truncate">{label}</span>
                    <span className="sr-only">
                        {t('shortcode, edit settings')}
                    </span>
                </PopoverTrigger>
                <PopoverContent className="w-80 p-3" sheetOnMobile>
                    <p className="mb-2 text-sm font-medium">{label}</p>
                    {definition && attrs !== null ? (
                        <PluginBlockFields
                            block={block}
                            onChange={change}
                            fields={definition.fields}
                            className="grid gap-3"
                        />
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'This shortcode is unknown or has a wrong attribute, so visitors see it as text.',
                            )}
                        </p>
                    )}
                    <code className="mt-3 block rounded bg-muted px-2 py-1 font-mono text-xs break-all">
                        {raw}
                    </code>
                </PopoverContent>
            </Popover>
        </NodeViewWrapper>
    );
}
