import { expect, test } from '@playwright/test';
import { installVerso } from './support/install';

// Runs against a project made with "composer create-project" and served by "php artisan serve".
// Start it with: node scripts/e2e/fresh-install.mjs

const owner = {
    siteName: 'Quinta do Vale Pottery',
    name: 'Inês Carvalho',
    email: 'ines@quintadovale.pt',
    password: 'Stoneware2026',
};

test.skip(!process.env.E2E_FRESH_URL, 'Run it with node scripts/e2e/fresh-install.mjs');

test('a new user installs Verso with the documented commands and logs in', async ({ page, browser, baseURL }) => {
    await installVerso(page, owner);
    await expect(page).toHaveURL(/\/admin\//);
    await expect(page.getByRole('navigation', { name: 'Admin' })).toBeVisible();

    const fresh = await browser.newContext({ baseURL });
    const login = await fresh.newPage();
    await login.goto('/login');
    await login.getByLabel('Email address').fill(owner.email);
    await login.getByLabel('Password', { exact: true }).fill(owner.password);
    await login.getByRole('button', { name: 'Log in' }).click();
    await expect(login).toHaveURL(/\/admin/);
    await expect(login.getByRole('navigation', { name: 'Admin' })).toBeVisible();
    await expect(login.getByRole('link', { name: `${owner.siteName} Site admin` })).toBeVisible();
    await fresh.close();
});
