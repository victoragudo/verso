import { expect, test } from '@playwright/test';
import type { Page } from '@playwright/test';
import { StepClock, expectAccessible, expectNoSidewaysScroll } from './support/checks';
import { installVerso } from './support/install';

// From a fresh install to a finished small business site, using only Easy mode.
// Every project (computer and phone) runs it against its own throwaway install.

const owner = {
    name: 'Marta Silva',
    email: 'marta@harborlanebakery.pt',
    password: 'Sourdough2026',
};

const site = {
    name: 'Harbor Lane Bakery',
    email: 'hello@harborlanebakery.pt',
    phone: '+351 222 018 447',
    address: 'Rua da Ribeira 12, 4050-513 Porto',
    instagram: 'https://instagram.com/harborlanebakery',
    description: 'A small bakery by the harbour in Porto with sourdough, cinnamon buns and good coffee every day.',
};

const heroHeading = 'Baked at five, gone by noon';
const newHeroHeading = 'Sourdough at sunrise on Harbor Lane';
const counterPicture = 'The bakery counter with cakes under glass domes.';

// Opens the admin dashboard from the admin bar of a public page.
async function dashboardFromAdminBar(page: Page): Promise<void> {
    await page.getByRole('navigation', { name: 'Site administration' }).getByRole('link', { name: 'Dashboard' }).click();
    await expect(page.getByRole('heading', { name: 'Launch checklist' })).toBeVisible();
}

// Opens an admin menu link, through the navigation sheet on small screens.
async function adminMenuLink(page: Page, isMobile: boolean, name: string): Promise<void> {
    if (isMobile) {
        await page.getByRole('button', { name: 'Open navigation' }).click();
    }

    await page.getByRole('navigation', { name: 'Admin' }).getByRole('link', { name, exact: true }).click();
}

// Saves the page editor. Phones keep "Save changes" in the "More actions" menu.
async function savePage(page: Page, isMobile: boolean): Promise<void> {
    if (isMobile) {
        await page.getByRole('button', { name: 'More actions' }).click();
        await page.getByRole('menuitem', { name: 'Save changes' }).click();
    } else {
        await page.getByRole('button', { name: 'Save changes' }).click();
    }

    await expect(page.getByText('All changes saved', { exact: true }).locator('visible=true')).toBeVisible();
}

