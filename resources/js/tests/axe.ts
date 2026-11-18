import axe from 'axe-core';

// WCAG 2.2 A and AA rules. jsdom draws no pixels, so color contrast is checked in a real browser and in
// lib/easy/contrast.ts, and "region" needs the whole admin layout around a component.
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];
const OFF_IN_JSDOM = ['color-contrast', 'region'];

// Parts a modal dialog hides on purpose: Radix focus guards and the page behind the dialog (aria-hidden).
const HIDDEN_BY_DIALOGS = [
    ['[data-radix-focus-guard]'],
    ['[data-aria-hidden="true"]'],
];

// The axe violations of the document, as "rule: selectors" lines, so a failing test says what to fix.
// Frames are not followed: the page editor frame is checked on its own.
export async function axeViolations(): Promise<string[]> {
    const results = await axe.run(
        { exclude: HIDDEN_BY_DIALOGS },
        {
            iframes: false,
            runOnly: { type: 'tag', values: TAGS },
            rules: Object.fromEntries(
                OFF_IN_JSDOM.map((rule) => [rule, { enabled: false }]),
            ),
        },
    );

    return results.violations.map(
        (violation) =>
            `${violation.id}: ${violation.nodes
                .map((node) => node.target.join(' '))
                .join(', ')}`,
    );
}
