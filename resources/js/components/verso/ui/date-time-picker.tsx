import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { CalendarIcon, ClockIcon } from '@/components/verso/icons';
import { useSiteDates } from '@/hooks/use-site-dates';
import {
    addDays,
    addMonths,
    dayKey,
    endOfWeek,
    formatCalendarDateTime,
    formatCalendarDay,
    formatWallTime,
    monthGrid,
    nextMonday,
    parseDay,
    parseTime,
    sameDay,
    startOfWeek,
    toDateTimeValue,
} from '@/lib/calendar';
import type { CalendarDay, WallTime } from '@/lib/calendar';
import { msg, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';

const DEFAULT_STEP = 5;
const DEFAULT_TIME: WallTime = { hour: 9, minute: 0 };
const WEEKDAY_SAMPLE: CalendarDay = { year: 2024, month: 1, day: 1 };
const HOURS_IN_DAY = 24;
const MINUTES_IN_HOUR = 60;
// Page Up and Page Down move a time field by this part of its range.
const PAGE_STEPS = 4;
const LABEL_FORMAT: Intl.DateTimeFormatOptions = {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
};
const PRESET_LABELS = {
    now: msg('Now'),
    tomorrowAt: msg('Tomorrow {time}'),
    nextMondayAt: msg('Next Monday {time}'),
    today: msg('Today'),
    tomorrow: msg('Tomorrow'),
    nextMonday: msg('Next Monday'),
};

type PickerProps = {
    value: string;
    onChange: (value: string) => void;
    min?: string;
    placeholder?: string;
    disabled?: boolean;
    id?: string;
    className?: string;
    'aria-invalid'?: boolean;
    'aria-describedby'?: string;
};

export type DateTimePickerProps = PickerProps & { step?: number };

export type DatePickerProps = PickerProps;

type Draft = { day: CalendarDay | null; time: WallTime };

type Preset = { label: string; day: CalendarDay; time: WallTime };

// Today and the current time on the site wall clock.
function siteNow(toLocalInput: (iso: string | null) => string): {
    day: CalendarDay;
    time: WallTime;
} {
    const value = toLocalInput(new Date().toISOString());

    return {
        day: parseDay(value) ?? { year: 1970, month: 1, day: 1 },
        time: parseTime(value) ?? DEFAULT_TIME,
    };
}

// Arrow keys that move one day sideways. In right to left text the next day is on the left.
const SIDEWAYS_KEYS: Record<string, number> = { ArrowLeft: -1, ArrowRight: 1 };

// True when the element shows right to left text, like Arabic.
function isRightToLeft(element: Element): boolean {
    return getComputedStyle(element).direction === 'rtl';
}

// Keeps a number inside a range, wrapping around at the ends.
function wrap(value: number, size: number): number {
    return ((value % size) + size) % size;
}

// Picks a date and a time in the site time zone. Sends "YYYY-MM-DDTHH:mm".
export function DateTimePicker({
    step = DEFAULT_STEP,
    ...props
}: DateTimePickerProps) {
    return <CalendarPicker {...props} step={step} withTime />;
}

// Picks only a day. Sends "YYYY-MM-DD".
export function DatePicker(props: DatePickerProps) {
    return <CalendarPicker {...props} step={DEFAULT_STEP} withTime={false} />;
}

// Shared trigger and panel for both pickers.
function CalendarPicker({
    value,
    onChange,
    min,
    placeholder,
    disabled = false,
    id,
    className,
    step,
    withTime,
    'aria-invalid': ariaInvalid,
    'aria-describedby': ariaDescribedBy,
}: PickerProps & { step: number; withTime: boolean }) {
    const { timeZone, toLocalInput } = useSiteDates();
    const [open, setOpen] = useState(false);
    const selectedDay = parseDay(value);
    const selectedTime = parseTime(value);

    const label = useMemo(() => {
        if (!selectedDay) {
            return '';
        }

        return withTime && selectedTime
            ? formatCalendarDateTime(selectedDay, selectedTime, LABEL_FORMAT)
            : formatCalendarDay(selectedDay, LABEL_FORMAT);
    }, [selectedDay, selectedTime, withTime]);

    const apply = (next: string) => {
        onChange(next);
        setOpen(false);
    };

    return (
        <div className={cn('relative', className)} data-slot="date-picker">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger
                    id={id}
                    role="combobox"
                    disabled={disabled}
                    aria-invalid={ariaInvalid}
                    aria-describedby={ariaDescribedBy}
                    className={cn(
                        'flex h-10 w-full min-w-0 items-center gap-2 rounded-md border border-input bg-card px-3 text-start text-base shadow-xs transition-[color,box-shadow] outline-none md:h-9 md:text-sm',
                        'focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
                        'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
                        'disabled:cursor-not-allowed disabled:opacity-50',
                        'data-[state=open]:border-ring data-[state=open]:ring-[3px] data-[state=open]:ring-ring/20',
                        selectedDay && !disabled && 'pe-9',
                    )}
                >
                    <CalendarIcon
                        size={16}
                        className="shrink-0 text-muted-foreground"
                    />
                    <span
                        className={cn(
                            'min-w-0 flex-1 truncate tabular-nums',
                            !label && 'text-muted-foreground',
                        )}
                    >
                        {label ||
                            placeholder ||
                            (withTime
                                ? t('Pick a date and time')
                                : t('Pick a date'))}
                    </span>
                </PopoverTrigger>
                <PopoverContent
                    sheetOnMobile
                    align="end"
                    aria-label={
                        withTime ? t('Choose date and time') : t('Choose date')
                    }
                    className="md:w-[21rem]"
                >
                    <PickerPanel
                        initialDay={selectedDay}
                        initialTime={selectedTime}
                        min={min}
                        step={step}
                        withTime={withTime}
                        timeZone={timeZone}
                        now={() => siteNow(toLocalInput)}
                        onCancel={() => setOpen(false)}
                        onApply={apply}
                    />
                </PopoverContent>
            </Popover>
            {selectedDay && !disabled && (
                <button
                    type="button"
                    onClick={() => onChange('')}
                    aria-label={
                        withTime ? t('Clear date and time') : t('Clear date')
                    }
                    className="absolute end-1 top-1/2 flex size-8 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground transition-colors outline-none hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring md:size-7"
                >
                    <X className="size-3.5" aria-hidden="true" />
                </button>
            )}
        </div>
    );
}

type PanelProps = {
    initialDay: CalendarDay | null;
    initialTime: WallTime | null;
    min?: string;
    step: number;
    withTime: boolean;
    timeZone: string;
    now: () => { day: CalendarDay; time: WallTime };
    onCancel: () => void;
    onApply: (value: string) => void;
};

// The calendar, time fields, presets and actions inside the popover.
function PickerPanel({
    initialDay,
    initialTime,
    min,
    step,
    withTime,
    timeZone,
    now,
    onCancel,
    onApply,
}: PanelProps) {
    const [today] = useState(() => now());
    const minDay = parseDay(min);
    const minKey = minDay ? dayKey(minDay) : null;
    const [draft, setDraft] = useState<Draft>({
        day: initialDay,
        time: initialTime ?? DEFAULT_TIME,
    });
    const [focusDay, setFocusDay] = useState<CalendarDay>(
        initialDay ??
            (minDay && dayKey(minDay) > dayKey(today.day) ? minDay : today.day),
    );

    const isDisabled = useCallback(
        (day: CalendarDay) => minKey !== null && dayKey(day) < minKey,
        [minKey],
    );

    const commit = (draftValue: Draft) => {
        if (!draftValue.day) {
            return;
        }

        onApply(
            withTime
                ? toDateTimeValue(draftValue.day, draftValue.time)
                : dayKey(draftValue.day),
        );
    };

    const chooseDay = (day: CalendarDay) => {
        if (isDisabled(day)) {
            return;
        }

        const next = { ...draft, day };
        setDraft(next);
        setFocusDay(day);

        if (!withTime) {
            commit(next);
        }
    };

    const presets = useMemo<Preset[]>(() => {
        const nineAm = DEFAULT_TIME;
        const nineAmText = formatWallTime(nineAm);
        const list: Preset[] = withTime
            ? [
                  {
                      label: t(PRESET_LABELS.now),
                      day: today.day,
                      time: today.time,
                  },
                  {
                      label: t(PRESET_LABELS.tomorrowAt, { time: nineAmText }),
                      day: addDays(today.day, 1),
                      time: nineAm,
                  },
                  {
                      label: t(PRESET_LABELS.nextMondayAt, {
                          time: nineAmText,
                      }),
                      day: nextMonday(today.day),
                      time: nineAm,
                  },
              ]
            : [
                  {
                      label: t(PRESET_LABELS.today),
                      day: today.day,
                      time: nineAm,
                  },
                  {
                      label: t(PRESET_LABELS.tomorrow),
                      day: addDays(today.day, 1),
                      time: nineAm,
                  },
                  {
                      label: t(PRESET_LABELS.nextMonday),
                      day: nextMonday(today.day),
                      time: nineAm,
                  },
              ];

        return list;
    }, [today, withTime]);

    const choosePreset = (preset: Preset) => {
        const next = { day: preset.day, time: preset.time };
        setDraft(next);
        setFocusDay(preset.day);

        if (!withTime) {
            commit(next);
        }
    };

    return (
        <div className="flex flex-col">
            <div
                aria-hidden="true"
                className="mx-auto mt-2 h-1 w-10 rounded-full bg-border md:hidden"
            />
            <div className="p-3 pb-2">
                <MonthCalendar
                    focusDay={focusDay}
                    selected={draft.day}
                    today={today.day}
                    isDisabled={isDisabled}
                    onFocusDay={setFocusDay}
                    onChoose={chooseDay}
                />
            </div>

            {withTime && (
                <div className="flex items-center justify-between gap-3 border-t px-3 py-2.5">
                    <span className="flex items-center gap-2 text-sm text-muted-foreground">
                        <ClockIcon size={16} />
                        {t('Time')}
                    </span>
                    <TimeFields
                        time={draft.time}
                        step={step}
                        onChange={(time) =>
                            setDraft((current) => ({ ...current, time }))
                        }
                    />
                </div>
            )}

            <div className="flex flex-wrap gap-1.5 border-t px-3 py-2.5">
                {presets.map((preset) => (
                    <button
                        key={preset.label}
                        type="button"
                        disabled={isDisabled(preset.day)}
                        onClick={() => choosePreset(preset)}
                        className="h-10 rounded-md border bg-card px-2.5 text-xs font-medium text-foreground tabular-nums transition-[background-color,scale] duration-150 ease-out outline-none hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-40 motion-safe:enabled:active:scale-[0.96] md:h-7"
                    >
                        {preset.label}
                    </button>
                ))}
            </div>

            <div className="flex items-center justify-between gap-3 border-t px-3 py-2.5">
                <span
                    className="min-w-0 truncate text-xs text-muted-foreground"
                    title={t('Site time zone')}
                >
                    {timeZone}
                </span>
                <div className="flex shrink-0 gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={onCancel}
                    >
                        {t('Cancel')}
                    </Button>
                    {withTime && (
                        <Button
                            type="button"
                            size="sm"
                            disabled={!draft.day || isDisabled(draft.day)}
                            onClick={() => commit(draft)}
                        >
                            {t('Apply')}
                        </Button>
                    )}
                </div>
            </div>
        </div>
    );
}

type MonthCalendarProps = {
    focusDay: CalendarDay;
    selected: CalendarDay | null;
    today: CalendarDay;
    isDisabled: (day: CalendarDay) => boolean;
    onFocusDay: (day: CalendarDay) => void;
    onChoose: (day: CalendarDay) => void;
};

// Month grid with Monday first and full keyboard support.
function MonthCalendar({
    focusDay,
    selected,
    today,
    isDisabled,
    onFocusDay,
    onChoose,
}: MonthCalendarProps) {
    const gridRef = useRef<HTMLDivElement | null>(null);
    const moveFocus = useRef(false);
    const weeks = useMemo(
        () => monthGrid(focusDay.year, focusDay.month),
        [focusDay.year, focusDay.month],
    );
    const title = formatCalendarDay(focusDay, {
        month: 'long',
        year: 'numeric',
    });
    const weekdays = useMemo(
        () =>
            Array.from({ length: 7 }, (_, index) => {
                const day = addDays(WEEKDAY_SAMPLE, index);

                return {
                    short: formatCalendarDay(day, { weekday: 'short' }),
                    long: formatCalendarDay(day, { weekday: 'long' }),
                };
            }),
        [],
    );

    useEffect(() => {
        if (!moveFocus.current) {
            return;
        }

        moveFocus.current = false;
        gridRef.current
            ?.querySelector<HTMLButtonElement>(
                `[data-day="${dayKey(focusDay)}"]`,
            )
            ?.focus();
    }, [focusDay]);

    // Moves the focused day with arrows, page keys, Home and End.
    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const sideways =
            (SIDEWAYS_KEYS[event.key] ?? 0) *
            (isRightToLeft(event.currentTarget) ? -1 : 1);
        const moves: Record<string, () => CalendarDay> = {
            ArrowLeft: () => addDays(focusDay, sideways),
            ArrowRight: () => addDays(focusDay, sideways),
            ArrowUp: () => addDays(focusDay, -7),
            ArrowDown: () => addDays(focusDay, 7),
            PageUp: () => addMonths(focusDay, event.shiftKey ? -12 : -1),
            PageDown: () => addMonths(focusDay, event.shiftKey ? 12 : 1),
            Home: () => startOfWeek(focusDay),
            End: () => endOfWeek(focusDay),
        };

        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            onChoose(focusDay);

            return;
        }

        const move = moves[event.key];

        if (!move) {
            return;
        }

        event.preventDefault();
        moveFocus.current = true;
        onFocusDay(move());
    };

    const navButton = (label: string, amount: number, icon: ReactNode) => (
        <button
            type="button"
            aria-label={label}
            onClick={() => onFocusDay(addMonths(focusDay, amount))}
            className="flex size-10 items-center justify-center rounded-md text-muted-foreground transition-colors outline-none hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring md:size-7 [&_svg]:size-4"
        >
            {icon}
        </button>
    );

    return (
        <div>
            <div className="mb-2 flex items-center justify-between gap-1">
                <div className="flex">
                    {navButton(
                        t('Previous year'),
                        -12,
                        <ChevronsLeft aria-hidden="true" />,
                    )}
                    {navButton(
                        t('Previous month'),
                        -1,
                        <ChevronLeft aria-hidden="true" />,
                    )}
                </div>
                <h2
                    aria-live="polite"
                    className="text-sm font-semibold text-foreground tabular-nums"
                >
                    {title}
                </h2>
                <div className="flex">
                    {navButton(
                        t('Next month'),
                        1,
                        <ChevronRight aria-hidden="true" />,
                    )}
                    {navButton(
                        t('Next year'),
                        12,
                        <ChevronsRight aria-hidden="true" />,
                    )}
                </div>
            </div>

            <div
                ref={gridRef}
                role="grid"
                aria-label={title}
                onKeyDown={onKeyDown}
                className="w-full"
            >
                <div role="row" className="grid grid-cols-7">
                    {weekdays.map((weekday) => (
                        <span
                            key={weekday.long}
                            role="columnheader"
                            aria-label={weekday.long}
                            className="flex h-8 items-center justify-center text-xs font-medium text-muted-foreground"
                        >
                            {weekday.short}
                        </span>
                    ))}
                </div>
                {weeks.map((week) => (
                    <div
                        role="row"
                        key={dayKey(week[0])}
                        className="grid grid-cols-7 gap-y-0.5"
                    >
                        {week.map((day) => (
                            <DayCell
                                key={dayKey(day)}
                                day={day}
                                outside={day.month !== focusDay.month}
                                focused={sameDay(day, focusDay)}
                                selected={sameDay(day, selected)}
                                today={sameDay(day, today)}
                                disabled={isDisabled(day)}
                                onChoose={onChoose}
                            />
                        ))}
                    </div>
                ))}
            </div>
        </div>
    );
}