test('a bakery owner builds and launches a site with Easy mode only', async ({ page, isMobile }, info) => {
    const clock = new StepClock(info);
    const checklist = page.getByRole('list', { name: 'Steps' });

    await clock.step('Install Verso with the web wizard', async () => {
        await installVerso(page, { siteName: site.name, name: owner.name, email: owner.email, password: owner.password });
    });

    await clock.step('Guided start: build a restaurant or cafe site', async () => {
        await expect(page.getByRole('heading', { name: 'What is your site for?' })).toBeVisible();
        await expectNoSidewaysScroll(page);
        await expectAccessible(page, 'Guided start');
        await page.getByRole('radio', { name: 'Restaurant or cafe' }).check();
        await page.getByRole('button', { name: 'Build my site' }).click();
        await expect(page.getByRole('heading', { name: 'Make it yours', level: 1 })).toBeVisible({ timeout: 60_000 });
    });

    await clock.step('Make it yours: logo, palette, fonts and style', async () => {
        await expectNoSidewaysScroll(page);
        await expectAccessible(page, 'Make it yours');
        const preview = page.getByTitle(/^Preview of your home page/);
        const firstPreview = await preview.getAttribute('src');

        const chooser = page.waitForEvent('filechooser');
        await page.getByRole('button', { name: 'Add your logo' }).click();
        await (await chooser).setFiles('tests/e2e/fixtures/harbor-lane-logo.png');
        await expect(page.getByRole('button', { name: 'Change your logo' })).toBeVisible();
        await expect(page.getByRole('radio', { name: 'From your logo' })).toBeVisible();

        await page.getByRole('radio', { name: 'Soft' }).check();
        await page.getByRole('radio', { name: 'Studio' }).check();
        await page.getByRole('radio', { name: 'Elegant' }).check();
        await expect(page.getByRole('radio', { name: 'Studio' })).toBeChecked();
        await expect(page.getByText('All colors are easy to read.')).toBeVisible();

        await expect(preview).not.toHaveAttribute('src', firstPreview ?? '');
        const accent = await page.getByRole('textbox', { name: 'Accent', exact: true }).inputValue();
        const frame = page.frameLocator('[data-test="style-preview"]');
        await expect
            .poll(() => frame.locator('html').evaluate((html) => getComputedStyle(html).getPropertyValue('--verso-color-accent')))
            .toContain(accent);
        await expect(frame.getByRole('img', { name: site.name })).toBeVisible();

        await page.getByRole('button', { name: 'Save my look' }).click();
    });

    await clock.step('Dashboard shows the launch checklist', async () => {
        await expect(page.getByRole('heading', { name: 'Launch checklist' })).toBeVisible();
        await expect(checklist.getByText('Add your logo (done)')).toBeVisible();
        await expect(checklist.getByText('Choose your colors (done)')).toBeVisible();
        await expectNoSidewaysScroll(page);
        await expectAccessible(page, 'Dashboard');
    });

    await clock.step('Open the visual page editor from the admin bar', async () => {
        await page.goto('/');
        await page.getByRole('navigation', { name: 'Site administration' }).getByRole('link', { name: 'Edit page' }).click();
        await expect(page.getByRole('heading', { name: 'Home', level: 1 })).toBeVisible();
        await expect(page.frameLocator('main iframe').getByRole('heading', { name: heroHeading })).toBeVisible();
        await expectNoSidewaysScroll(page);
        await expectAccessible(page, 'Page editor');
    });

    const editor = page.frameLocator('main iframe');

    await clock.step('Edit the hero heading in place', async () => {
        await editor.getByRole('heading', { name: heroHeading }).click();
        await page.keyboard.press('ControlOrMeta+A');
        await page.keyboard.type(newHeroHeading);
        await page.keyboard.press('Enter');
        await expect(editor.getByRole('region', { name: newHeroHeading })).toBeVisible();
    });

    await clock.step('Replace a picture from the media library', async () => {
        const about = editor.getByRole('region', { name: 'A small bakery with a big oven' });
        await about.getByRole('button', { name: 'Replace image' }).click();
        const library = page.getByRole('dialog', { name: 'Media library' });
        await library.getByRole('button', { name: counterPicture }).click();
        await library.getByRole('button', { name: 'Select' }).click();
        await expect(library).toBeHidden();
        await expect(about.getByRole('img', { name: counterPicture })).toBeVisible();
    });

    const faqTools = editor.getByRole('toolbar', { name: /^Tools for Section \d+: Questions and answers$/ });

    await clock.step('Add a questions section from the gallery and move it up', async () => {
        await editor.getByRole('button', { name: 'Add a section after section 4' }).click();
        const gallery = page.getByRole('dialog', { name: 'Add a section' });
        await expect(gallery).toBeVisible();
        await expectAccessible(page, 'Section gallery');
        await gallery.getByRole('button', { name: /^Questions and answers/ }).click();
        await expect(gallery).toBeHidden();
        await expect(faqTools).toHaveAccessibleName('Tools for Section 5: Questions and answers');
        await faqTools.getByRole('button', { name: 'Move up' }).click();
        await expect(faqTools).toHaveAccessibleName('Tools for Section 4: Questions and answers');
    });

    await clock.step('Hide the gallery, undo a mistake and save', async () => {
        const counter = editor.getByRole('region', { name: 'From our counter' });
        await counter.click({ position: { x: 8, y: 8 } });
        await counter.getByRole('toolbar').getByRole('button', { name: 'Hide' }).click();
        await expect(editor.getByRole('region', { name: 'From our counter' }).getByText('Hidden on your site')).toBeVisible();

        await editor.getByRole('region', { name: 'Questions and answers' }).click({ position: { x: 8, y: 8 } });
        await faqTools.getByRole('button', { name: 'Duplicate' }).click();
        await expect(editor.getByRole('heading', { name: 'Questions and answers' })).toHaveCount(2);
        await page.getByRole('button', { name: 'Undo' }).click();
        await expect(editor.getByRole('heading', { name: 'Questions and answers' })).toHaveCount(1);

        await savePage(page, isMobile);
    });

    await clock.step('Check the public home page', async () => {
        await page.getByRole('link', { name: 'Back to site' }).click();
        const content = page.getByRole('main');
        await expect(content.getByRole('region', { name: newHeroHeading })).toBeVisible();
        await expect(content.getByRole('heading', { name: 'Questions and answers' })).toBeVisible();
        await expect(content.getByRole('img', { name: counterPicture })).toBeVisible();
        await expect(content.getByRole('heading', { name: 'From our counter' })).toHaveCount(0);
        await expect(page.getByRole('banner').getByRole('img', { name: site.name })).toBeVisible();
        await expectNoSidewaysScroll(page);
    });

    await clock.step('Fill in your site details', async () => {
        await dashboardFromAdminBar(page);
        await checklist.getByRole('link', { name: 'Do it: Add your contact details' }).click();
        await expect(page.getByRole('heading', { name: 'Your site details', level: 1 })).toBeVisible();
        await expectNoSidewaysScroll(page);
        await expectAccessible(page, 'Your site details');
        await page.getByRole('textbox', { name: 'Email address' }).fill(site.email);
        await page.getByRole('textbox', { name: 'Phone number' }).fill(site.phone);
        await page.getByRole('textbox', { name: 'Address', exact: true }).fill(site.address);
        await page.getByRole('textbox', { name: 'Profile addresses' }).fill(site.instagram);
        await page.getByRole('textbox', { name: 'Short description' }).fill(site.description);
        await page.getByRole('button', { name: 'Save details' }).click();
        await expect(page.getByText('Your site details are saved.')).toBeVisible();
    });

    await clock.step('Create the privacy page from the checklist', async () => {
        await adminMenuLink(page, isMobile, 'Dashboard');
        await checklist.getByRole('button', { name: 'Create a privacy page' }).click();
        await expect(page.getByText('Your privacy page is ready.', { exact: false })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Privacy policy', level: 1 })).toBeVisible();
        await expect(editor.getByText(`To do that, write to ${site.email}.`)).toBeVisible();

        await page.getByRole('link', { name: 'Back to site' }).click();
        await expect(page.getByRole('contentinfo').getByRole('link', { name: 'Privacy policy' })).toBeVisible();
        await dashboardFromAdminBar(page);
        await expect(checklist.getByText('Add a privacy page (done)')).toBeVisible();
    });

    await clock.step('Go live and finish the checklist', async () => {
        await checklist.getByRole('button', { name: 'Go live' }).click();
        await expect(checklist.getByText('Go live (done)')).toBeVisible();
        await expect(page.getByRole('progressbar', { name: 'All done. Your site is ready.' })).toBeVisible();
        await expect(checklist.getByText('(not done yet)')).toHaveCount(0);
    });

    await clock.report();
});
