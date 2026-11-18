import { expect, test } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';
import {
    StepClock,
    expectAccessible,
    expectNoSidewaysScroll,
} from './support/checks';
import { installVerso } from './support/install';

// A designer builds a spring landing page from scratch with the builder: interactive blocks, a style preset,
// a sticky header from the theme builder and a slide-in popup, then checks the public page.
// It runs at 1440 (computer), 1024 (tablet) and 390 (phone) pixels wide, each on its own throwaway install.

const designer = {
    siteName: 'Harbor Lane Bakery',
    name: 'Inês Carvalho',
    email: 'ines@harborlanebakery.pt',
    password: 'Croissant2026',
};

const pageTitle = 'Spring opening at Harbor Lane';

// The Add blocks panel of the editor: a column on wide screens, a sheet on small ones.
async function addBlock(
    page: Page,
    name: string,
    options: { source?: string; search?: string } = {},
): Promise<void> {
    const toggle = page
        .getByRole('button', { name: 'Add blocks', exact: true })
        .first();

    if ((await toggle.getAttribute('aria-pressed')) !== 'true') {
        await toggle.click();
    }

    const panel = page
        .getByRole('complementary', { name: 'Add blocks' })
        .or(page.getByRole('dialog', { name: 'Add blocks' }));
    await panel
        .getByRole('group', { name: 'What to add' })
        .getByRole('button', { name: options.source ?? 'Blocks', exact: true })
        .click();
    const searchBox = panel.getByRole('searchbox');
    await searchBox.fill(options.search ?? name);
    await panel.getByRole('button', { name, exact: true }).first().click();
}

// On small screens the Add blocks panel is a sheet over the page: close it to work on the blocks.
async function closeAddPanel(page: Page): Promise<void> {
    const sheet = page.getByRole('dialog', { name: 'Add blocks' });

    if (await sheet.isVisible()) {
        await page.keyboard.press('Escape');
        await expect(sheet).toBeHidden();
    }
}

// The inputs of one field of the blocks in the canvas, by field name. Their ids end with the field name.
function fields(page: Page, name: string): Locator {
    return page.locator(`[data-block-id] input[id$="-${name}"]`);
}

// Layout shift of the page since it loaded, from the browser's own measure.
async function layoutShift(page: Page): Promise<number> {
    return page.evaluate(
        () =>
            new Promise<number>((resolve) => {
                let total = 0;
                new PerformanceObserver((list) => {
                    for (const entry of list.getEntries() as (PerformanceEntry & {
                        value: number;
                        hadRecentInput: boolean;
                    })[]) {
                        if (!entry.hadRecentInput) {
                            total += entry.value;
                        }
                    }
                }).observe({ type: 'layout-shift', buffered: true });
                setTimeout(() => resolve(total), 1500);
            }),
    );
}

// Opens the settings sidebar of a full screen editor when it is a sheet.
async function openSettings(page: Page): Promise<void> {
    const sidebar = page
        .getByRole('complementary', { name: 'Settings' })
        .or(page.getByRole('dialog', { name: 'Settings' }));

    if (!(await sidebar.isVisible())) {
        await page
            .getByRole('button', { name: 'Settings', exact: true })
            .locator('visible=true')
            .last()
            .click();
        await expect(sidebar).toBeVisible();
    }
}

