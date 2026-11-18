import { currentLocale, msg, t } from '@/lib/i18n';

// Ready sections as the server lists them (SectionLibrary::toArray()), and the gallery search.

export type SectionInfo = {
    key: string;
    label: string;
    description: string;
    icon: string;
    category: string;
    keywords: string[];
    plugin: string | null;
};

// Plain names of the core categories. Unknown categories get their key with a capital letter.
const CATEGORY_LABELS: Record<string, string> = {
    intro: msg('Welcome'),
    content: msg('Text'),
    media: msg('Pictures'),
    trust: msg('Reviews'),
    sales: msg('Prices'),
    people: msg('People'),
    help: msg('Help'),
    contact: msg('Contact'),
    action: msg('Call to action'),
};

export function categoryLabel(category: string): string {
    const label = CATEGORY_LABELS[category];

    return label
        ? t(label)
        : category.charAt(0).toUpperCase() +
              category.slice(1).replace(/-/g, ' ');
}

// The categories used by the sections, in the order they first appear.
export function sectionCategories(
    sections: SectionInfo[],
): { value: string; label: string }[] {
    const seen = new Set<string>();

    return sections.flatMap((section) => {
        if (seen.has(section.category)) {
            return [];
        }

        seen.add(section.category);

        return [
            { value: section.category, label: categoryLabel(section.category) },
        ];
    });
}

const normalize = (text: string) =>
    text
        .toLocaleLowerCase(currentLocale())
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '');

// Sections of a category (null for all) whose label, description or keywords hold every searched word.
export function filterSections(
    sections: SectionInfo[],
    query: string,
    category: string | null,
): SectionInfo[] {
    const words = normalize(query).split(/\s+/).filter(Boolean);

    return sections.filter((section) => {
        if (category !== null && section.category !== category) {
            return false;
        }

        const haystack = normalize(
            [
                section.label,
                section.description,
                section.key,
                categoryLabel(section.category),
                ...section.keywords,
            ].join(' '),
        );

        return words.every((word) => haystack.includes(word));
    });
}
