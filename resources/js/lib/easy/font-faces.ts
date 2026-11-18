// Loads theme font files into the admin page, so font samples use the real fonts. Fails quietly.

export type FontFaceInfo = { font: string; family: string; url: string };

export function loadFontFaces(faces: FontFaceInfo[]): void {
    if (typeof FontFace === 'undefined' || !document.fonts) {
        return;
    }

    for (const face of faces) {
        const font = new FontFace(face.family, `url("${face.url}")`, {
            display: 'swap',
        });

        font.load()
            .then((loaded) => document.fonts.add(loaded))
            .catch(() => undefined);
    }
}
