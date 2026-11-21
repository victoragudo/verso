import { describe, expect, it } from 'vitest';
import {
    scan,
    scanSource,
} from '../../../../scripts/i18n/hardcoded-strings.mjs';

type Finding = { file?: string; line: number; text: string };

describe('hard coded strings scanner', () => {
    it('finds text outside t()', () => {
        const found: Finding[] = scanSource(
            'example.tsx',
            `export function Card() {
                return (
                    <section aria-label="Recent orders" title={t('Orders')}>
                        <h2>Recent orders</h2>
                        <p>{t('No orders yet.')}</p>
                        <Input placeholder={ready ? 'Search orders' : t('Loading')} />
                        <Badge label={msg('Paid')} />
                        {count > 0 ? 'Some left' : null}
                    </section>
                );
            }
            toast.success('Order saved.');
            const OPTIONS = [{ value: 'paid', label: 'Paid' }, { value: 'due', label: msg('Due') }];`,
        );

        expect(found.map((item) => item.text)).toEqual([
            'Recent orders',
            'Recent orders',
            'Search orders',
            'Some left',
            'Order saved.',
            'Paid',
        ]);
    });

    it('ignores code, class names, keys and brand names', () => {
        const found: Finding[] = scanSource(
            'example.tsx',
            `<div className="flex gap-2" data-test="save-button" id="site-name">
                <Input name="email" type="email" autoComplete="username" />
                <span>{' '}</span>
                <a href="/admin/media">Verso</a>
                <code>image/jpeg</code>
                <kbd>⌘K</kbd>
            </div>`,
            new Set(['Verso']),
        );

        expect(found).toEqual([]);
    });
});

// The scan reads every admin file, so it needs more than the default 5 seconds on a busy CI runner.
const FULL_SCAN_TIMEOUT_MS = 30_000;

describe('admin TSX', () => {
    it(
        'has no English text outside t() and msg()',
        () => {
            const found: Finding[] = scan();
            const lines = found.map(
                (item) => `${item.file}:${item.line}  ${item.text}`,
            );

            expect(lines).toEqual([]);
        },
        FULL_SCAN_TIMEOUT_MS,
    );
});
