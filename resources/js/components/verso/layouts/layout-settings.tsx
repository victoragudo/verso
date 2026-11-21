import { useId } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { OptionSelect } from '@/components/verso/ui/option-select';
import { msg, t } from '@/lib/i18n';
import { plainText } from '@/lib/utils';
import type { LayoutKindName, LayoutSettingsValue } from './types';

type Field =
    | { name: string; label: string; type: 'toggle' }
    | {
          name: string;
          label: string;
          type: 'number';
          min: number;
          max: number;
          help?: string;
          shows?: (value: LayoutSettingsValue) => boolean;
      }
    | {
          name: string;
          label: string;
          type: 'text';
          max: number;
          help?: string;
          shows?: (value: LayoutSettingsValue) => boolean;
      }
    | {
          name: string;
          label: string;
          type: 'select';
          options: [string, string][];
          shows?: (value: LayoutSettingsValue) => boolean;
      }
    | { name: string; label: string; type: 'devices' };

// The options of each kind of layout. Same names and limits as App\Verso\Layouts\LayoutSettings.
export const LAYOUT_SETTING_FIELDS: Record<LayoutKindName, Field[]> = {
    template: [
        {
            name: 'width',
            label: msg('Width of the content'),
            type: 'select',
            options: [
                ['full', msg('Full screen width')],
                ['wide', msg('Page width of the theme')],
                ['content', msg('Text column width')],
            ],
        },
    ],
    header: [
        {
            name: 'sticky',
            label: msg('Stay at the top while scrolling'),
            type: 'toggle',
        },
        {
            name: 'transparent',
            label: msg('See through, on top of the first section'),
            type: 'toggle',
        },
        {
            name: 'scrollEffect',
            label: msg('When people scroll down'),
            type: 'select',
            options: [
                ['none', msg('Nothing changes')],
                ['solid', msg('Get a background and a shadow')],
                ['shrink', msg('Get smaller')],
                ['hide', msg('Hide, and come back when scrolling up')],
            ],
        },
        {
            name: 'scrollOffset',
            label: msg('After how many pixels'),
            type: 'number',
            min: 0,
            max: 2000,
            shows: (value) => value.scrollEffect !== 'none',
        },
    ],
    footer: [
        {
            name: 'sticky',
            label: msg('Stay at the bottom of the screen'),
            type: 'toggle',
        },
    ],
    popup: [
        {
            name: 'type',
            label: msg('Kind'),
            type: 'select',
            options: [
                ['modal', msg('Popup in the middle (blocks the page)')],
                ['slide-in', msg('Slide-in box in a corner')],
                ['top-bar', msg('Bar at the top of the page')],
                ['cookie', msg('Cookie notice')],
            ],
        },
        {
            name: 'title',
            label: msg('Title'),
            type: 'text',
            max: 120,
            help: msg(
                'Screen readers read it. Empty uses the name of the popup.',
            ),
        },
        {
            name: 'position',
            label: msg('Place'),
            type: 'select',
            options: [
                ['bottom-end', msg('Bottom corner, end side')],
                ['bottom-start', msg('Bottom corner, start side')],
                ['bottom', msg('Bottom, in the middle')],
                ['top', msg('Top, in the middle')],
            ],
            shows: (value) =>
                value.type === 'slide-in' || value.type === 'cookie',
        },
        {
            name: 'size',
            label: msg('Width'),
            type: 'select',
            options: [
                ['small', msg('Small')],
                ['medium', msg('Medium')],
                ['large', msg('Large')],
            ],
            shows: (value) =>
                value.type === 'modal' || value.type === 'slide-in',
        },
        {
            name: 'trigger',
            label: msg('When it shows'),
            type: 'select',
            options: [
                ['load', msg('Right away')],
                ['delay', msg('After some seconds')],
                ['scroll', msg('After scrolling part of the page')],
                ['exit', msg('When the mouse leaves the page')],
                [
                    'click',
                    msg(
                        'Only on a click (popup button or a link to #verso-popup-ID)',
                    ),
                ],
            ],
            shows: (value) =>
                value.type === 'modal' || value.type === 'slide-in',
        },
        {
            name: 'delay',
            label: msg('Seconds'),
            type: 'number',
            min: 0,
            max: 600,
            shows: (value) =>
                value.trigger === 'delay' &&
                value.type !== 'cookie' &&
                value.type !== 'top-bar',
        },
        {
            name: 'scroll',
            label: msg('Percent of the page'),
            type: 'number',
            min: 1,
            max: 100,
            shows: (value) =>
                value.trigger === 'scroll' &&
                value.type !== 'cookie' &&
                value.type !== 'top-bar',
        },
        {
            name: 'frequency',
            label: msg('How often'),
            type: 'select',
            options: [
                ['always', msg('On every page')],
                ['session', msg('Once per visit')],
                ['days', msg('Once every few days')],
                ['dismissed', msg('Until the visitor closes it')],
            ],
            shows: (value) => value.type !== 'cookie',
        },
        {
            name: 'days',
            label: msg('Days'),
            type: 'number',
            min: 1,
            max: 365,
            shows: (value) =>
                value.frequency === 'days' && value.type !== 'cookie',
        },
        { name: 'devices', label: msg('Screens'), type: 'devices' },
        {
            name: 'acceptLabel',
            label: msg('Accept button text'),
            type: 'text',
            max: 60,
            shows: (value) => value.type === 'cookie',
        },
        {
            name: 'dismissLabel',
            label: msg('Decline button text'),
            type: 'text',
            max: 60,
            shows: (value) => value.type === 'cookie',
        },
    ],
};