type DayCellProps = {
    day: CalendarDay;
    outside: boolean;
    focused: boolean;
    selected: boolean;
    today: boolean;
    disabled: boolean;
    onChoose: (day: CalendarDay) => void;
};

// One day button inside the month grid.
function DayCell({
    day,
    outside,
    focused,
    selected,
    today,
    disabled,
    onChoose,
}: DayCellProps) {
    return (
        <div
            role="gridcell"
            aria-selected={selected}
            className="flex justify-center"
        >
            <button
                type="button"
                data-day={dayKey(day)}
                tabIndex={focused ? 0 : -1}
                data-autofocus={focused || undefined}
                aria-label={formatCalendarDay(day, {
                    weekday: 'long',
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric',
                })}
                aria-current={today ? 'date' : undefined}
                aria-disabled={disabled || undefined}
                onClick={() => onChoose(day)}
                className={cn(
                    'flex h-10 w-full items-center justify-center rounded-md text-sm tabular-nums transition-[background-color,color] duration-100 outline-none md:h-9',
                    'focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-1 focus-visible:ring-offset-popover',
                    outside ? 'text-muted-foreground/60' : 'text-foreground',
                    !selected && !disabled && 'hover:bg-accent',
                    today &&
                        !selected &&
                        'font-semibold ring-1 ring-foreground/20 ring-inset',
                    selected &&
                        'bg-primary font-semibold text-primary-foreground',
                    disabled &&
                        'cursor-not-allowed text-muted-foreground/35 line-through decoration-muted-foreground/30',
                )}
            >
                {day.day}
            </button>
        </div>
    );
}

