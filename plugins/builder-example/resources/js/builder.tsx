import type { BuilderApi, InspectorPanelProps } from '@/builder';
import { msg, t } from '@/lib/i18n';

const WARNING_TIP = msg(
    'builder-example::Warnings catch the eye. Use them for things people must not miss.',
);
const DEFAULT_TIP = msg(
    'builder-example::Keep the message short: one sentence works best.',
);

// Tips shown in the Block tab for the notice block.
function NoticeTips({ block }: InspectorPanelProps) {
    const tone = block.attrs?.tone ?? 'info';

    return (
        <p className="text-sm text-muted-foreground">
            {t(tone === 'warning' ? WARNING_TIP : DEFAULT_TIP)}
        </p>
    );
}

// Runs in the editor after core. Each call uses the public builder API (contract version 1).
export default function register(api: BuilderApi): void {
    // An inspector panel for the notice block.
    api.inspectorPanels.register({
        name: 'builder-example/notice-tips',
        title: msg('builder-example::Notice tips'),
        order: 10,
        appliesTo: (block) => block.type === 'example-notice',
        component: NoticeTips,
    });

    // The same drop rule as the server, so the editor never offers a place the server refuses.
    api.dropRules.register({
        name: 'builder-example/no-notice-in-columns',
        allows: (parent, child) =>
            parent === 'column' && child === 'example-notice' ? false : null,
    });
}
