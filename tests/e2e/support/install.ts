import { expect } from '@playwright/test';
import type { Page } from '@playwright/test';
import { expectNoSidewaysScroll } from './checks';

// The site and the first admin of a fresh install.
export type InstallDetails = {
    siteName: string;
    name: string;
    email: string;
    password: string;
};

// Runs the web install wizard on a fresh server (SQLite, no demo content) and opens the admin.
export async function installVerso(
    page: Page,
    details: InstallDetails,
): Promise<void> {
    await page.goto('/');
    await expect(
        page.getByRole('heading', { name: 'Welcome to Verso' }),
    ).toBeVisible();
    await expectNoSidewaysScroll(page);
    await page.getByRole('button', { name: 'Start' }).click();

    await expect(
        page.getByRole('heading', { name: 'Server check' }),
    ).toBeVisible();
    await expectNoSidewaysScroll(page);
    await page.getByRole('button', { name: 'Continue', exact: true }).click();

    await expect(page.getByRole('radio', { name: /^SQLite/ })).toBeChecked();
    await page.getByRole('button', { name: 'Save and continue' }).click();

    await expect(
        page.getByRole('heading', { name: 'Site and admin account' }),
    ).toBeVisible();
    await page
        .getByRole('textbox', { name: 'Site name' })
        .fill(details.siteName);
    await page.getByRole('textbox', { name: 'Your name' }).fill(details.name);
    await page.getByRole('textbox', { name: 'Email' }).fill(details.email);
    await page
        .getByRole('textbox', { name: 'Password', exact: true })
        .fill(details.password);
    await page
        .getByRole('textbox', { name: 'Confirm password' })
        .fill(details.password);
    await page.getByRole('button', { name: 'Continue', exact: true }).click();

    await expect(page.getByRole('heading', { name: 'Services' })).toBeVisible();
    await page.getByRole('button', { name: 'Skip, use defaults' }).click();

    await expect(page.getByRole('heading', { name: 'Content' })).toBeVisible();
    await expect(
        page.getByRole('radio', { name: /^Start empty/ }),
    ).toBeChecked();
    await page.getByRole('button', { name: 'Continue', exact: true }).click();

    await expect(page.getByRole('heading', { name: 'Review' })).toBeVisible();
    await page.getByRole('button', { name: 'Install Verso' }).click();
    await expect(
        page.getByRole('heading', { name: 'Verso is installed' }),
    ).toBeVisible({ timeout: 120_000 });
    await page.getByRole('link', { name: 'Go to your dashboard' }).click();
}
