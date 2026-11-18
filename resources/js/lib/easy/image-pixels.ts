import { t } from '@/lib/i18n';

// Reads the pixels of an image in the browser, shrunk so the longest side has at most maxSize pixels.
export async function readImagePixels(
    src: string,
    maxSize: number,
): Promise<Uint8ClampedArray> {
    const image = new Image();
    image.decoding = 'async';
    image.src = src;
    await image.decode();

    const scale = Math.min(
        1,
        maxSize / Math.max(image.naturalWidth || 1, image.naturalHeight || 1),
    );
    const width = Math.max(1, Math.round((image.naturalWidth || 1) * scale));
    const height = Math.max(1, Math.round((image.naturalHeight || 1) * scale));
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const context = canvas.getContext('2d', { willReadFrequently: true });

    if (!context) {
        throw new Error(t('This browser cannot read the colors of pictures.'));
    }

    context.drawImage(image, 0, 0, width, height);

    return context.getImageData(0, 0, width, height).data;
}
