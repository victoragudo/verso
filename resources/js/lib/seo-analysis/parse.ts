import type { Block } from '@/types';
import { decodeEntities, htmlToText, splitSentences, wordsOf } from './text';
import type {
    ParsedContent,
    ParsedHeading,
    ParsedImage,
    ParsedLink,
} from './types';

const LINK_PATTERN = /<a\b([^>]*)>([\s\S]*?)<\/a>/gi;
const IMAGE_PATTERN = /<img\b([^>]*)>/gi;
const LIST_ITEM_PATTERN = /<li\b[^>]*>/i;
const IGNORED_HREF_PATTERN = /^(?:#|mailto:|tel:|sms:|javascript:)/i;
const SCHEME_PATTERN = /^[a-z][a-z0-9+.-]*:/i;
const HOST_PATTERN = /^(?:[a-z][a-z0-9+.-]*:)?\/\/([^/?#]+)/i;
const SKIPPED_TEXT_TYPES = new Set(['code', 'html']);
const NO_TEXT_TYPES = new Set([
    'image',
    'gallery',
    'embed',
    'video',
    'separator',
    'button',
]);

// Reads one attribute like href="..." from the inside of an HTML tag.
function attributeOf(attributes: string, name: string): string | null {
    const match = new RegExp(
        `(?:^|\\s)${name}\\s*=\\s*(?:"([^"]*)"|'([^']*)'|([^\\s>]+))`,
        'i',
    ).exec(attributes);

    if (match === null) {
        return null;
    }

    return decodeEntities(match[1] ?? match[2] ?? match[3] ?? '');
}

function stringAttr(block: Block, name: string): string {
    const value = block.attrs?.[name];

    return typeof value === 'string' ? value : '';
}

// The host part of a host name, without port and "www.".
function bareHost(host: string): string {
    return host
        .toLowerCase()
        .replace(/:\d+$/, '')
        .replace(/^www\./, '');
}

// Collects text, links and images while it walks the blocks in order.
class ContentCollector {
    private readonly parts: string[] = [];
    private readonly words: string[] = [];
    private readonly paragraphs: string[] = [];
    private readonly headings: ParsedHeading[] = [];
    private readonly links: ParsedLink[] = [];
    private readonly images: ParsedImage[] = [];
    private readonly runs: number[] = [0];
    private readonly siteHost: string;

    constructor(siteHost: string) {
        this.siteHost = bareHost(
            siteHost.replace(/^[a-z][a-z0-9+.-]*:\/\//i, '').split('/')[0],
        );
    }

    visit(blocks: Block[]): void {
        for (const block of blocks) {
            if (block !== null && typeof block === 'object') {
                this.visitBlock(block);
            }
        }
    }

    result(): ParsedContent {
        const sentences = this.paragraphs.flatMap((paragraph) =>
            splitSentences(paragraph),
        );

        return {
            text: this.parts.join('\n\n'),
            words: this.words,
            sentences,
            paragraphs: this.paragraphs,
            firstParagraph: this.paragraphs[0] ?? '',
            headings: this.headings,
            links: this.links,
            images: this.images,
            wordsBetweenHeadings: this.runs,
        };
    }

    private visitBlock(block: Block): void {
        const html = typeof block.content === 'string' ? block.content : '';

        if (block.type !== 'code') {
            this.collectLinks(html);
        }

        switch (block.type) {
            case 'heading':
                this.addHeading(block, html);
                break;
            case 'paragraph':
            case 'quote':
                this.addParagraph(htmlToText(html));
                break;
            case 'list':
                html.split(LIST_ITEM_PATTERN).forEach((item) =>
                    this.addParagraph(htmlToText(item)),
                );
                break;
            case 'table':
                this.addTable(block);
                break;
            case 'image':
                this.addImage(block.attrs ?? {});
                break;
            case 'gallery':
                this.addGallery(block);
                break;
            case 'button':
                this.addButton(block);
                break;
            case 'html':
                this.collectImages(html);
                break;
            default:
                if (
                    !SKIPPED_TEXT_TYPES.has(block.type) &&
                    !NO_TEXT_TYPES.has(block.type)
                ) {
                    this.addText(htmlToText(html));
                }
        }

        if (Array.isArray(block.children)) {
            this.visit(block.children);
        }
    }

    private addText(text: string): string[] {
        if (text === '') {
            return [];
        }

        const words = wordsOf(text);
        this.parts.push(text);
        this.words.push(...words);
        this.runs[this.runs.length - 1] += words.length;

        return words;
    }

    private addParagraph(text: string): void {
        if (this.addText(text).length > 0) {
            this.paragraphs.push(text);
        }
    }

    private addHeading(block: Block, html: string): void {
        const text = htmlToText(html).replace(/\n/g, ' ');
        const level = Number(block.attrs?.level ?? 2);

        if (text === '') {
            return;
        }

        this.headings.push({
            level: Number.isInteger(level)
                ? Math.min(6, Math.max(1, level))
                : 2,
            text,
        });
        this.parts.push(text);
        this.words.push(...wordsOf(text));
        this.runs.push(0);
    }

    private addTable(block: Block): void {
        const rows = Array.isArray(block.attrs?.rows) ? block.attrs.rows : [];

        for (const row of rows) {
            if (!Array.isArray(row)) {
                continue;
            }

            for (const cell of row) {
                if (typeof cell === 'string') {
                    this.collectLinks(cell);
                    this.addText(htmlToText(cell));
                }
            }
        }
    }

    private addImage(attrs: Record<string, unknown>): void {
        const src = typeof attrs.src === 'string' ? attrs.src : '';
        const hasMedia = attrs.mediaId !== null && attrs.mediaId !== undefined;

        if (src === '' && !hasMedia) {
            return;
        }

        this.images.push({
            src,
            alt: typeof attrs.alt === 'string' ? attrs.alt.trim() : '',
        });
    }

    private addGallery(block: Block): void {
        const images = Array.isArray(block.attrs?.images)
            ? block.attrs.images
            : [];

        for (const image of images) {
            if (image !== null && typeof image === 'object') {
                this.addImage(image as Record<string, unknown>);
            }
        }
    }

    private addButton(block: Block): void {
        this.addLink(
            stringAttr(block, 'url'),
            stringAttr(block, 'label'),
            stringAttr(block, 'rel'),
        );
    }

    private collectLinks(html: string): void {
        if (!html.includes('<a')) {
            return;
        }

        for (const match of html.matchAll(LINK_PATTERN)) {
            this.addLink(
                attributeOf(match[1], 'href') ?? '',
                htmlToText(match[2]).replace(/\n/g, ' '),
                attributeOf(match[1], 'rel') ?? '',
            );
        }
    }

    private collectImages(html: string): void {
        if (!html.includes('<img')) {
            return;
        }

        for (const match of html.matchAll(IMAGE_PATTERN)) {
            this.addImage({
                src: attributeOf(match[1], 'src') ?? '',
                alt: attributeOf(match[1], 'alt') ?? '',
            });
        }
    }

    private addLink(href: string, text: string, rel: string): void {
        const target = href.trim();

        if (target === '' || IGNORED_HREF_PATTERN.test(target)) {
            return;
        }

        this.links.push({
            href: target,
            text,
            internal: this.isInternal(target),
            nofollow: /(?:^|\s)nofollow(?:\s|$)/i.test(rel),
        });
    }

    // Relative links and links to the site host are internal.
    private isInternal(href: string): boolean {
        const host = HOST_PATTERN.exec(href);

        if (host !== null) {
            return this.siteHost !== '' && bareHost(host[1]) === this.siteHost;
        }

        return !SCHEME_PATTERN.test(href);
    }
}

// Reads the blocks of an entry and returns their text, headings, links and images.
export function parseBlocks(blocks: Block[], siteHost: string): ParsedContent {
    const collector = new ContentCollector(siteHost);
    collector.visit(Array.isArray(blocks) ? blocks : []);

    return collector.result();
}
