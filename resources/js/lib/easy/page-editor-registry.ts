import type { ComponentType, SVGProps } from 'react';
import type { Block } from '@/types';

// Extension points of the visual page editor. Plugins and themes add items from their
// builder module (resources/js/builder.ts) with api.pageEditorButtons and api.pageSectionActions.

// What a button or section action can read and change.
export type PageEditorContext = {
    entry: { id: number; title: string; type: string };
    blocks: Block[];
    // Replaces the blocks as one undo step and tells screen reader users what happened.
    change: (blocks: Block[], message: string) => void;
    announce: (message: string) => void;
};

// A button in the top bar of the page editor, like "Write with AI".
export type PageEditorButton = {
    name: string;
    label: string;
    // A Verso icon (or a Lucide icon) drawn before the label.
    icon?: ComponentType<SVGProps<SVGSVGElement>>;
    order?: number;
    run: (context: PageEditorContext) => void;
};

// A button in the toolbar of every section, after Move, Duplicate, Hide and Delete.
export type PageSectionAction = {
    name: string;
    label: string;
    order?: number;
    // Return false to leave the button out for this section.
    shows?: (section: Block) => boolean;
    run: (context: PageEditorContext & { section: Block }) => void;
};
