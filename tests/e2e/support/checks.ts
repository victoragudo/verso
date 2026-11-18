import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { expect, test } from '@playwright/test';
import type { Page, TestInfo } from '@playwright/test';

// Shared checks for the end to end journeys: no sideways scroll, axe-core and step timings.

const require = createRequire(import.meta.url);
const axeSource = readFileSync(require.resolve('axe-core/axe.min.js'), 'utf8');

// WCAG 2.2 AA, the target of every Easy mode screen.
const AXE_TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

type AxeViolation = { id: string; impact: string | null; help: string; nodes: { target: string[] }[] };

// The page is never wider than the screen, so nothing needs sideways scrolling.
export async function expectNoSidewaysScroll(page: Page): Promise<void> {
    await expect
        .poll(() => page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth))
        .toBeLessThanOrEqual(0);
}

// Runs axe-core on the page (frames are left out) and fails with a readable list of problems.
export async function expectAccessible(page: Page, screen: string): Promise<void> {
    // Fading dialogs would be measured half transparent, so wait until every animation is done.
    await expect
        .poll(() => page.evaluate(() => document.getAnimations().filter((animation) => animation.playState === 'running').length))
        .toBe(0);
    await page.evaluate(axeSource);

    const violations = await page.evaluate(async (tags) => {
        const axe = (window as unknown as { axe: { run: (context: unknown, options: unknown) => Promise<{ violations: AxeViolation[] }> } }).axe;
        const result = await axe.run(
            { exclude: [['iframe']] },
            { runOnly: { type: 'tag', values: tags }, resultTypes: ['violations'] },
        );

        return result.violations.map((item) => ({
            id: item.id,
            impact: item.impact,
            help: item.help,
            nodes: item.nodes.slice(0, 3).map((node) => ({ target: node.target })),
        }));
    }, AXE_TAGS);

    expect(violations, `${screen}: ${JSON.stringify(violations, null, 2)}`).toEqual([]);
}

// Times each step of a journey and attaches the list to the report.
export class StepClock {
    private readonly timings: { step: string; seconds: number }[] = [];

    constructor(private readonly info: TestInfo) {}

    async step(name: string, body: () => Promise<void>): Promise<void> {
        const started = performance.now();

        await test.step(name, body);
        this.timings.push({ step: name, seconds: Math.round((performance.now() - started) / 100) / 10 });
    }

    async report(): Promise<void> {
        const total = this.timings.reduce((sum, item) => sum + item.seconds, 0);
        const lines = [...this.timings.map((item) => `${item.seconds.toFixed(1)}s  ${item.step}`), `${total.toFixed(1)}s  total`];

        console.log(`[${this.info.project.name}] journey timings\n${lines.join('\n')}`);
        await this.info.attach('timings', { body: lines.join('\n'), contentType: 'text/plain' });
    }
}
