import type { PageEditorContext } from '@/lib/easy/page-editor-registry';
import type { Block } from '@/types';

// What the server shares with the page editor (slot "page-editor") when the user may use the assistant.
export type PageHelperProps = {
    ready: boolean;
    settingsUrl: string | null;
    endpoints: {
        sectionText: string;
        headlines: string;
        translate: string;
        altText: string;
        business: string;
    };
    locales: { code: string; name: string }[];
    business: string;
    businessMax: number;
    maxImages: number;
};

// A dialog the helper was asked to open, with the page editor context of that moment.
export type PageHelperRequest =
    | { kind: 'menu' | 'setup' | 'translate'; context: PageEditorContext }
    | {
          kind: 'section-text' | 'headlines' | 'images';
          context: PageEditorContext;
          section: Block;
      };

export type SectionTextResult = { section: Block };
export type HeadlinesResult = { headlines: string[] };
export type BusinessResult = { business: string };
