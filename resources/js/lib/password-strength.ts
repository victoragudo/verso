// Scores a password from 0 (too weak) to 4 (strong) and says how to make it better.

export type PasswordStrength = {
    score: 0 | 1 | 2 | 3 | 4;
    label: string;
    meetsRules: boolean;
    hints: string[];
};

import { msg, t } from '@/lib/i18n';

const LABELS = [
    msg('Too weak'),
    msg('Weak'),
    msg('Fair'),
    msg('Good'),
    msg('Strong'),
] as const;

const COMMON_WORDS = [
    'password',
    'passw0rd',
    'qwerty',
    'letmein',
    'welcome',
    'admin',
    'iloveyou',
    'monkey',
    'dragon',
    'verso',
    '123456',
    'abc123',
];

const SEQUENCES = [
    'abcdefghijklmnopqrstuvwxyz',
    '0123456789',
    'qwertyuiop',
    'asdfghjkl',
];

const SEQUENCE_LENGTH = 4;
const LONG_PASSWORD = 16;
const VERY_LONG_PASSWORD = 20;
const MIN_CONTEXT_WORD = 3;

// True when the text has a run like "abcd" or "4321".
function hasSequence(text: string): boolean {
    const lower = text.toLowerCase();

    return SEQUENCES.some((sequence) => {
        const reversed = sequence.split('').reverse().join('');

        for (
            let start = 0;
            start + SEQUENCE_LENGTH <= sequence.length;
            start++
        ) {
            if (
                lower.includes(
                    sequence.slice(start, start + SEQUENCE_LENGTH),
                ) ||
                lower.includes(reversed.slice(start, start + SEQUENCE_LENGTH))
            ) {
                return true;
            }
        }

        return false;
    });
}

// Words from the email or site name, like "ines" from "ines@lisbon.pt".
function contextWords(context: string[]): string[] {
    return context
        .flatMap((value) => value.toLowerCase().split(/[^\p{L}\p{N}]+/u))
        .filter((word) => word.length >= MIN_CONTEXT_WORD);
}

export function passwordStrength(
    password: string,
    minLength: number,
    context: string[] = [],
): PasswordStrength {
    const hints: string[] = [];
    const hasLetters = /\p{L}/u.test(password);
    const hasNumbers = /\p{N}/u.test(password);
    const meetsRules = password.length >= minLength && hasLetters && hasNumbers;

    if (password.length < minLength) {
        hints.push(
            t(
                '{count, plural, one {Use at least # character.} other {Use at least # characters.}}',
                { count: minLength },
            ),
        );
    }

    if (!hasLetters || !hasNumbers) {
        hints.push(t('Mix letters and numbers.'));
    }

    if (password === '') {
        return { score: 0, label: t(LABELS[0]), meetsRules: false, hints };
    }

    const classes = [/\p{Ll}/u, /\p{Lu}/u, /\p{N}/u, /[^\p{L}\p{N}]/u].filter(
        (pattern) => pattern.test(password),
    ).length;

    let points = 0;
    points += password.length >= minLength ? 1 : 0;
    points += password.length >= LONG_PASSWORD ? 1 : 0;
    points += password.length >= VERY_LONG_PASSWORD ? 1 : 0;
    points += classes >= 3 ? 1 : 0;
    points += classes === 4 ? 1 : 0;

    const lower = password.toLowerCase();

    if (COMMON_WORDS.some((word) => lower.includes(word))) {
        points -= 2;
        hints.push(t('Avoid common words like "password" or "admin".'));
    }

    if (contextWords(context).some((word) => lower.includes(word))) {
        points -= 1;
        hints.push(t('Do not use your name, email or site name.'));
    }

    if (/(.)\1{2,}/u.test(password)) {
        points -= 1;
        hints.push(t('Avoid repeated characters like "aaa".'));
    }

    if (hasSequence(password)) {
        points -= 1;
        hints.push(t('Avoid runs like "abcd" or "1234".'));
    }

    if (classes < 3 && password.length < LONG_PASSWORD) {
        hints.push(t('Add capitals or symbols, or make it longer.'));
    }

    const capped = meetsRules ? Math.max(1, points) : 0;
    const score = Math.min(4, Math.max(0, capped)) as PasswordStrength['score'];

    return { score, label: t(LABELS[score]), meetsRules, hints };
}
