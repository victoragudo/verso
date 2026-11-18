import { LaunchChecklistCard } from '@/components/verso/easy/launch-checklist-card';
import { SimpleModeSwitch } from '@/components/verso/easy/simple-mode-switch';
import {
    registerRowAction,
    registerSlotComponent,
} from '@/components/verso/slots/registry';
import type {
    EmptyContext,
    EntryRowContext,
    SlotComponentProps,
} from '@/components/verso/slots/types';
import { SectionCard } from '@/components/verso/ui';
import type { LaunchChecklistData } from '@/lib/easy/launch-checklist';
import { t } from '@/lib/i18n';

// Stands for the entry id in the "Edit on the page" link the server sends. Same as EasyServiceProvider::ENTRY_PLACEHOLDER.
export const ENTRY_PLACEHOLDER = '__entry__';

// The launch checklist card at the top of the dashboard.
export function LaunchChecklistSlot({
    props,
}: SlotComponentProps<EmptyContext>) {
    return <LaunchChecklistCard data={props as LaunchChecklistData} />;
}

// The simple mode card on the profile page.
export function SimpleModeProfileSlot({
    props,
}: SlotComponentProps<EmptyContext>) {
    return (
        <SectionCard
            title={t('How the admin looks')}
            description={
                props.siteDefault === true
                    ? t('This site starts everyone in simple mode.')
                    : undefined
            }
        >
            <SimpleModeSwitch enabled={props.enabled === true} />
        </SectionCard>
    );
}

// "Edit on the page" in the row menu of the page list, for pages the person may change.
export function editOnPageAction(
    context: EntryRowContext,
    props: Record<string, unknown>,
) {
    const can = context.row.can as { update?: boolean } | undefined;

    if (typeof props.href !== 'string' || can?.update !== true) {
        return null;
    }

    return {
        label: t('Edit on the page'),
        href: props.href.replace(ENTRY_PLACEHOLDER, String(context.row.id)),
    };
}

// Registers the slot components of Easy mode.
export function registerEasySlots(): void {
    registerSlotComponent('easy.launch-checklist', LaunchChecklistSlot);
    registerSlotComponent('easy.simple-mode', SimpleModeProfileSlot);
    registerRowAction('easy.edit-on-page', editOnPageAction);
}
