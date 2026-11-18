import { afterEach, describe, expect, it } from 'vitest';
import {
    clearSlotRegistry,
    registerRowAction,
    slotRowActions,
} from '@/components/verso/slots';
import { rowContext, slotItem } from './fixtures';

afterEach(() => clearSlotRegistry());

describe('slotRowActions', () => {
    it('uses the registered builder with the row and the props', () => {
        registerRowAction('duplicate.action', (context, props) => ({
            label: `Duplicate “${context.row.title}”`,
            href: `/admin/duplicate/${context.row.id}?into=${String(props.into)}`,
            method: 'post',
        }));

        const actions = slotRowActions(
            [
                slotItem({
                    key: 'duplicate',
                    component: 'duplicate.action',
                    props: { into: 'draft' },
                }),
            ],
            rowContext(),
        );

        expect(actions).toEqual([
            {
                label: 'Duplicate “Winter Menu at Harbor Lane”',
                href: '/admin/duplicate/42?into=draft',
                method: 'post',
            },
        ]);
    });

    it('accepts a list or nothing from a builder', () => {
        registerRowAction('share.actions', () => [
            { label: 'Share on Mastodon', href: '/share/mastodon' },
            { label: 'Share by email', href: '/share/email' },
        ]);
        registerRowAction('hidden.action', () => null);

        const actions = slotRowActions(
            [
                slotItem({ key: 'share', component: 'share.actions' }),
                slotItem({ key: 'hidden', component: 'hidden.action' }),
            ],
            rowContext(),
        );

        expect(actions.map((action) => action.label)).toEqual([
            'Share on Mastodon',
            'Share by email',
        ]);
    });

    it('builds an action from PHP props when no builder is registered', () => {
        const actions = slotRowActions(
            [
                slotItem({
                    key: 'archive',
                    component: 'archive.link',
                    label: 'Send to archive',
                    props: {
                        href: '/admin/archive/{id}',
                        method: 'post',
                        destructive: true,
                    },
                }),
                slotItem({
                    key: 'broken',
                    component: 'broken.link',
                    label: 'Broken',
                    props: {},
                }),
            ],
            rowContext({ id: 7 }),
        );

        expect(actions).toEqual([
            {
                label: 'Send to archive',
                href: '/admin/archive/7',
                method: 'post',
                external: false,
                destructive: true,
            },
        ]);
    });
});
