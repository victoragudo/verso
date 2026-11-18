import {
    fireEvent,
    render,
    screen,
    waitFor,
    within,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import MakeItYours from '@/pages/admin/easy/style';
import { router, setPageProps } from '@/tests/mocks/inertia';
import {
    boldPreset,
    calmPreset,
    classicPair,
    currentColors,
    modernPair,
    roles,
} from './style-fixtures';

vi.mock(
    '@inertiajs/react',
    async () => (await import('@/tests/mocks/inertia')).inertiaMock,
);

const readImagePixels = vi.fn();
const uploadMedia = vi.fn();

vi.mock('@/lib/easy/image-pixels', () => ({
    readImagePixels: (...args: unknown[]) => readImagePixels(...args),
}));

vi.mock('@/components/verso/media/media-api', () => ({
    uploadMedia: (...args: unknown[]) => uploadMedia(...args),
}));

// A logo with a white background and an orange mark.
const logoPixels = new Uint8ClampedArray(
    [
        ...Array.from({ length: 60 }, () => [255, 255, 255, 255]),
        ...Array.from({ length: 40 }, () => [194, 65, 12, 255]),
    ].flat(),
);

const props = {
    theme: { name: 'Ashgrove', slug: 'ashgrove' },
    roles,
    colors: currentColors,
    presets: [calmPreset, boldPreset],
    fontPairs: [classicPair, modernPair],
    fontFaces: [],
    preset: 'calm',
    fontPair: 'classic',
    logo: null,
    logoSupported: true,
    canUpload: true,
    previewUrl: '/admin/site-editor/preview',
    previewWidths: { desktop: null, tablet: 768, phone: 390 },
    previewDelay: 0,
    palette: { sampleSize: 96, maxColors: 6 },
};

const frame = () =>
    screen.getByTitle(/Preview of your home page/) as HTMLIFrameElement;
const frameParams = () =>
    new URL(frame().src, 'http://harbor-lane.test').searchParams;

beforeEach(() => {
    setPageProps({ errors: {} });
    router.put.mockClear();
    readImagePixels.mockReset();
    uploadMedia.mockReset();
    URL.createObjectURL = vi.fn(() => 'blob:harbor-lane-logo');
    URL.revokeObjectURL = vi.fn();
});

describe('Make it yours page', () => {
    it('shows the picks of the site and previews them', () => {
        render(<MakeItYours {...props} />);

        expect(
            screen.getByRole('heading', { name: 'Make it yours' }),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('radio', { name: 'Your colors now' }),
        ).toBeChecked();
        expect(screen.getByRole('radio', { name: 'Calm' })).toBeChecked();
        expect(screen.getByRole('radio', { name: 'Classic' })).toBeChecked();
        expect(screen.getByRole('radio', { name: 'Light' })).not.toBeChecked();
        expect(
            screen.queryByRole('radio', { name: 'From your logo' }),
        ).toBeNull();
        expect(
            screen.getByText('All colors are easy to read.'),
        ).toBeInTheDocument();
        expect(frameParams().get('tokens[radius.base]')).toBe('6px');
        expect(frameParams().get('tokens[font.heading]')).toBe('georgia');
        expect(frameParams().get('tokens[color.background]')).toBe('#f6f7f3');
        expect(frameParams().has('logo')).toBe(false);
    });

    it('keeps the font pair the person picked when a style is picked after it', async () => {
        render(<MakeItYours {...props} />);

        await userEvent.click(
            screen.getByRole('radio', { name: 'Clean and modern' }),
        );
        await userEvent.click(screen.getByRole('radio', { name: 'Bold' }));
        await userEvent.click(screen.getByRole('radio', { name: 'Calm' }));

        expect(
            screen.getByRole('radio', { name: 'Clean and modern' }),
        ).toBeChecked();
        await waitFor(() =>
            expect(frameParams().get('tokens[font.body]')).toBe('system'),
        );
        expect(frameParams().get('tokens[radius.base]')).toBe('6px');
    });

    it('updates the preview when a preset, font pair or palette is picked', async () => {
        render(<MakeItYours {...props} />);

        await userEvent.click(screen.getByRole('radio', { name: 'Bold' }));

        expect(
            screen.getByRole('radio', { name: 'Clean and modern' }),
        ).toBeChecked();
        await waitFor(() =>
            expect(frameParams().get('tokens[radius.base]')).toBe('0px'),
        );
        expect(frameParams().get('tokens[font.body]')).toBe('system');

        await userEvent.click(screen.getByRole('radio', { name: 'Classic' }));
        await waitFor(() =>
            expect(frameParams().get('tokens[font.body]')).toBe('charter'),
        );

        await userEvent.click(screen.getByRole('radio', { name: 'Dark' }));
        await waitFor(() =>
            expect(frameParams().get('tokens[color.background]')).not.toBe(
                '#f6f7f3',
            ),
        );
        expect(
            screen.getByText('All colors are easy to read.'),
        ).toBeInTheDocument();
    });

    it('warns about hard to read text and fixes it in one click', async () => {
        render(<MakeItYours {...props} />);

        const text = screen.getByLabelText('Text');
        await userEvent.clear(text);
        await userEvent.type(text, '#c9c6bf');

        const warnings = document.querySelector<HTMLElement>(
            '[data-test="contrast-warnings"]',
        )!;
        expect(
            within(warnings).getByText(
                'The text is hard to read on this background. Try a darker text color.',
            ),
        ).toBeInTheDocument();
        expect(
            screen.getByRole('radio', { name: 'Your colors now' }),
        ).not.toBeChecked();
        await waitFor(() =>
            expect(frameParams().get('tokens[color.text]')).toBe('#c9c6bf'),
        );

        await userEvent.click(
            within(warnings).getByRole('button', { name: 'Fix it' }),
        );

        expect(
            within(warnings).getByText('All colors are easy to read.'),
        ).toBeInTheDocument();
        expect(screen.getByLabelText('Text')).not.toHaveValue('#c9c6bf');
    });

    it('takes palettes from a dropped logo, uploads it and previews it', async () => {
        readImagePixels.mockResolvedValue(logoPixels);
        uploadMedia.mockResolvedValue({
            id: 31,
            url: '/storage/media/harbor-lane-logo.png',
            filename: 'harbor-lane-logo.png',
        });
        render(<MakeItYours {...props} />);

        const file = new File(['logo'], 'harbor-lane-logo.png', {
            type: 'image/png',
        });
        await userEvent.upload(screen.getByLabelText('Add your logo'), file);

        expect(
            await screen.findByText(/We found 2 colors in your logo/),
        ).toBeInTheDocument();
        expect(readImagePixels).toHaveBeenCalledWith(
            'blob:harbor-lane-logo',
            96,
        );
        expect(uploadMedia).toHaveBeenCalledWith(file);
        expect(
            screen.getByRole('radio', { name: 'From your logo' }),
        ).toBeChecked();
        expect(screen.getByRole('img', { name: 'Your logo' })).toHaveAttribute(
            'src',
            '/storage/media/harbor-lane-logo.png',
        );
        await waitFor(() => expect(frameParams().get('logo')).toBe('31'));
        expect(frameParams().get('tokens[color.accent]')).toBe('#c2410c');
    });

    it('takes a logo dropped on the logo area', async () => {
        readImagePixels.mockResolvedValue(logoPixels);
        uploadMedia.mockResolvedValue({
            id: 32,
            url: '/storage/media/ribeira-cafe.png',
            filename: 'ribeira-cafe.png',
        });
        render(<MakeItYours {...props} />);

        const zone = document.querySelector<HTMLElement>(
            '[data-test="logo-drop"]',
        )!;
        const file = new File(['logo'], 'ribeira-cafe.png', {
            type: 'image/png',
        });
        fireEvent.dragOver(zone, { dataTransfer: { files: [file] } });
        fireEvent.drop(zone, { dataTransfer: { files: [file] } });

        await waitFor(() => expect(uploadMedia).toHaveBeenCalledWith(file));
        expect(
            await screen.findByRole('img', { name: 'Your logo' }),
        ).toHaveAttribute('src', '/storage/media/ribeira-cafe.png');
    });

    it('refuses files that are not pictures', async () => {
        render(<MakeItYours {...props} />);

        await userEvent.upload(
            screen.getByLabelText('Add your logo'),
            new File(['%PDF'], 'opening-hours.pdf', {
                type: 'application/pdf',
            }),
            { applyAccept: false },
        );

        expect(
            screen.getByText(
                'This file is not a picture we can use. Pick a PNG or JPG file.',
            ),
        ).toBeInTheDocument();
        expect(uploadMedia).not.toHaveBeenCalled();
    });

    it('saves the picks with the logo only when it changed', async () => {
        render(
            <MakeItYours
                {...props}
                logo={{
                    id: 12,
                    url: '/storage/media/old-logo.png',
                    filename: 'old-logo.png',
                }}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Save my look' }),
        );

        expect(router.put).toHaveBeenLastCalledWith(
            '/admin/easy/style',
            { colors: currentColors, preset: 'calm', fontPair: 'classic' },
            expect.any(Object),
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Remove logo' }),
        );
        await userEvent.click(
            screen.getByRole('button', { name: 'Save my look' }),
        );

        expect(router.put).toHaveBeenLastCalledWith(
            '/admin/easy/style',
            expect.objectContaining({ logo: null }),
            expect.any(Object),
        );
        await waitFor(() => expect(frameParams().get('logo')).toBe('0'));
    });

    it('shows server errors next to the fields', () => {
        setPageProps({
            errors: {
                'colors.accent': 'Use a color code like #2e5e4e.',
                preset: 'Pick one of the styles on this page.',
            },
        });
        render(<MakeItYours {...props} />);

        expect(
            screen.getByText('Use a color code like #2e5e4e.', {
                selector: 'p.text-sm, p',
            }),
        ).toBeInTheDocument();
        expect(
            screen.getByText('Pick one of the styles on this page.'),
        ).toBeInTheDocument();
    });

    it('switches the preview between computer, tablet and phone', async () => {
        render(<MakeItYours {...props} />);

        await userEvent.click(screen.getByRole('radio', { name: 'Phone' }));

        expect(frame().style.width).toBe('390px');
    });

    it('undoes the last pick and goes back to the saved look, without saving', async () => {
        render(<MakeItYours {...props} />);
        const undo = screen.getByRole('button', { name: 'Undo' });
        expect(undo).toHaveAttribute('aria-disabled', 'true');

        await userEvent.click(screen.getByRole('radio', { name: 'Bold' }));
        await userEvent.click(screen.getByRole('radio', { name: 'Dark' }));
        expect(undo).toHaveAttribute('aria-disabled', 'false');

        await userEvent.click(undo);
        expect(
            screen.getByRole('radio', { name: 'Your colors now' }),
        ).toBeChecked();
        expect(screen.getByRole('radio', { name: 'Bold' })).toBeChecked();
        await waitFor(() =>
            expect(frameParams().get('tokens[color.background]')).toBe(
                '#f6f7f3',
            ),
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Back to the colors I had' }),
        );
        expect(screen.getByRole('radio', { name: 'Calm' })).toBeChecked();
        expect(screen.getByRole('radio', { name: 'Classic' })).toBeChecked();
        await waitFor(() =>
            expect(frameParams().get('tokens[radius.base]')).toBe('6px'),
        );
        expect(
            screen.getByText(/Back to the colors, fonts and style you had\./),
        ).toHaveAttribute('role', 'status');

        await userEvent.click(undo);
        expect(screen.getByRole('radio', { name: 'Bold' })).toBeChecked();
        expect(router.put).not.toHaveBeenCalled();
    });

    it('makes one Undo step of typing in one color box', async () => {
        render(<MakeItYours {...props} />);
        const undo = screen.getByRole('button', { name: 'Undo' });
        const accent = screen.getByRole('textbox', { name: 'Accent' });

        await userEvent.click(accent);
        await userEvent.type(accent, '{backspace}1{backspace}2{backspace}3');
        expect(accent).toHaveValue('#2e5e43');
        await waitFor(() =>
            expect(frameParams().get('tokens[color.accent]')).toBe('#2e5e43'),
        );

        await userEvent.click(undo);
        expect(accent).toHaveValue('#2e5e4e');
        expect(undo).toHaveAttribute('aria-disabled', 'true');
    });

    it('starts a new Undo step once the color box loses focus', async () => {
        render(<MakeItYours {...props} />);
        const undo = screen.getByRole('button', { name: 'Undo' });
        const accent = screen.getByRole('textbox', { name: 'Accent' });

        await userEvent.type(accent, '{backspace}1{backspace}2');
        await userEvent.tab();
        await userEvent.type(accent, '{backspace}9');
        expect(accent).toHaveValue('#2e5e49');

        await userEvent.click(undo);
        expect(accent).toHaveValue('#2e5e42');
        await userEvent.click(undo);
        expect(accent).toHaveValue('#2e5e4e');
    });

    it('shows a plain message when saving fails and keeps the picks', async () => {
        router.put.mockImplementationOnce((_url, _data, options) => {
            options?.onHttpException?.({ status: 500 });
            options?.onFinish?.({});
        });
        render(<MakeItYours {...props} />);

        await userEvent.click(screen.getByRole('radio', { name: 'Bold' }));
        await userEvent.click(
            screen.getByRole('button', { name: 'Save my look' }),
        );

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Something went wrong on our side. Your changes are still here. Wait a moment, then press Save my look.',
        );
        expect(screen.getByRole('radio', { name: 'Bold' })).toBeChecked();
    });
});
