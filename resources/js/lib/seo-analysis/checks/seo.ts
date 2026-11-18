import { msg, t } from '@/lib/i18n';
import { wordsOf } from '../text';
import type { AnalysisCheck, AnalysisContext, CheckOutcome } from '../types';
import { good, ok, percent, problem } from './helpers';

const LENGTH_TOLERANCE = 10;
const MIN_WORDS_FOR_DENSITY = 100;

type LengthMessages = {
    tooShort: string;
    tooLong: string;
    good: string;
};

const TITLE_LENGTH_MESSAGES: LengthMessages = {
    tooShort: msg(
        'The SEO title is {count, plural, one {# character} other {# characters}}. Make it at least {min}.',
    ),
    tooLong: msg(
        'The SEO title is {count, plural, one {# character} other {# characters}}. Google may cut it after {max}.',
    ),
    good: msg('The SEO title has a good length. Well done.'),
};

const DESCRIPTION_LENGTH_MESSAGES: LengthMessages = {
    tooShort: msg(
        'The meta description is {count, plural, one {# character} other {# characters}}. Make it at least {min}.',
    ),
    tooLong: msg(
        'The meta description is {count, plural, one {# character} other {# characters}}. Google may cut it after {max}.',
    ),
    good: msg('The meta description has a good length. Well done.'),
};

function lengthOutcome(
    length: number,
    min: number,
    max: number,
    messages: LengthMessages,
): CheckOutcome {
    if (length < min) {
        const message = t(messages.tooShort, { count: length, min });

        return length >= min - LENGTH_TOLERANCE
            ? ok(message)
            : problem(message);
    }

    if (length > max) {
        const message = t(messages.tooLong, { count: length, max });

        return length <= max + LENGTH_TOLERANCE
            ? ok(message)
            : problem(message);
    }

    return good(t(messages.good));
}

function subheadings(context: AnalysisContext): string[] {
    return context.content.headings
        .filter((heading) => heading.level === 2 || heading.level === 3)
        .map((heading) => heading.text);
}

