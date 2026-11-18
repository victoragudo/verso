import { describe, expect, it } from 'vitest';
import {
    allIds,
    duplicateSection,
    freshId,
    insertSections,
    isHidden,
    moveSection,
    plainText,
    removeSection,
    sectionName,
    setImage,
    setText,
    toggleHidden,
    withFreshIds,
} from '@/lib/easy/page-blocks';
import { bakeryBlocks, croissantPhoto } from './page-fixtures';

const ids = (blocks: { id: string }[]) => blocks.map((block) => block.id);

describe('page blocks', () => {
    it('makes ids that are not used yet', () => {
        const used = new Set(['b_hero0001']);
        const id = freshId(used);

        expect(id).toMatch(/^b_[a-z0-9]{8}$/);
        expect(used.has(id)).toBe(true);
    });

    it('moves a section up and down and keeps the edges', () => {
        const blocks = bakeryBlocks();

        expect(ids(moveSection(blocks, 'b_serv0001', 'up'))).toEqual([
            'b_serv0001',
            'b_hero0001',
            'b_gall0001',
        ]);
        expect(ids(moveSection(blocks, 'b_serv0001', 'down'))).toEqual([
            'b_hero0001',
            'b_gall0001',
            'b_serv0001',
        ]);
        expect(moveSection(blocks, 'b_hero0001', 'up')).toBe(blocks);
        expect(moveSection(blocks, 'b_gall0001', 'down')).toBe(blocks);
        expect(moveSection(blocks, 'b_unknown1', 'up')).toBe(blocks);
        expect(ids(blocks)).toEqual(['b_hero0001', 'b_serv0001', 'b_gall0001']);
    });

    it('duplicates a section right after it with new ids everywhere', () => {
        const blocks = bakeryBlocks();
        const next = duplicateSection(blocks, 'b_hero0001');
        const copy = next[1];

        expect(next).toHaveLength(4);
        expect(copy.attrs).toEqual(blocks[0].attrs);
        expect(copy.attrs).not.toBe(blocks[0].attrs);
        expect(copy.children?.map((child) => child.content)).toEqual(
            blocks[0].children?.map((child) => child.content),
        );
        expect(allIds(next).size).toBe(
            allIds(blocks).size * 2 - allIds(blocks.slice(1)).size,
        );
        expect([...allIds([copy])].some((id) => allIds(blocks).has(id))).toBe(
            false,
        );
    });

    it('gives a block and its children fresh ids', () => {
        const used = allIds(bakeryBlocks());
        const copy = withFreshIds(bakeryBlocks()[0], used);

        expect(copy.id).not.toBe('b_hero0001');
        expect(
            copy.children?.every(
                (child) =>
                    !child.id.startsWith('b_title') &&
                    !child.id.startsWith('b_text'),
            ),
        ).toBe(true);
    });

    it('removes a section and says where it was', () => {
        const blocks = bakeryBlocks();
        const result = removeSection(blocks, 'b_serv0001');

        expect(ids(result.blocks)).toEqual(['b_hero0001', 'b_gall0001']);
        expect(result.removed?.id).toBe('b_serv0001');
        expect(result.index).toBe(1);
        expect(removeSection(blocks, 'b_unknown1')).toEqual({
            blocks,
            removed: null,
            index: -1,
        });
    });

    it('puts a removed section back where it was', () => {
        const { blocks, removed, index } = removeSection(
            bakeryBlocks(),
            'b_serv0001',
        );

        expect(ids(insertSections(blocks, index, [removed!]))).toEqual([
            'b_hero0001',
            'b_serv0001',
            'b_gall0001',
        ]);
    });

    it('inserts new sections and renews ids that are already on the page', () => {
        const blocks = bakeryBlocks();
        const faq = {
            id: 'b_faq00001',
            type: 'section',
            attrs: {},
            content: '',
            children: [
                {
                    id: 'b_title001',
                    type: 'heading',
                    attrs: {},
                    content: 'Questions',
                    children: [],
                },
            ],
        };
        const next = insertSections(blocks, 1, [faq]);

        expect(next).toHaveLength(4);
        expect(next[1].id).not.toBe('b_faq00001');
        expect(next[1].children?.[0].id).not.toBe('b_title001');
        expect(allIds(next).size).toBe(allIds(blocks).size + 2);

        const fresh = { ...faq, id: 'b_faq00002', children: [] };
        expect(insertSections(blocks, 99, [fresh]).at(-1)).toBe(fresh);
        expect(insertSections(blocks, -3, [fresh])[0]).toBe(fresh);
    });

    it('hides and shows a section and keeps its other styles', () => {
        const blocks = bakeryBlocks();
        const hidden = toggleHidden(blocks, 'b_hero0001');

        expect(isHidden(hidden[0])).toBe(true);
        expect(hidden[0].attrs.design).toEqual({
            spacing: { padding: { top: 'var:spacing.x-large' } },
            visibility: { hidden: true },
        });

        const shown = toggleHidden(hidden, 'b_hero0001');
        expect(isHidden(shown[0])).toBe(false);
        expect(shown[0].attrs).toEqual(blocks[0].attrs);

        const plain = toggleHidden(
            toggleHidden(blocks, 'b_serv0001'),
            'b_serv0001',
        );
        expect(plain[1].attrs).toEqual({ tag: 'section' });
    });

    it('sets text content and text attrs deep in the tree', () => {
        const blocks = bakeryBlocks();
        const titled = setText(
            blocks,
            'b_title001',
            'content',
            'Sourdough every morning',
        );
        const labelled = setText(titled, 'b_button01', 'label', 'Order a cake');

        expect(labelled[0].children?.[0].content).toBe(
            'Sourdough every morning',
        );
        expect(labelled[0].children?.[2].attrs.label).toBe('Order a cake');
        expect(labelled[1]).toBe(blocks[1]);
        expect(
            setText(
                blocks,
                'b_title001',
                'content',
                'Fresh bread on Harbour Lane',
            ),
        ).toBe(blocks);
    });

    it('renames a section named after the heading people edit', () => {
        const blocks = bakeryBlocks();
        const next = setText(
            blocks,
            'b_title001',
            'content',
            'Sourdough <em>every</em> morning',
        );

        expect(next[0].attrs.label).toBe('Sourdough every morning');
        expect(next[0].attrs.design).toEqual(blocks[0].attrs.design);
        expect(blocks[0].attrs.label).toBe('Fresh bread on Harbour Lane');
    });

    it('keeps a section name that differs from the heading', () => {
        const blocks = bakeryBlocks();
        blocks[0].attrs.label = 'Welcome banner';

        expect(
            setText(blocks, 'b_title001', 'content', 'Sourdough every morning')[0]
                .attrs.label,
        ).toBe('Welcome banner');
        expect(
            setText(blocks, 'b_text0001', 'content', '<p>Fresh rolls.</p>')[0]
                .attrs.label,
        ).toBe('Welcome banner');
    });

    it('replaces the picture of an image block', () => {
        const next = setImage(
            bakeryBlocks(),
            { blockId: 'b_image001' },
            croissantPhoto,
        );

        expect(next[1].children?.[1].attrs).toEqual({
            mediaId: 21,
            src: '/storage/croissants.jpg',
            alt: 'Croissants on a tray',
            width: 1600,
            height: 1067,
        });
    });

    it('drops old sizes when the new picture has none', () => {
        const next = setImage(
            bakeryBlocks(),
            { blockId: 'b_image001' },
            { ...croissantPhoto, alt: null, width: null, height: null },
        );

        expect(next[1].children?.[1].attrs).toEqual({
            mediaId: 21,
            src: '/storage/croissants.jpg',
            alt: '',
        });
    });

    it('replaces one picture of a gallery and its media id', () => {
        const next = setImage(
            bakeryBlocks(),
            { blockId: 'b_gallery1', index: 1 },
            croissantPhoto,
        );
        const gallery = next[2].children?.[0].attrs as {
            images: { mediaId: number; alt: string }[];
            mediaIds: number[];
        };

        expect(gallery.images[1]).toMatchObject({
            mediaId: 21,
            alt: 'Croissants on a tray',
        });
        expect(gallery.images[0]).toMatchObject({ mediaId: 7 });
        expect(gallery.mediaIds).toEqual([7, 21]);
        expect(
            setImage(
                bakeryBlocks(),
                { blockId: 'b_gallery1', index: 5 },
                croissantPhoto,
            )[2].children?.[0].attrs.mediaIds,
        ).toEqual([7, 8]);
    });

    it('names sections by label, first heading or place', () => {
        const blocks = bakeryBlocks();

        expect(sectionName(blocks, 'b_hero0001')).toBe(
            'Section 1: Fresh bread on Harbour Lane',
        );
        expect(sectionName(blocks, 'b_serv0001')).toBe(
            'Section 2: What we bake',
        );
        expect(
            sectionName(
                [
                    {
                        id: 'b_empty001',
                        type: 'section',
                        attrs: {},
                        children: [],
                    },
                ],
                'b_empty001',
            ),
        ).toBe('Section 1');
        expect(sectionName(blocks, 'b_unknown1')).toBe('Section');
    });

    it('turns a little HTML into plain text', () => {
        expect(plainText('<p>Fish &amp; chips&nbsp;<em>daily</em></p>')).toBe(
            'Fish & chips daily',
        );
    });
});
