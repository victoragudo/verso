import type { BuilderApi } from '@/builder';
import { SparklesIcon } from '@/components/verso/icons';
import { t } from '@/lib/i18n';
import { firstHeading, imagesWithoutAlt, sectionHasText } from './page-text';
import { pageHelper } from './store';
import type { PageHelperProps } from './types';

// Which tools the page editor shows: all of them, only a hint for admins, or none.
export type PageToolsMode = 'ready' | 'setup' | 'off';

export const TOOL_NAMES = {
    button: 'ai-assistant/helper',
    sectionText: 'ai-assistant/section-text',
    headlines: 'ai-assistant/headlines',
    images: 'ai-assistant/images',
};

export function toolsMode(config: PageHelperProps | null): PageToolsMode {
    if (!config) {
        return 'off';
    }

    if (config.ready) {
        return 'ready';
    }

    return config.settingsUrl ? 'setup' : 'off';
}

// Adds the tools of one mode to the page editor registries. Returns a function that removes them.
function registerTools(api: BuilderApi, mode: PageToolsMode): () => void {
    if (mode === 'off') {
        return () => {};
    }

    const removers = [
        api.pageEditorButtons.register({
            name: TOOL_NAMES.button,
            label: t('ai-assistant::AI helper'),
            icon: SparklesIcon,
            order: 80,
            run: (context) =>
                pageHelper.open({
                    kind: mode === 'ready' ? 'menu' : 'setup',
                    context,
                }),
        }),
    ];

    if (mode === 'ready') {
        removers.push(
            api.pageSectionActions.register({
                name: TOOL_NAMES.sectionText,
                label: t('ai-assistant::Write this section with AI'),
                order: 80,
                shows: sectionHasText,
                run: ({ section, ...context }) =>
                    pageHelper.open({ kind: 'section-text', context, section }),
            }),
            api.pageSectionActions.register({
                name: TOOL_NAMES.headlines,
                label: t('ai-assistant::Suggest headlines'),
                order: 81,
                shows: (section) => firstHeading(section) !== null,
                run: ({ section, ...context }) =>
                    pageHelper.open({ kind: 'headlines', context, section }),
            }),
            api.pageSectionActions.register({
                name: TOOL_NAMES.images,
                label: t('ai-assistant::Describe images'),
                order: 82,
                shows: (section) => imagesWithoutAlt(section).length > 0,
                run: ({ section, ...context }) =>
                    pageHelper.open({ kind: 'images', context, section }),
            }),
        );
    }

    return () => removers.forEach((remove) => remove());
}

// Keeps the page editor tools in line with the helper state: they appear when the
// server props say the helper is ready and leave with the page editor.
export function connectPageTools(api: BuilderApi): () => void {
    let mode: PageToolsMode = 'off';
    let remove = () => {};

    const sync = () => {
        const next = toolsMode(pageHelper.get().config);

        if (next !== mode) {
            remove();
            mode = next;
            remove = registerTools(api, mode);
        }
    };

    sync();
    const unsubscribe = pageHelper.subscribe(sync);

    return () => {
        unsubscribe();
        remove();
        mode = 'off';
    };
}
