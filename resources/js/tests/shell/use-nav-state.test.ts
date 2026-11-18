import { act, renderHook } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import {
    navStorageKey,
    readNavState,
    useNavState,
} from '@/components/verso/shell/use-nav-state';

beforeEach(() => window.localStorage.clear());

describe('readNavState', () => {
    it('gives an empty state when nothing is saved or the data is broken', () => {
        expect(readNavState('verso.nav.3')).toEqual({
            closedGroups: [],
            openItems: [],
        });

        window.localStorage.setItem('verso.nav.3', '{not json');
        expect(readNavState('verso.nav.3')).toEqual({
            closedGroups: [],
            openItems: [],
        });

        window.localStorage.setItem(
            'verso.nav.3',
            JSON.stringify({ closedGroups: ['tools', 4], openItems: 'shop' }),
        );
        expect(readNavState('verso.nav.3')).toEqual({
            closedGroups: ['tools'],
            openItems: [],
        });
    });
});

describe('useNavState', () => {
    it('keeps one saved state per user', () => {
        const first = renderHook(() => useNavState(navStorageKey(1), null, []));
        act(() => first.result.current.setGroupOpen('settings', false));

        const second = renderHook(() =>
            useNavState(navStorageKey(2), null, []),
        );

        expect(first.result.current.isGroupOpen('settings')).toBe(false);
        expect(second.result.current.isGroupOpen('settings')).toBe(true);
    });

    it('opens the group and parents of the current page', () => {
        window.localStorage.setItem(
            navStorageKey(5),
            JSON.stringify({ closedGroups: ['tools'], openItems: [] }),
        );

        const { result } = renderHook(() =>
            useNavState(navStorageKey(5), 'tools', ['plugin.forms']),
        );

        expect(result.current.isGroupOpen('tools')).toBe(true);
        expect(result.current.isItemOpen('plugin.forms')).toBe(true);
    });

    it('opens a path on request', () => {
        const { result } = renderHook(() =>
            useNavState(navStorageKey(6), null, []),
        );

        act(() => result.current.setGroupOpen('administration', false));
        act(() => result.current.openPath('administration', ['system']));

        expect(result.current.isGroupOpen('administration')).toBe(true);
        expect(result.current.isItemOpen('system')).toBe(true);
    });
});
