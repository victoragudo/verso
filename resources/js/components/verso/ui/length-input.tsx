import { useEffect, useId, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { SearchableSelect } from '@/components/verso/ui/searchable-select';
import type { SearchableOption } from '@/lib/option-search';
import {
    ANGLE_UNITS,
    COMMON_UNITS,
    cssValueRule,
    LENGTH_UNIT_GROUPS,
    normalizeCssValue,
    onlyUnits,
    splitCssValue,
    stepNumber,
    TIME_UNITS,
} from '@/lib/css-value';
import type { CssValueRule } from '@/lib/css-value';
import { msg, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

// Picker values that are not units.
const NO_UNIT = ':none';
const CUSTOM = ':custom';
const KEYWORD_PREFIX = 'kw:';
const CUSTOM_FUNCTIONS = ['calc()', 'clamp()', 'var()'];

// A design token like "var:spacing.md" (only where the caller allows tokens).
const TOKEN = /^var:[a-z][a-z0-9]*\.[a-z0-9][a-z0-9-]{0,40}$/;

// A number people type, maybe not finished yet, like "-", "1." or ".5".
const TYPED_NUMBER = /^[+-]?(\d+\.?\d*|\.\d+)$/;

// Plain names of the units, shown in the list and used by the search.
const UNIT_NAMES: Record<string, string> = {
    px: msg('pixels'),
    cm: msg('centimeters'),
    mm: msg('millimeters'),
    Q: msg('quarter millimeters'),
    in: msg('inches'),
    pc: msg('picas'),
    pt: msg('points'),
    em: msg('text size of the block'),
    rem: msg('text size of the page'),
    ex: msg('height of x'),
    rex: msg('height of x on the page'),
    cap: msg('height of capitals'),
    rcap: msg('height of capitals on the page'),
    ch: msg('width of 0'),
    rch: msg('width of 0 on the page'),
    ic: msg('width of a CJK sign'),
    ric: msg('width of a CJK sign on the page'),
    lh: msg('line height'),
    rlh: msg('line height of the page'),
    vw: msg('screen width'),
    vh: msg('screen height'),
    vi: msg('screen inline size'),
    vb: msg('screen block size'),
    vmin: msg('smaller screen side'),
    vmax: msg('bigger screen side'),
    cqw: msg('container width'),
    cqh: msg('container height'),
    cqi: msg('container inline size'),
    cqb: msg('container block size'),
    cqmin: msg('smaller container side'),
    cqmax: msg('bigger container side'),
    '%': msg('percent'),
    deg: msg('degrees'),
    rad: msg('radians'),
    grad: msg('gradians'),
    turn: msg('turns'),
    s: msg('seconds'),
    ms: msg('milliseconds'),
};

const VIEWPORT_PREFIX: Record<string, string> = {
    sv: msg('small'),
    lv: msg('large'),
    dv: msg('dynamic'),
};

const GROUP_LABELS: Record<string, string> = {
    absolute: msg('Absolute'),
    font: msg('Text relative'),
    viewport: msg('Screen'),
    container: msg('Container'),
    percent: msg('Percent'),
};

// Plain name of a unit, like "screen height, dynamic" for dvh.
export function unitName(unit: string): string {
    const prefix = VIEWPORT_PREFIX[unit.slice(0, 2)];
    const viewportName = UNIT_NAMES[`v${unit.slice(2)}`];

    if (prefix && viewportName) {
        return t('{unit}, {variant}', {
            unit: t(viewportName),
            variant: t(prefix),
        });
    }

    return UNIT_NAMES[unit] ? t(UNIT_NAMES[unit]) : unit;
}

// The choices of the unit picker: common units first, then every other unit, keywords, no unit and custom.
export function unitOptions(rule: CssValueRule): SearchableOption[] {
    const option = (unit: string, group: string): SearchableOption => ({
        value: unit,
        label: unit,
        description: unitName(unit),
        group,
        keywords: [unitName(unit)],
    });
    const common = COMMON_UNITS.filter((unit) => rule.units.includes(unit));
    const groups: [string, string[]][] =
        rule.type === 'angle'
            ? [[t('Angle'), ANGLE_UNITS]]
            : rule.type === 'time'
              ? [[t('Time'), TIME_UNITS]]
              : rule.type === 'number'
                ? []
                : Object.entries(LENGTH_UNIT_GROUPS).map(([name, units]) => [
                      GROUP_LABELS[name] ? t(GROUP_LABELS[name]) : name,
                      units,
                  ]);

    return [
        ...common.map((unit) => option(unit, t('Common'))),
        ...groups.flatMap(([group, units]) =>
            units
                .filter(
                    (unit) =>
                        rule.units.includes(unit) && !common.includes(unit),
                )
                .map((unit) => option(unit, group)),
        ),
        ...rule.keywords.map((keyword) => ({
            value: KEYWORD_PREFIX + keyword,
            label: keyword,
            description: t('keyword'),
            group: t('Keywords'),
        })),
        ...(rule.unitless
            ? [
                  {
                      value: NO_UNIT,
                      label: t('No unit'),
                      description: t('plain number'),
                      group: t('Other'),
                      keywords: ['number', 'unitless'],
                  },
              ]
            : []),
        ...(rule.custom
            ? [
                  {
                      value: CUSTOM,
                      label: t('Custom'),
                      description: CUSTOM_FUNCTIONS.join(', '),
                      group: t('Other'),
                      keywords: ['calc', 'clamp', 'min', 'max', 'var'],
                  },
              ]
            : []),
    ];
}

// The unit picked when the field is empty.
function defaultPick(rule: CssValueRule): string {
    if (rule.unitless) {
        return NO_UNIT;
    }

    return (
        COMMON_UNITS.find((unit) => rule.units.includes(unit)) ??
        rule.units[0] ??
        CUSTOM
    );
}

type State = { pick: string; text: string };

// What the field shows for a stored value.
function stateFor(value: unknown, rule: CssValueRule, tokens: boolean): State {
    if (tokens && typeof value === 'string' && TOKEN.test(value)) {
        return { pick: CUSTOM, text: value };
    }

    const parts = splitCssValue(value, rule);

    switch (parts.mode) {
        case 'number':
            return {
                pick:
                    parts.unit !== ''
                        ? parts.unit
                        : rule.unitless
                          ? NO_UNIT
                          : defaultPick(rule),
                text: parts.number,
            };
        case 'keyword':
            return {
                pick: KEYWORD_PREFIX + parts.keyword,
                text: parts.keyword,
            };
        case 'custom':
            return { pick: CUSTOM, text: parts.text };
        default:
            return { pick: defaultPick(rule), text: '' };
    }
}

// The value to save for what is typed with the picked unit: a clean CSS value, '' for empty, or null when not allowed.
export function composeLength(
    text: string,
    pick: string,
    rule: CssValueRule,
    tokens = false,
): string | null {
    const typed = text.trim();

    if (typed === '') {
        return '';
    }

    if (tokens && TOKEN.test(typed)) {
        return typed;
    }

    const isUnit =
        pick === NO_UNIT ||
        (!pick.startsWith(':') && !pick.startsWith(KEYWORD_PREFIX));
    const candidate =
        isUnit && TYPED_NUMBER.test(typed)
            ? typed + (pick === NO_UNIT ? '' : pick)
            : typed;

    return normalizeCssValue(candidate, rule);
}

function hint(rule: CssValueRule): string {
    return t(
        'Use {type, select, angle {an angle like 45deg} time {a time like 200ms} number {a number like 1.5} other {a size like 16px, 1.5rem or 50%}}{hasKeywords, select, yes {, or {keywords}} other {}}{custom, select, yes {, or calc(), clamp(), min(), max() or var(--name)} other {}}.',
        {
            type: rule.type,
            hasKeywords: rule.keywords.length > 0 ? 'yes' : 'no',
            keywords: rule.keywords.join(', '),
            custom: rule.custom ? 'yes' : 'no',
        },
    );
}

export type LengthInputProps = {
    id?: string;
    // Stored value: a CSS value like "1.5rem", an old plain number, or undefined for none.
    value: unknown;
    // Called with a clean CSS value, or undefined when the field is cleared.
    onChange: (value: string | undefined) => void;
    // Units, keywords and numbers the property accepts. Every length unit by default.
    rule?: Partial<CssValueRule> | CssValueRule | null;
    // Units the theme allows, null for every unit of the rule.
    units?: string[] | null;
    // Also accept design tokens like "var:spacing.md".
    tokens?: boolean;
    // Values offered while typing, like the theme spacing scale.
    suggestions?: string[];
    // Name of the field, used to name the unit picker for screen readers.
    label: string;
    placeholder?: string;
    size?: 'sm' | 'default';
    disabled?: boolean;
    invalid?: boolean;
    'aria-describedby'?: string;
    className?: string;
};

// A number with a unit picker for any CSS size: every unit, keywords, plain numbers and custom values.
// Arrow keys change the number (Shift for ten steps). Errors show when the field is left, not while typing.
export function LengthInput({
    id,
    value,
    onChange,
    rule: ruleData,
    units = null,
    tokens = false,
    suggestions = [],
    label,
    placeholder,
    size = 'default',
    disabled = false,
    invalid = false,
    'aria-describedby': describedBy,
    className,
}: LengthInputProps) {
    const baseId = useId();
    const inputId = id ?? `${baseId}-value`;
    const errorId = `${baseId}-error`;
    const listId = `${baseId}-list`;
    const inputRef = useRef<HTMLInputElement | null>(null);
    const rule = useMemo(() => {
        const full = cssValueRule(ruleData ?? {});

        return units ? onlyUnits(full, units) : full;
    }, [ruleData, units]);
    const options = useMemo(() => unitOptions(rule), [rule]);
    const [state, setState] = useState<State>(() =>
        stateFor(value, rule, tokens),
    );
    const [error, setError] = useState(false);
    const sent = useRef<unknown>(value);

    useEffect(() => {
        if (value !== sent.current) {
            sent.current = value;
            setState(stateFor(value, rule, tokens));
            setError(false);
        }
    }, [value, rule, tokens]);

    const emit = (css: string | null) => {
        if (css === null) {
            return;
        }

        const next = css === '' ? undefined : css;
        sent.current = next;
        onChange(next);
    };

    const change = (next: State) => {
        setState(next);
        setError(false);
        emit(composeLength(next.text, next.pick, rule, tokens));
    };

    const pickUnit = (pick: string) => {
        if (pick.startsWith(KEYWORD_PREFIX)) {
            change({ pick, text: pick.slice(KEYWORD_PREFIX.length) });

            return;
        }

        if (pick === CUSTOM) {
            const current = composeLength(state.text, state.pick, rule, tokens);
            change({ pick, text: current || state.text });
        } else {
            const number = /[+-]?(\d+\.?\d*|\.\d+)/.exec(state.text)?.[0] ?? '';
            change({ pick, text: number });
        }

        requestAnimationFrame(() => inputRef.current?.focus());
    };

    const isNumberPick =
        state.pick === NO_UNIT ||
        (!state.pick.startsWith(':') && !state.pick.startsWith(KEYWORD_PREFIX));

    // Shows the clean value when the field is left, or the error when it is not allowed.
    const commit = () => {
        const css = composeLength(state.text, state.pick, rule, tokens);

        if (css === null) {
            setError(true);

            return;
        }

        if (css !== '' && !(tokens && TOKEN.test(css))) {
            setState(stateFor(css, rule, tokens));
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Escape') {
            setState(stateFor(sent.current, rule, tokens));
            setError(false);

            return;
        }

        if (event.key === 'Enter') {
            commit();

            return;
        }

        if (event.key !== 'ArrowUp' && event.key !== 'ArrowDown') {
            return;
        }

        const typed = state.text.trim();

        if (!isNumberPick || (typed !== '' && !TYPED_NUMBER.test(typed))) {
            return;
        }

        event.preventDefault();
        const unit = state.pick === NO_UNIT ? '' : state.pick;
        let number = stepNumber(
            typed,
            unit,
            event.key === 'ArrowUp' ? 1 : -1,
            event.shiftKey,
        );

        if (!rule.negative && Number(number) < 0) {
            number = '0';
        }

        change({ ...state, text: number });
    };

    const showError = error || invalid;
    const custom = state.pick === CUSTOM;
    const height = size === 'sm' ? 'h-8 md:h-8' : undefined;

    return (
        <div
            className={cn('grid min-w-0 gap-1', className)}
            data-slot="length-input"
        >
            <div className="flex min-w-0 gap-1">
                <input
                    ref={inputRef}
                    id={inputId}
                    type="text"
                    inputMode={custom ? 'text' : 'decimal'}
                    autoComplete="off"
                    autoCorrect="off"
                    autoCapitalize="none"
                    spellCheck={false}
                    enterKeyHint="done"
                    disabled={disabled}
                    value={state.text}
                    placeholder={placeholder}
                    list={suggestions.length > 0 ? listId : undefined}
                    aria-invalid={showError || undefined}
                    aria-describedby={
                        [describedBy, showError ? errorId : '']
                            .filter(Boolean)
                            .join(' ') || undefined
                    }
                    onChange={(event) =>
                        change({ ...state, text: event.target.value })
                    }
                    onBlur={commit}
                    onKeyDown={onKeyDown}
                    className={cn(
                        'flex h-10 w-full min-w-0 flex-1 rounded-md border border-input bg-card px-2.5 py-1 text-base tabular-nums shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 md:h-9 md:text-sm dark:bg-input/30 dark:aria-invalid:ring-destructive/40',
                        custom && 'font-mono text-xs md:text-xs',
                        height,
                    )}
                />
                <SearchableSelect
                    value={state.pick}
                    onValueChange={pickUnit}
                    options={options}
                    disabled={disabled}
                    aria-label={t('{field} unit', { field: label })}
                    searchPlaceholder={t('Search units')}
                    emptyText={t('No unit found.')}
                    showSelectedDescription={false}
                    className="w-auto shrink-0"
                    triggerClassName={cn(
                        'min-w-[4.25rem] gap-1 px-2 font-mono text-xs',
                        height,
                    )}
                />
            </div>
            {suggestions.length > 0 && (
                <datalist id={listId}>
                    {suggestions.map((suggestion) => (
                        <option key={suggestion} value={suggestion} />
                    ))}
                </datalist>
            )}
            <p
                id={errorId}
                aria-live="polite"
                className={cn(
                    'text-xs text-destructive',
                    !showError && 'sr-only',
                )}
            >
                {showError ? hint(rule) : ''}
            </p>
        </div>
    );
}
