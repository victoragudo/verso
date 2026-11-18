import { Checkbox } from '@/components/ui/checkbox';
import { slugOf } from '@/components/verso/seo/seo-text';
import { SeoPanel, seoFromMeta } from '@/components/verso/site/seo-panel';
import type { SeoAnalysisProps } from '@/components/verso/site/seo-panel';
import { t } from '@/lib/i18n';
import type { EntrySeoFields } from '@/types';
import { registerSlotComponent } from './registry';
import type { EntryEditorContext, SlotComponentProps } from './types';

// The SEO card of the entry editor. It edits meta.seo.
export function SeoSlot({
    props,
    context,
}: SlotComponentProps<EntryEditorContext>) {
    const featured = context.meta.featured_image as
        | { src?: unknown }
        | null
        | undefined;
    const analysis = props.config ? (props as SeoAnalysisProps) : null;

    return (
        <SeoPanel
            value={seoFromMeta(context.meta)}
            onChange={(value: EntrySeoFields) => context.setMeta('seo', value)}
            fallbackTitle={context.title}
            fallbackDescription={context.excerpt}
            url={context.entry?.url}
            blocks={context.blocks}
            slug={context.entry?.slug ?? slugOf(context.entry?.url)}
            featuredImage={
                typeof featured?.src === 'string' ? featured.src : null
            }
            analysis={analysis}
        />
    );
}

// The "Allow comments" switch of the Publish card. It edits meta.comments_open.
export function CommentsSwitchSlot({
    context,
}: SlotComponentProps<EntryEditorContext>) {
    return (
        <label className="flex min-h-10 items-center gap-2.5 text-sm md:min-h-8">
            <Checkbox
                checked={context.meta.comments_open === true}
                onCheckedChange={(checked) =>
                    context.setMeta('comments_open', checked === true)
                }
            />
            {t('Allow comments')}
        </label>
    );
}

// Registers the slot components of the core modules.
export function registerCoreSlots(): void {
    registerSlotComponent('site.seo', SeoSlot, {
        initialMeta: (meta) => ({ seo: seoFromMeta(meta) }),
    });
    registerSlotComponent('comments.switch', CommentsSwitchSlot, {
        initialMeta: (_meta, props) => ({ comments_open: props.open === true }),
    });
}
