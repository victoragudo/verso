export type SearchableOption = {
    value: string;
    label: string;
    description?: string;
    group?: string;
    keywords?: string[];
    // The language of the label, for labels like language names.
    lang?: string;
};

const ACCENT_MARKS = /[̀-ͯ]/g;
const WORD_SEPARATORS = /[\s_/]+/g;

// Makes text easy to compare: no accents, lower case, "_" and "/" as spaces.
export function normalizeSearchText(text: string): string {
    return text
        .normalize('NFD')
        .replace(ACCENT_MARKS, '')
        .toLowerCase()
        .replace(WORD_SEPARATORS, ' ')
        .trim();
}

// Joins all the searchable text of one option into one string.
export function optionSearchText(option: SearchableOption): string {
    return normalizeSearchText(
        [
            option.label,
            option.value,
            option.group ?? '',
            ...(option.keywords ?? []),
        ].join(' '),
    );
}

// True when every word of the query is found in the text.
export function matchesSearch(searchText: string, query: string): boolean {
    const words = normalizeSearchText(query).split(' ').filter(Boolean);

    return words.every((word) => searchText.includes(word));
}

// Returns a filter that keeps the options that match a query.
export function createOptionFilter<T extends SearchableOption>(
    options: T[],
): (query: string) => T[] {
    const searchTexts = options.map(optionSearchText);

    return (query) => {
        if (normalizeSearchText(query) === '') {
            return options;
        }

        return options.filter((_, index) =>
            matchesSearch(searchTexts[index], query),
        );
    };
}