const DEVICES: [string, string][] = [
    ['computer', msg('Computer')],
    ['tablet', msg('Tablet')],
    ['phone', msg('Phone')],
];

type Props = {
    kind: LayoutKindName;
    value: LayoutSettingsValue;
    onChange: (value: LayoutSettingsValue) => void;
};

// The options of one layout, like sticky for a header or the trigger of a popup.
export function LayoutSettingsFields({ kind, value, onChange }: Props) {
    const id = useId();
    const set = (name: string, next: unknown) =>
        onChange({ ...value, [name]: next });

    return (
        <div className="grid gap-3" data-test="layout-settings">
            {LAYOUT_SETTING_FIELDS[kind].map((field) => {
                const fieldId = `${id}-${field.name}`;

                if ('shows' in field && field.shows && !field.shows(value)) {
                    return null;
                }

                if (field.type === 'toggle') {
                    return (
                        <div
                            key={field.name}
                            className="flex items-center gap-2"
                        >
                            <Checkbox
                                id={fieldId}
                                checked={value[field.name] === true}
                                onCheckedChange={(checked) =>
                                    set(field.name, checked === true)
                                }
                            />
                            <Label
                                htmlFor={fieldId}
                                className="text-sm font-normal"
                            >
                                {t(field.label)}
                            </Label>
                        </div>
                    );
                }

                if (field.type === 'devices') {
                    const chosen = Array.isArray(value.devices)
                        ? (value.devices as string[])
                        : [];

                    return (
                        <fieldset key={field.name} className="grid gap-1.5">
                            <legend className="mb-1 text-xs font-medium">
                                {t(field.label)}
                            </legend>
                            {DEVICES.map(([device, label]) => (
                                <div
                                    key={device}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={`${fieldId}-${device}`}
                                        checked={chosen.includes(device)}
                                        onCheckedChange={(checked) =>
                                            set(
                                                'devices',
                                                checked === true
                                                    ? [...chosen, device]
                                                    : chosen.filter(
                                                          (item) =>
                                                              item !== device,
                                                      ),
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor={`${fieldId}-${device}`}
                                        className="text-sm font-normal"
                                    >
                                        {t(label)}
                                    </Label>
                                </div>
                            ))}
                        </fieldset>
                    );
                }

                return (
                    <div key={field.name} className="grid gap-1">
                        <Label htmlFor={fieldId} className="text-xs">
                            {t(field.label)}
                        </Label>
                        {field.type === 'select' ? (
                            <OptionSelect
                                id={fieldId}
                                value={plainText(value[field.name])}
                                onValueChange={(option) =>
                                    set(field.name, option)
                                }
                                options={field.options.map(
                                    ([option, label]) => ({
                                        value: option,
                                        label: t(label),
                                    }),
                                )}
                            />
                        ) : (
                            <Input
                                id={fieldId}
                                type={
                                    field.type === 'number' ? 'number' : 'text'
                                }
                                min={
                                    field.type === 'number'
                                        ? field.min
                                        : undefined
                                }
                                max={
                                    field.type === 'number'
                                        ? field.max
                                        : undefined
                                }
                                maxLength={
                                    field.type === 'text'
                                        ? field.max
                                        : undefined
                                }
                                value={plainText(value[field.name])}
                                onChange={(event) =>
                                    set(
                                        field.name,
                                        field.type === 'number'
                                            ? Number(event.target.value)
                                            : event.target.value,
                                    )
                                }
                                className="h-9"
                            />
                        )}
                        {'help' in field && field.help && (
                            <p className="text-xs text-muted-foreground">
                                {t(field.help)}
                            </p>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
