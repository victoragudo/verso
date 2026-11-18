import { act, fireEvent, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetTitle } from '@/components/ui/sheet';
import { promoteToTopLayer } from '@/components/ui/top-layer';

// jsdom has neither showModal() nor the Popover API, so tests add small stand-ins.
// jsdom hides every [popover] element, so popover queries use { hidden: true }.
function installNativeDialog() {
    const showModal = vi.fn(function (this: HTMLDialogElement) {
        this.setAttribute('open', '');
    });
    const close = vi.fn(function (this: HTMLDialogElement) {
        this.removeAttribute('open');
        this.dispatchEvent(new Event('close'));
    });
    Object.defineProperty(HTMLDialogElement.prototype, 'showModal', {
        value: showModal,
        configurable: true,
    });
    Object.defineProperty(HTMLDialogElement.prototype, 'close', {
        value: close,
        configurable: true,
    });

    return { showModal, close };
}

function installPopoverApi() {
    const showPopover = vi.fn();
    Object.defineProperty(HTMLElement.prototype, 'popover', {
        get() {
            return this.getAttribute('popover');
        },
        configurable: true,
    });
    Object.defineProperty(HTMLElement.prototype, 'showPopover', {
        value: showPopover,
        configurable: true,
    });

    return { showPopover };
}

function removeStandIns() {
    for (const name of ['showModal', 'close'] as const) {
        delete (
            HTMLDialogElement.prototype as unknown as Record<string, unknown>
        )[name];
    }

    for (const name of ['popover', 'showPopover'] as const) {
        delete (HTMLElement.prototype as unknown as Record<string, unknown>)[
            name
        ];
    }
}

function DeletePostDialog({
    onOpenChange,
    onCloseAutoFocus,
}: {
    onOpenChange?: (open: boolean) => void;
    onCloseAutoFocus?: (event: Event) => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                onOpenChange?.(next);
            }}
        >
            <DialogTrigger>Delete “Autumn in Kyoto”</DialogTrigger>
            <DialogContent onCloseAutoFocus={onCloseAutoFocus}>
                <DialogTitle>Delete this post?</DialogTitle>
                <DialogDescription>
                    “Autumn in Kyoto” moves to the trash.
                </DialogDescription>
                <button type="button">Move to trash</button>
            </DialogContent>
        </Dialog>
    );
}