export const SEO_CHECKS: AnalysisCheck[] = [
    {
        id: 'keyphrase-set',
        group: 'seo',
        label: msg('Focus keyphrase'),
        run: ({ keyphrase }) =>
            keyphrase.isEmpty
                ? problem(t('Set a focus keyphrase to get keyphrase tips.'))
                : good(t('You set a focus keyphrase. Good.')),
    },
    {
        id: 'keyphrase-length',
        group: 'seo',
        label: msg('Keyphrase length'),
        needsKeyphrase: true,
        run: ({ keyphrase, config }) =>
            keyphrase.words.length > config.keyphraseMaxWords
                ? ok(
                      t(
                          'The keyphrase has {count, plural, one {# main word} other {# main words}}. Try {max} or fewer.',
                          {
                              count: keyphrase.words.length,
                              max: config.keyphraseMaxWords,
                          },
                      ),
                  )
                : good(t('The keyphrase has a good length.')),
    },
    {
        id: 'keyphrase-title',
        group: 'seo',
        label: msg('Keyphrase in SEO title'),
        needsKeyphrase: true,
        run: ({ keyphrase, input }) =>
            keyphrase.matches(input.seoTitle)
                ? good(t('The focus keyphrase is in the SEO title. Well done.'))
                : problem(t('Add the focus keyphrase to the SEO title.')),
    },
    {
        id: 'keyphrase-intro',
        group: 'seo',
        label: msg('Keyphrase in introduction'),
        needsKeyphrase: true,
        run: ({ keyphrase, content }) => {
            if (content.firstParagraph === '') {
                return problem(
                    t('Write a first paragraph that uses the focus keyphrase.'),
                );
            }

            return keyphrase.matches(content.firstParagraph)
                ? good(
                      t('The focus keyphrase is in the first paragraph. Good.'),
                  )
                : problem(t('Use the focus keyphrase in the first paragraph.'));
        },
    },
    {
        id: 'keyphrase-slug',
        group: 'seo',
        label: msg('Keyphrase in slug'),
        needsKeyphrase: true,
        run: ({ keyphrase, input }) =>
            keyphrase.matches(input.slug.replace(/[-_/]+/g, ' '))
                ? good(t('The focus keyphrase is in the slug. Great.'))
                : ok(t('Add the focus keyphrase to the slug (the URL).')),
    },
    {
        id: 'keyphrase-description',
        group: 'seo',
        label: msg('Keyphrase in meta description'),
        needsKeyphrase: true,
        run: ({ keyphrase, input }) => {
            if (input.description.trim() === '') {
                return problem(
                    t(
                        'Write a meta description that uses the focus keyphrase.',
                    ),
                );
            }

            return keyphrase.matches(input.description)
                ? good(
                      t(
                          'The focus keyphrase is in the meta description. Good.',
                      ),
                  )
                : problem(
                      t('Add the focus keyphrase to the meta description.'),
                  );
        },
    },
    {
        id: 'keyphrase-headings',
        group: 'seo',
        label: msg('Keyphrase in subheadings'),
        needsKeyphrase: true,
        run: (context) => {
            const texts = subheadings(context);

            if (texts.length === 0) {
                return ok(
                    t(
                        'Add H2 or H3 subheadings and use the focus keyphrase in one of them.',
                    ),
                );
            }

            return texts.some((text) => context.keyphrase.matches(text))
                ? good(t('The focus keyphrase is in a subheading. Nice.'))
                : problem(
                      t(
                          'Use the focus keyphrase in at least one H2 or H3 subheading.',
                      ),
                  );
        },
    },
    {
        id: 'keyphrase-density',
        group: 'seo',
        label: msg('Keyphrase density'),
        needsKeyphrase: true,
        run: ({ keyphrase, content, config }) => {
            const total = content.words.length;

            if (total < MIN_WORDS_FOR_DENSITY) {
                return null;
            }

            const count = keyphrase.count(content.text);
            const density = percent(count, total);
            const values = { count, density };

            if (density < config.keyphraseDensityMin) {
                return problem(
                    t(
                        'The focus keyphrase shows up {count, plural, one {# time} other {# times}} ({density, number}%). Use it a few more times.',
                        values,
                    ),
                );
            }

            if (density > config.keyphraseDensityMax) {
                return problem(
                    t(
                        'The focus keyphrase shows up {count, plural, one {# time} other {# times}} ({density, number}%). That is too often. Use it a bit less.',
                        values,
                    ),
                );
            }

            return good(
                t(
                    'The focus keyphrase shows up {count, plural, one {# time} other {# times}} ({density, number}%). That is just right.',
                    values,
                ),
            );
        },
    },
    {
        id: 'title-length',
        group: 'seo',
        label: msg('SEO title length'),
        run: ({ input, config }) => {
            const length = input.seoTitle.trim().length;

            if (length === 0) {
                return problem(t('Add an SEO title.'));
            }

            return lengthOutcome(
                length,
                config.titleMin,
                config.titleMax,
                TITLE_LENGTH_MESSAGES,
            );
        },
    },
    {
        id: 'description-length',
        group: 'seo',
        label: msg('Meta description length'),
        run: ({ input, config }) => {
            const length = input.description.trim().length;

            if (length === 0) {
                return problem(
                    t(
                        'Add a meta description. Google shows it under the title.',
                    ),
                );
            }

            const outcome = lengthOutcome(
                length,
                config.descriptionMin,
                config.descriptionMax,
                DESCRIPTION_LENGTH_MESSAGES,
            );

            return outcome.status === 'problem' ? ok(outcome.message) : outcome;
        },
    },
    {
        id: 'text-length',
        group: 'seo',
        label: msg('Text length'),
        run: ({ content, config }) => {
            const count = content.words.length;
            if (count >= config.minWords) {
                return good(
                    t(
                        'The text has {count, plural, one {# word} other {# words}}. Good job.',
                        { count },
                    ),
                );
            }

            const advice = t(
                'The text has {count, plural, one {# word} other {# words}}. Write at least {min}.',
                { count, min: config.minWords },
            );

            return count >= config.minWords / 2 ? ok(advice) : problem(advice);
        },
    },
    {
        id: 'internal-links',
        group: 'seo',
        label: msg('Internal links'),
        run: ({ content }) => {
            const count = content.links.filter((link) => link.internal).length;

            return count > 0
                ? good(
                      t(
                          'You link to {count, plural, one {# other page} other {# other pages}} on your site. Good.',
                          { count },
                      ),
                  )
                : problem(t('Add a link to another page on your site.'));
        },
    },
    {
        id: 'outbound-links',
        group: 'seo',
        label: msg('Outbound links'),
        run: ({ content }) => {
            const outbound = content.links.filter((link) => !link.internal);

            if (outbound.length === 0) {
                return ok(t('Add a link to a useful page on another site.'));
            }

            if (outbound.every((link) => link.nofollow)) {
                return ok(
                    t(
                        'All your links to other sites are nofollow. Make at least one a normal link.',
                    ),
                );
            }

            return good(
                t(
                    'You link to {count, plural, one {# page} other {# pages}} on other sites. Good.',
                    { count: outbound.length },
                ),
            );
        },
    },
    {
        id: 'image-alt',
        group: 'seo',
        label: msg('Image alt text'),
        run: ({ content, keyphrase }) => {
            const images = content.images;

            if (images.length === 0) {
                return null;
            }

            const missing = images.filter((image) => image.alt === '').length;

            if (missing > 0) {
                return problem(
                    t(
                        '{count, plural, one {# image has} other {# images have}} no alt text. Describe each image for people who cannot see it.',
                        { count: missing },
                    ),
                );
            }

            if (keyphrase.isEmpty) {
                return good(t('All images have alt text. Well done.'));
            }

            return images.some((image) => keyphrase.matches(image.alt))
                ? good(
                      t(
                          'All images have alt text, and one uses the focus keyphrase. Well done.',
                      ),
                  )
                : good(
                      t(
                          'All images have alt text. You can also use the focus keyphrase in one of them.',
                      ),
                  );
        },
    },
    {
        id: 'images',
        group: 'seo',
        label: msg('Images'),
        run: ({ content, input }) =>
            content.images.length > 0 || input.hasFeaturedImage
                ? good(t('This page has images. Nice.'))
                : ok(
                      t(
                          'Add an image or a featured image to make the page more inviting.',
                      ),
                  ),
    },
    {
        id: 'slug-length',
        group: 'seo',
        label: msg('Slug length'),
        run: ({ input, config }) => {
            const count = wordsOf(input.slug.replace(/[-_/]+/g, ' ')).length;

            if (count === 0) {
                return null;
            }

            return count > config.slugMaxWords
                ? ok(
                      t(
                          'The slug has {count, plural, one {# word} other {# words}}. Keep it to {max} or fewer.',
                          { count, max: config.slugMaxWords },
                      ),
                  )
                : good(t('The slug is short and clear.'));
        },
    },
    {
        id: 'single-h1',
        group: 'seo',
        label: msg('Single H1'),
        run: ({ content }) =>
            content.headings.some((heading) => heading.level === 1)
                ? problem(
                      t(
                          'The page title is already the H1. Use H2 for sections.',
                      ),
                  )
                : good(t('The page has only one H1, the title. Good.')),
    },
];
