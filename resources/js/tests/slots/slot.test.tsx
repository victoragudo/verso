import { render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    Slot,
    clearSlotRegistry,
    registerSlotComponent,
} from '@/components/verso/slots';
import type {
    EntryRowContext,
    SlotComponentProps,
} from '@/components/verso/slots';
import { resetPageProps, setPageProps } from '@/tests/mocks/inertia';
import { rowContext, slotItem } from './fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

function Badge({ item, props, context }: SlotComponentProps<EntryRowContext>) {
    return (
        <span data-testid={item.key}>
            {context.row.title}: {String(props.minutes)} min
        </span>
    );
}

function Weather({ props }: SlotComponentProps) {
    return <p>Weather in {String(props.city)}</p>;
}

afterEach(() => {
    clearSlotRegistry();
    resetPageProps();
});

describe('Slot', () => {
    it('renders the shared items with their props and the context', () => {
        registerSlotComponent('reading-time.badge', Badge);
        setPageProps({
            versoSlots: {
                'entries-columns': [slotItem({ props: { minutes: 6 } })],
            },
        });

        render(<Slot name="entries-columns" context={rowContext()} />);

        expect(screen.getByTestId('reading-time')).toHaveTextContent(
            'Winter Menu at Harbor Lane: 6 min',
        );
    });

    it('keeps the order the server sent', () => {
        registerSlotComponent('weather.widget', Weather);
        setPageProps({
            versoSlots: {
                'dashboard-widgets': [
                    slotItem({
                        key: 'lisbon',
                        component: 'weather.widget',
                        order: 10,
                        props: { city: 'Lisbon' },
                    }),
                    slotItem({
                        key: 'porto',
                        component: 'weather.widget',
                        order: 20,
                        props: { city: 'Porto' },
                    }),
                ],
            },
        });

        render(<Slot name="dashboard-widgets" context={{}} />);

        expect(
            screen.getAllByText(/Weather in/).map((node) => node.textContent),
        ).toEqual(['Weather in Lisbon', 'Weather in Porto']);
    });

    it('renders nothing for keys no plugin registered', () => {
        setPageProps({
            versoSlots: {
                'dashboard-widgets': [
                    slotItem({ component: 'uninstalled.widget' }),
                ],
            },
        });

        const { container } = render(
            <Slot name="dashboard-widgets" context={{}} />,
        );

        expect(container).toBeEmptyDOMElement();
    });

    it('renders nothing when the server shares no slots', () => {
        registerSlotComponent('weather.widget', Weather);
        setPageProps({ versoSlots: [] });

        const { container } = render(
            <Slot name="dashboard-widgets" context={{}} />,
        );

        expect(container).toBeEmptyDOMElement();
    });
});