describe('Dialog on the native <dialog> element', () => {
    afterEach(removeStandIns);

    it('opens with showModal() and is named by its title and description', async () => {
        const user = userEvent.setup();
        const { showModal } = installNativeDialog();
        render(<DeletePostDialog />);

        await user.click(
            screen.getByRole('button', { name: 'Delete “Autumn in Kyoto”' }),
        );

        const dialog = screen.getByRole('dialog', {
            name: 'Delete this post?',
        });
        expect(dialog.tagName).toBe('DIALOG');
        expect(showModal).toHaveBeenCalledTimes(1);
        expect(dialog).toHaveAttribute('closedby', 'any');
        expect(dialog).toHaveAccessibleDescription(
            '“Autumn in Kyoto” moves to the trash.',
        );
        expect(dialog).not.toHaveAttribute('role');
        expect(dialog).not.toHaveAttribute('aria-modal');
        expect(
            screen.getByRole('button', { name: 'Move to trash' }),
        ).toHaveFocus();
    });

    it('closes on a cancel request (Escape) and gives focus back to the trigger', async () => {
        const user = userEvent.setup();
        const { close } = installNativeDialog();
        const onOpenChange = vi.fn();
        render(<DeletePostDialog onOpenChange={onOpenChange} />);
        const trigger = screen.getByRole('button', {
            name: 'Delete “Autumn in Kyoto”',
        });

        await user.click(trigger);
        const cancel = new Event('cancel', { cancelable: true });
        act(() => {
            screen.getByRole('dialog').dispatchEvent(cancel);
        });

        expect(cancel.defaultPrevented).toBe(true);
        expect(onOpenChange).toHaveBeenLastCalledWith(false);
        expect(close).toHaveBeenCalledTimes(1);
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
        expect(trigger).toHaveFocus();
    });

    it('keeps React in sync when the browser closes the dialog by itself', async () => {
        const user = userEvent.setup();
        installNativeDialog();
        const onOpenChange = vi.fn();
        render(<DeletePostDialog onOpenChange={onOpenChange} />);

        await user.click(
            screen.getByRole('button', { name: 'Delete “Autumn in Kyoto”' }),
        );
        const dialog = screen.getByRole('dialog');
        act(() => {
            dialog.removeAttribute('open');
            dialog.dispatchEvent(new Event('close'));
        });

        expect(onOpenChange).toHaveBeenLastCalledWith(false);
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('closes on a press and release on the backdrop when closedby is missing', async () => {
        const user = userEvent.setup();
        installNativeDialog();
        const onOpenChange = vi.fn();
        render(<DeletePostDialog onOpenChange={onOpenChange} />);

        await user.click(
            screen.getByRole('button', { name: 'Delete “Autumn in Kyoto”' }),
        );
        const dialog = screen.getByRole('dialog');
        dialog.getBoundingClientRect = () =>
            ({ left: 100, right: 500, top: 100, bottom: 400 }) as DOMRect;

        fireEvent.pointerDown(dialog, { clientX: 20, clientY: 20 });
        fireEvent.pointerUp(dialog, { clientX: 20, clientY: 20 });

        expect(onOpenChange).toHaveBeenLastCalledWith(false);
    });

    it('stays open when the press is inside the dialog box', async () => {
        const user = userEvent.setup();
        installNativeDialog();
        const onOpenChange = vi.fn();
        render(<DeletePostDialog onOpenChange={onOpenChange} />);

        await user.click(
            screen.getByRole('button', { name: 'Delete “Autumn in Kyoto”' }),
        );
        const dialog = screen.getByRole('dialog');
        dialog.getBoundingClientRect = () =>
            ({ left: 100, right: 500, top: 100, bottom: 400 }) as DOMRect;

        fireEvent.pointerDown(dialog, { clientX: 120, clientY: 120 });
        fireEvent.pointerUp(dialog, { clientX: 120, clientY: 120 });

        expect(onOpenChange).not.toHaveBeenCalledWith(false);
        expect(screen.getByRole('dialog')).toBeInTheDocument();
    });

    it('lets onCloseAutoFocus move focus somewhere else', async () => {
        const user = userEvent.setup();
        installNativeDialog();
        const search = document.createElement('input');
        document.body.append(search);
        render(
            <DeletePostDialog
                onCloseAutoFocus={(event) => {
                    event.preventDefault();
                    search.focus();
                }}
            />,
        );

        await user.click(
            screen.getByRole('button', { name: 'Delete “Autumn in Kyoto”' }),
        );
        await user.click(screen.getByRole('button', { name: 'Close' }));

        expect(search).toHaveFocus();
        search.remove();
    });

    it('works without showModal() and closes on Escape', async () => {
        const user = userEvent.setup();
        render(<DeletePostDialog />);

        await user.click(
            screen.getByRole('button', { name: 'Delete “Autumn in Kyoto”' }),
        );
        expect(screen.getByRole('dialog')).toHaveAttribute('open');

        await user.keyboard('{Escape}');

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });
});

describe('Sheet', () => {
    afterEach(removeStandIns);

    it('is a native dialog placed on one side', () => {
        installNativeDialog();
        render(
            <Sheet open>
                <SheetContent side="left">
                    <SheetTitle>Menu</SheetTitle>
                </SheetContent>
            </Sheet>,
        );

        const sheet = screen.getByRole('dialog', { name: 'Menu' });
        expect(sheet.tagName).toBe('DIALOG');
        expect(sheet).toHaveAttribute('data-side', 'left');
        expect(sheet).toHaveAttribute('data-slot', 'sheet-content');
    });
});

function TagPicker({
    onOpenChange,
}: {
    onOpenChange?: (open: boolean) => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <Popover
            open={open}
            onOpenChange={(next) => {
                setOpen(next);
                onOpenChange?.(next);
            }}
        >
            <PopoverTrigger>Tags</PopoverTrigger>
            <PopoverContent aria-label="Pick tags">
                <button type="button">Travel</button>
            </PopoverContent>
        </Popover>
    );
}

describe('Popover', () => {
    afterEach(removeStandIns);

    it('uses a native auto popover when the browser has the API', async () => {
        const user = userEvent.setup();
        const { showPopover } = installPopoverApi();
        render(<TagPicker />);

        await user.click(screen.getByRole('button', { name: 'Tags' }));

        const panel = screen.getByRole('dialog', { hidden: true });
        expect(panel).toHaveAttribute('popover', 'auto');
        expect(showPopover).toHaveBeenCalled();
    });

    it('follows the browser when light dismiss closes it', async () => {
        const user = userEvent.setup();
        installPopoverApi();
        const onOpenChange = vi.fn();
        render(<TagPicker onOpenChange={onOpenChange} />);

        await user.click(screen.getByRole('button', { name: 'Tags' }));
        const toggle = Object.assign(new Event('toggle'), {
            newState: 'closed',
        });
        act(() => {
            screen.getByRole('dialog', { hidden: true }).dispatchEvent(toggle);
        });

        expect(onOpenChange).toHaveBeenLastCalledWith(false);
        expect(
            screen.queryByRole('dialog', { hidden: true }),
        ).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Tags' })).toHaveFocus();
    });

    it('does not open again when a click on the trigger closed it by light dismiss', () => {
        installPopoverApi();
        render(<TagPicker />);
        const trigger = screen.getByRole('button', { name: 'Tags' });

        fireEvent.click(trigger);
        expect(
            screen.getByRole('dialog', { hidden: true }),
        ).toBeInTheDocument();

        fireEvent.pointerDown(trigger);
        const toggle = Object.assign(new Event('toggle'), {
            newState: 'closed',
        });
        act(() => {
            screen.getByRole('dialog', { hidden: true }).dispatchEvent(toggle);
        });
        fireEvent.click(trigger);

        expect(
            screen.queryByRole('dialog', { hidden: true }),
        ).not.toBeInTheDocument();
        expect(trigger).toHaveAttribute('aria-expanded', 'false');
    });

    it('closes on an outside press without the Popover API', async () => {
        const user = userEvent.setup();
        render(
            <>
                <TagPicker />
                <p>Outside</p>
            </>,
        );

        await user.click(screen.getByRole('button', { name: 'Tags' }));
        expect(screen.getByRole('dialog')).not.toHaveAttribute('popover');

        await user.click(screen.getByText('Outside'));

        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('lives inside an open dialog, so the dialog does not make it inert', async () => {
        const user = userEvent.setup();
        installNativeDialog();
        render(
            <Dialog open>
                <DialogContent>
                    <DialogTitle>Edit post</DialogTitle>
                    <TagPicker />
                </DialogContent>
            </Dialog>,
        );

        await user.click(screen.getByRole('button', { name: 'Tags' }));

        const dialog = screen.getByRole('dialog', { name: 'Edit post' });
        expect(dialog).toContainElement(
            screen.getByRole('dialog', { name: 'Pick tags' }),
        );
    });

    it('Escape inside the popover closes only the popover', async () => {
        const user = userEvent.setup();
        installNativeDialog();
        render(
            <Dialog open>
                <DialogContent>
                    <DialogTitle>Edit post</DialogTitle>
                    <TagPicker />
                </DialogContent>
            </Dialog>,
        );

        await user.click(screen.getByRole('button', { name: 'Tags' }));
        await user.keyboard('{Escape}');

        expect(
            screen.queryByRole('dialog', { name: 'Pick tags' }),
        ).not.toBeInTheDocument();
        expect(
            screen.getByRole('dialog', { name: 'Edit post' }),
        ).toBeInTheDocument();
    });
});

describe('promoteToTopLayer', () => {
    let wrapper: HTMLDivElement;
    let content: HTMLDivElement;

    beforeEach(() => {
        wrapper = document.createElement('div');
        wrapper.setAttribute('data-radix-popper-content-wrapper', '');
        content = document.createElement('div');
        wrapper.append(content);
        document.body.append(wrapper);
    });

    afterEach(() => {
        wrapper.remove();
        removeStandIns();
    });

    it('shows the Radix position wrapper as a manual popover', () => {
        const { showPopover } = installPopoverApi();

        promoteToTopLayer(content);

        expect(wrapper).toHaveAttribute('popover', 'manual');
        expect(showPopover).toHaveBeenCalledTimes(1);
    });

    it('changes nothing without the Popover API', () => {
        promoteToTopLayer(content);

        expect(wrapper).not.toHaveAttribute('popover');
    });

    it('leaves other parents alone', () => {
        installPopoverApi();
        const plain = document.createElement('section');
        const child = document.createElement('div');
        plain.append(child);

        promoteToTopLayer(child);

        expect(plain).not.toHaveAttribute('popover');
    });
});

describe('Popover placement without anchor positioning', () => {
    function mockBoxes(trigger: Partial<DOMRect>, panel: Partial<DOMRect>) {
        vi.spyOn(
            HTMLElement.prototype,
            'getBoundingClientRect',
        ).mockImplementation(function (this: HTMLElement) {
            const box =
                this.dataset.slot === 'popover-content' ? panel : trigger;

            return {
                x: 0,
                y: 0,
                top: 0,
                left: 0,
                right: 0,
                bottom: 0,
                width: 0,
                height: 0,
                ...box,
                toJSON: () => box,
            } as DOMRect;
        });
        vi.spyOn(
            document.documentElement,
            'clientWidth',
            'get',
        ).mockReturnValue(1000);
    }

    afterEach(() => {
        document.documentElement.style.direction = '';
    });

    it('lines up the start edges, which is the right edge in RTL', async () => {
        const user = userEvent.setup();
        document.documentElement.style.direction = 'rtl';
        mockBoxes(
            {
                top: 100,
                bottom: 130,
                left: 600,
                right: 700,
                width: 100,
                height: 30,
            },
            { width: 300, height: 200 },
        );
        render(<TagPicker />);

        await user.click(screen.getByRole('button', { name: 'Tags' }));

        const panel = screen.getByRole('dialog', { name: 'Pick tags' });
        expect(panel.style.left).toBe('400px');
        expect(panel.style.top).toBe('136px');
    });

    it('moves to the other edge when the panel would leave the screen', async () => {
        const user = userEvent.setup();
        mockBoxes(
            {
                top: 100,
                bottom: 130,
                left: 850,
                right: 950,
                width: 100,
                height: 30,
            },
            { width: 300, height: 200 },
        );
        render(<TagPicker />);

        await user.click(screen.getByRole('button', { name: 'Tags' }));

        expect(
            screen.getByRole('dialog', { name: 'Pick tags' }).style.left,
        ).toBe('650px');
    });
});

describe('Radix menus inside a dialog', () => {
    afterEach(removeStandIns);

    it('portal into the dialog, so the modal does not make them inert', async () => {
        const user = userEvent.setup();
        installNativeDialog();
        render(
            <Dialog open>
                <DialogContent>
                    <DialogTitle>Edit menu</DialogTitle>
                    <DropdownMenu>
                        <DropdownMenuTrigger>Link actions</DropdownMenuTrigger>
                        <DropdownMenuContent>
                            <DropdownMenuItem>Move up</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </DialogContent>
            </Dialog>,
        );

        screen.getByRole('button', { name: 'Link actions' }).focus();
        await user.keyboard('{Enter}');

        const dialog = screen.getByRole('dialog', { name: 'Edit menu' });
        expect(dialog).toContainElement(
            screen.getByRole('menuitem', { name: 'Move up' }),
        );
    });
});

describe('dialog close button', () => {
    afterEach(removeStandIns);

    it('is at least 24px and shows its ring only for keyboard focus', () => {
        installNativeDialog();
        render(
            <Dialog open>
                <DialogContent>
                    <DialogTitle>Rename menu</DialogTitle>
                </DialogContent>
            </Dialog>,
        );

        const close = screen.getByRole('button', { name: 'Close' });
        expect(close).toHaveClass('size-6');
        expect(close.className).toContain('focus-visible:ring-2');
        expect(close.className).not.toMatch(/(^|\s)focus:ring/);
    });
});