test('a designer builds a landing page from scratch with the builder', async ({
    page,
}, info) => {
    const clock = new StepClock(info);
    const wide = (page.viewportSize()?.width ?? 0) >= 1024;

    await clock.step('Install Verso', async () => {
        await installVerso(page, designer);
        await expect(page).toHaveURL(/\/admin/);
    });

    await clock.step('Start a new page', async () => {
        await page.goto('/admin/entries/page/create');
        await page
            .getByRole('textbox', { name: 'Title', exact: true })
            .fill(pageTitle);
        await expectNoSidewaysScroll(page);
    });

    await clock.step(
        'Add a ready hero section and a grid of services',
        async () => {
            await addBlock(page, 'Welcome banner', {
                source: 'Sections',
                search: 'welcome',
            });
            await expect(page.locator('[data-block-id]').first()).toBeVisible();
            await addBlock(page, 'Grid');
            await addBlock(page, 'Icon list');
            await closeAddPanel(page);
            await expect(fields(page, 'text').last()).toHaveValue(
                'Third point',
            );
            await fields(page, 'text')
                .last()
                .fill('Free coffee on opening day');
        },
    );

    await clock.step('Add tabs, an accordion and a counter', async () => {
        await addBlock(page, 'Tabs');
        await closeAddPanel(page);
        await fields(page, 'title').nth(0).fill('Breads');
        await fields(page, 'title').nth(1).fill('Pastries');
        await addBlock(page, 'Accordion');
        await closeAddPanel(page);
        await fields(page, 'title')
            .nth(2)
            .fill('Do you bake gluten free bread?');
        await addBlock(page, 'Counter');
        await closeAddPanel(page);
        await fields(page, 'number').last().fill('1200');
        await fields(page, 'suffix').last().fill('+');
        await fields(page, 'label').last().fill('loaves every week');
        await addBlock(page, 'Shape divider');
        await expectNoSidewaysScroll(page);
    });

    await clock.step('Give the hero the Hero style preset', async () => {
        await closeAddPanel(page);
        await page
            .locator('[data-block-id]')
            .first()
            .click({ position: { x: 8, y: 8 } });
        await openSettings(page);
        await page.getByRole('tab', { name: 'Block' }).click();
        const presets = page.getByLabel('Style preset');
        await presets.first().selectOption('hero');
        await expect(presets.first()).toHaveValue('hero');
        await page.keyboard.press('Escape');
    });

    await clock.step('Publish the page', async () => {
        await page
            .getByRole('button', { name: 'Publish', exact: true })
            .click();
        await expect(
            page
                .getByRole('button', { name: 'Update', exact: true })
                .locator('visible=true')
                .first(),
        ).toBeVisible();
    });

    await clock.step('Build a sticky header in the theme builder', async () => {
        await page.goto('/admin/theme-builder');
        await expectNoSidewaysScroll(page);
        await expectAccessible(page, 'Theme builder');
        await page.getByLabel('Name of the new header').fill('Spring header');
        await page
            .locator('[data-test="layouts-header"]')
            .getByRole('button', { name: 'Create' })
            .click();
        await expect(page).toHaveURL(/theme-builder\/\d+$/);
        await openSettings(page);
        await page.getByLabel('Stay at the top while scrolling').check();
        await page.getByLabel('When people scroll down').selectOption('solid');
        await page.keyboard.press('Escape');
        await page
            .getByRole('button', { name: 'Publish', exact: true })
            .click();
        await expect(
            page.getByText('Spring header is live. Visitors see it now.'),
        ).toBeVisible();
    });

    await clock.step('Build a slide-in popup', async () => {
        await page.goto('/admin/theme-builder');
        await page
            .getByLabel('Name of the new popup or banner')
            .fill('Opening offer');
        await page
            .locator('[data-test="layouts-popup"]')
            .getByRole('button', { name: 'Create' })
            .click();
        await expect(page).toHaveURL(/theme-builder\/\d+$/);
        await openSettings(page);
        await page.getByLabel('Kind').selectOption('slide-in');
        await page.getByLabel('When it shows').selectOption('load');
        await page.getByLabel('How often').selectOption('session');
        await page.keyboard.press('Escape');
        await page
            .getByRole('button', { name: 'Publish', exact: true })
            .click();
        await expect(
            page.getByText('Opening offer is live. Visitors see it now.'),
        ).toBeVisible();
    });

    await clock.step('Visit the page as a visitor', async () => {
        await page.context().clearCookies();
        await page.goto('/spring-opening-at-harbor-lane');
        await expect(page.getByRole('tablist')).toBeVisible();
        await expect(page.locator('header.verso-header--sticky')).toBeVisible();
        expect(await layoutShift(page)).toBeLessThan(0.1);
        await expectNoSidewaysScroll(page);
    });

    await clock.step('The popup shows once and closes', async () => {
        const popup = page.getByRole('dialog', { name: 'Opening offer' });
        await expect(popup).toBeVisible();
        await popup.getByRole('button', { name: 'Close' }).click();
        await expect(popup).toBeHidden();
        await page.reload();
        await expect(page.getByRole('tablist')).toBeVisible();
        await expect(
            page.getByRole('dialog', { name: 'Opening offer' }),
        ).toBeHidden();
    });

    await clock.step('Tabs work with the mouse and the keyboard', async () => {
        const pastries = page.getByRole('tab', { name: 'Pastries' });
        await pastries.click();
        await expect(pastries).toHaveAttribute('aria-selected', 'true');
        await page.keyboard.press('ArrowLeft');
        await expect(page.getByRole('tab', { name: 'Breads' })).toBeFocused();
        await expect(page.getByRole('tab', { name: 'Breads' })).toHaveAttribute(
            'aria-selected',
            'true',
        );
    });

    await clock.step(
        'The accordion opens and the counter ends on its number',
        async () => {
            const question = page.getByText('Do you bake gluten free bread?');
            await question.click();
            await expect(
                page.locator('details.verso-disclosure').first(),
            ).toHaveAttribute('open', '');
            await page.locator('.verso-counter').scrollIntoViewIfNeeded();
            await expect(page.locator('.verso-counter__number')).toHaveText(
                /1,200|1200/,
            );
        },
    );

    await clock.step(
        'The header sticks and gets solid while scrolling',
        async () => {
            await page.mouse.wheel(0, 1200);
            await expect(page.locator('header.verso-header')).toHaveClass(
                /is-scrolled/,
            );
            const top = await page
                .locator('header.verso-header')
                .evaluate((header) => header.getBoundingClientRect().top);
            expect(top).toBeLessThanOrEqual(1);
            await page.mouse.wheel(0, -2400);
        },
    );

    await clock.step('The page is accessible at this width', async () => {
        await expectAccessible(
            page,
            `Landing page at ${page.viewportSize()?.width}px`,
        );
        info.annotations.push({
            type: 'width',
            description: wide ? 'wide' : 'narrow',
        });
    });

    await clock.report();
});
