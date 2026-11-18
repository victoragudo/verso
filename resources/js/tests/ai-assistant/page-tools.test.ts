import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { builder } from '@/builder';
import { pageHelper } from '@/pages/admin/plugins/ai-assistant/page-helper/store';
import {
    connectPageTools,
    TOOL_NAMES,
    toolsMode,
} from '@/pages/admin/plugins/ai-assistant/page-helper/tools';
import register from '../../../../plugins/ai-assistant/resources/js/builder';
import {
    editorContext,
    gallerySection,
    helperProps,
    heroSection,
} from './page-helper-fixtures';

const buttonNames = () => builder.pageEditorButtons.all().map((b) => b.name);
const actionNames = () => builder.pageSectionActions.all().map((a) => a.name);

let disconnect = () => {};

beforeEach(() => {
    pageHelper.reset();
    builder.pageEditorButtons.clear();
    builder.pageSectionActions.clear();
    disconnect = connectPageTools(builder);
});

afterEach(() => {
    disconnect();
    pageHelper.reset();
});

describe('readiness', () => {
    it('picks the mode from the server props', () => {
        expect(toolsMode(null)).toBe('off');
        expect(toolsMode(helperProps())).toBe('ready');
        expect(
            toolsMode(
                helperProps({
                    ready: false,
                    settingsUrl: '/admin/ai-assistant',
                }),
            ),
        ).toBe('setup');
        expect(toolsMode(helperProps({ ready: false }))).toBe('off');
    });

    it('adds nothing before the page editor shares the props', () => {
        expect(buttonNames()).toEqual([]);
        expect(actionNames()).toEqual([]);
    });

    it('adds the button and the section actions when the helper is ready', () => {
        pageHelper.setConfig(helperProps());

        expect(buttonNames()).toEqual([TOOL_NAMES.button]);
        expect(builder.pageEditorButtons.get(TOOL_NAMES.button)?.label).toBe(
            'AI helper',
        );
        expect(
            builder.pageSectionActions.all().map((action) => action.label),
        ).toEqual([
            'Write this section with AI',
            'Suggest headlines',
            'Describe images',
        ]);
    });

    it('only adds a setup button for admins when the key is missing', () => {
        pageHelper.setConfig(
            helperProps({ ready: false, settingsUrl: '/admin/ai-assistant' }),
        );

        expect(buttonNames()).toEqual([TOOL_NAMES.button]);
        expect(actionNames()).toEqual([]);

        builder.pageEditorButtons.get(TOOL_NAMES.button)?.run(editorContext());

        expect(pageHelper.get().request?.kind).toBe('setup');
    });

    it('adds nothing for editors when the key is missing', () => {
        pageHelper.setConfig(helperProps({ ready: false }));

        expect(buttonNames()).toEqual([]);
        expect(actionNames()).toEqual([]);
    });

    it('removes the tools when the page editor closes', () => {
        pageHelper.setConfig(helperProps());
        pageHelper.setConfig(null);

        expect(buttonNames()).toEqual([]);
        expect(actionNames()).toEqual([]);
    });

    it('is what the builder module of the plugin connects', () => {
        disconnect();
        builder.pageEditorButtons.clear();
        register(builder);
        pageHelper.setConfig(helperProps());

        expect(buttonNames()).toEqual([TOOL_NAMES.button]);
    });
});

describe('section actions', () => {
    beforeEach(() => pageHelper.setConfig(helperProps()));

    it('only shows actions that fit the section', () => {
        const shows = (name: string, section = heroSection()) =>
            builder.pageSectionActions.get(name)?.shows?.(section);

        expect(shows(TOOL_NAMES.sectionText)).toBe(true);
        expect(shows(TOOL_NAMES.headlines)).toBe(true);
        expect(shows(TOOL_NAMES.images)).toBe(true);
        expect(shows(TOOL_NAMES.sectionText, gallerySection())).toBe(false);
        expect(shows(TOOL_NAMES.headlines, gallerySection())).toBe(false);
        expect(shows(TOOL_NAMES.images, gallerySection())).toBe(true);
    });

    it('opens the dialog of the action with the section and the context', () => {
        const context = editorContext();
        const section = heroSection();

        builder.pageSectionActions
            .get(TOOL_NAMES.headlines)
            ?.run({ ...context, section });

        const request = pageHelper.get().request;

        expect(request?.kind).toBe('headlines');
        expect(request && 'section' in request && request.section).toBe(
            section,
        );
        expect(request?.context.change).toBe(context.change);
    });

    it('opens the menu from the top bar button', () => {
        builder.pageEditorButtons.get(TOOL_NAMES.button)?.run(editorContext());

        expect(pageHelper.get().request?.kind).toBe('menu');
    });
});
