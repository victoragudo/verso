// Small text helpers shared by the SEO previews. They follow the server rules in SeoSettings.

export type SiteInfo = {
    name: string;
    tagline: string;
    url: string;
    host: string;
    locale: string;
    titleFormat: string;
    separator: string;
    defaultDescription: string | null;
    defaultImage: string | null;
    twitterSite: string;
};

// Builds the page title from the title format, like "Riding Tram 28 | Harbor Lane".
export function formatTitle(title: string, site: SiteInfo): string {
    const separator = ` ${site.separator} `;
    const text = site.titleFormat
        .replaceAll('{title}', title)
        .replaceAll('{sep}', separator)
        .replaceAll('{site}', site.name)
        .replaceAll('{tagline}', site.tagline)
        .trim();
    const symbol = escapeRegExp(site.separator.trim());
    const cleaned = symbol
        ? text
              .replace(
                  new RegExp(`^\\s*${symbol}\\s*|\\s*${symbol}\\s*$`, 'gu'),
                  '',
              )
              .trim()
        : text;

    return cleaned === '' ? title : cleaned;
}

// Cuts text at a word and adds an ellipsis, like search results do.
export function truncate(text: string, max: number): string {
    const clean = text.replace(/\s+/g, ' ').trim();

    if (clean.length <= max) {
        return clean;
    }

    const cut = clean.slice(0, max);
    const space = cut.lastIndexOf(' ');

    return `${(space > max * 0.6 ? cut.slice(0, space) : cut).trimEnd()}…`;
}

// "harborlane.example.org › blog › riding-tram-28", the way Google shows an address.
export function breadcrumbUrl(url: string): string {
    try {
        const parsed = new URL(url);
        const parts = parsed.pathname.split('/').filter(Boolean);

        return [parsed.host, ...parts.map(decodeURIComponent)].join(' › ');
    } catch {
        return url;
    }
}

export function hostOf(url: string): string {
    try {
        return new URL(url).host;
    } catch {
        return url;
    }
}

// The last part of a path, like "riding-tram-28".
export function slugOf(url: string | null | undefined): string {
    if (!url) {
        return '';
    }

    try {
        const parts = new URL(url, 'https://localhost').pathname
            .split('/')
            .filter(Boolean);

        return decodeURIComponent(parts.at(-1) ?? '');
    } catch {
        return '';
    }
}

function escapeRegExp(text: string): string {
    return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