type TimeFieldsProps = {
    time: WallTime;
    step: number;
    onChange: (time: WallTime) => void;
};

// Hour and minute fields in 24 hour format. Hours stay first in right to left text too.
function TimeFields({ time, step, onChange }: TimeFieldsProps) {
    return (
        <div
            dir="ltr"
            className="flex items-center gap-1"
            role="group"
            aria-label={t('Time')}
        >
            <TimeSegment
                label={t('Hours')}
                value={time.hour}
                size={HOURS_IN_DAY}
                step={1}
                onChange={(hour) => onChange({ ...time, hour })}
            />
            <span
                aria-hidden="true"
                className="text-sm font-medium text-muted-foreground"
            >
                :
            </span>
            <TimeSegment
                label={t('Minutes')}
                value={time.minute}
                size={MINUTES_IN_HOUR}
                step={step}
                onChange={(minute) => onChange({ ...time, minute })}
            />
        </div>
    );
}

type TimeSegmentProps = {
    label: string;
    value: number;
    size: number;
    step: number;
    onChange: (value: number) => void;
};

// A two digit spin field. Arrow keys move by the step and wrap around,
// Page keys move by a bigger step, Home and End go to the first and last value.
function TimeSegment({ label, value, size, step, onChange }: TimeSegmentProps) {
    const [text, setText] = useState<string | null>(null);
    const bigStep = Math.max(step, size / PAGE_STEPS);
    const shown = text ?? String(value).padStart(2, '0');

    const stepBy = (direction: 1 | -1) => {
        const snapped =
            direction === 1
                ? Math.floor(value / step) * step + step
                : Math.ceil(value / step) * step - step;
        setText(null);
        onChange(wrap(snapped, size));
    };

    const jumpTo = (next: number) => {
        setText(null);
        onChange(next);
    };

    const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        const jumps: Record<string, number> = {
            Home: 0,
            End: size - 1,
            PageUp: wrap(value + bigStep, size),
            PageDown: wrap(value - bigStep, size),
        };

        if (event.key === 'ArrowUp' || event.key === 'ArrowDown') {
            event.preventDefault();
            stepBy(event.key === 'ArrowUp' ? 1 : -1);
        } else if (event.key in jumps) {
            event.preventDefault();
            jumpTo(jumps[event.key]);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            event.currentTarget.blur();
        }
    };

    return (
        <input
            type="text"
            inputMode="numeric"
            role="spinbutton"
            aria-label={label}
            aria-valuemin={0}
            aria-valuemax={size - 1}
            aria-valuenow={value}
            aria-valuetext={String(value).padStart(2, '0')}
            maxLength={2}
            autoComplete="off"
            enterKeyHint="done"
            value={shown}
            onFocus={(event) => event.currentTarget.select()}
            onChange={(event) => {
                const digits = event.target.value
                    .replace(/\D/g, '')
                    .slice(0, 2);
                setText(digits);

                if (digits !== '') {
                    onChange(Math.min(Number(digits), size - 1));
                }
            }}
            onBlur={() => setText(null)}
            onKeyDown={onKeyDown}
            className="h-10 w-12 rounded-md border border-input bg-card text-center text-base font-medium tabular-nums shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 md:h-8 md:w-10 md:text-sm"
        />
    );
}
