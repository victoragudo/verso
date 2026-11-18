import type {
    EntryEditorContext,
    SlotComponentProps,
    SlotRegistry,
} from '@/components/verso/slots';
import {
    isEmptyContent,
    withImageAlt,
} from '@/pages/admin/plugins/ai-assistant/block-helpers';
import { AssistantPanel } from '@/pages/admin/plugins/ai-assistant/components/assistant-panel';
import type { AssistantApply } from '@/pages/admin/plugins/ai-assistant/components/assistant-panel';
import { PageHelperHost } from '@/pages/admin/plugins/ai-assistant/page-helper/page-helper-host';
import type { AiAssistantProps } from '@/pages/admin/plugins/ai-assistant/types';

// The assistant card of the entry editor. Accepted results change the editor state, never the saved entry.
export function AssistantSlot({
    props,
    context,
}: SlotComponentProps<EntryEditorContext>) {
    const apply: AssistantApply = {
        outline: (outline) =>
            context.setBlocks((current) =>
                isEmptyContent(current) ? outline : [...current, ...outline],
            ),
        excerpt: (excerpt) => context.setExcerpt(excerpt),
        seo: (suggestion) =>
            context.updateMeta('seo', (current) => ({
                ...(current as Record<string, unknown>),
                title: suggestion.title,
                description: suggestion.description,
            })),
        imageAlt: (mediaId, alt) =>
            context.setBlocks((current) => withImageAlt(current, mediaId, alt)),
        translation: (result) => {
            context.setTitle(result.title);
            context.replaceBlocks(result.blocks);
        },
    };

    return (
        <AssistantPanel
            config={props as AiAssistantProps}
            title={context.title}
            blocks={context.blocks}
            editor={context.activeEditor}
            apply={apply}
        />
    );
}

// Registers the components of the AI Assistant plugin.
export default function register(registry: SlotRegistry): void {
    registry.registerSlotComponent('ai-assistant.panel', AssistantSlot);
    registry.registerSlotComponent('ai-assistant.page-helper', PageHelperHost);
}
