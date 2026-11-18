import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { msg, t } from '@/lib/i18n';
import type { BlockFieldProps } from './shared';
import { boolAttr, stringAttr, withAttrs } from './shared';

const BUTTON_STYLES = [
    { value: 'primary', label: msg('Primary') },
    { value: 'secondary', label: msg('Secondary') },
    { value: 'outline', label: msg('Outline') },
];

// Source code typed as plain text, with a language name.
export function CodeBlockFields({ block, onChange }: BlockFieldProps) {
    return (
        <div className="grid gap-2">
            <Input
                aria-label={t('Code language')}
                placeholder={t('Language, for example php or bash')}
                value={stringAttr(block, 'language')}
                onChange={(event) =>
                    onChange(withAttrs(block, { language: event.target.value }))
                }
                className="h-8 w-56 font-mono text-xs"
            />
            <Textarea
                aria-label={t('Code')}
                spellCheck={false}
                value={block.content ?? ''}
                onChange={(event) =>
                    onChange({ ...block, content: event.target.value })
                }
                rows={Math.max(3, (block.content ?? '').split('\n').length)}
                className="bg-zinc-50 font-mono text-sm dark:bg-zinc-900"
            />
        </div>
    );
}

// Raw HTML. The server only saves it for users with the right capability.
export function HtmlBlockFields({ block, onChange }: BlockFieldProps) {
    return (
        <div className="grid gap-1">
            <Textarea
                aria-label="HTML"
                spellCheck={false}
                value={block.content ?? ''}
                onChange={(event) =>
                    onChange({ ...block, content: event.target.value })
                }
                rows={4}
                placeholder="<div>Custom HTML</div>"
                className="bg-zinc-50 font-mono text-sm dark:bg-zinc-900"
            />
            <p className="text-xs text-muted-foreground">
                {t('Raw HTML is printed as it is. Only admins can save it.')}
            </p>
        </div>
    );
}

export function SeparatorBlockFields() {
    return <hr className="my-3 border-zinc-300 dark:border-zinc-700" />;
}

// Label, link and look of a button.
export function ButtonBlockFields({ block, onChange }: BlockFieldProps) {
    const newTabId = `${block.id}-new-tab`;

    return (
        <div className="auto-fit-grid gap-2 [--grid-min:13rem]">
            <Input
                aria-label={t('Button label')}
                placeholder={t('Button label')}
                value={stringAttr(block, 'label')}
                onChange={(event) =>
                    onChange(withAttrs(block, { label: event.target.value }))
                }
            />
            <Input
                aria-label={t('Button link')}
                placeholder={t('https://example.org or /contact')}
                value={stringAttr(block, 'url')}
                onChange={(event) =>
                    onChange(withAttrs(block, { url: event.target.value }))
                }
            />
            <Select
                value={stringAttr(block, 'style') || 'primary'}
                onValueChange={(style) => onChange(withAttrs(block, { style }))}
            >
                <SelectTrigger aria-label={t('Button style')}>
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {BUTTON_STYLES.map((style) => (
                        <SelectItem key={style.value} value={style.value}>
                            {t(style.label)}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <div className="flex items-center gap-2">
                <Checkbox
                    id={newTabId}
                    checked={boolAttr(block, 'newTab')}
                    onCheckedChange={(checked) =>
                        onChange(withAttrs(block, { newTab: checked === true }))
                    }
                />
                <Label htmlFor={newTabId}>{t('Open in a new tab')}</Label>
            </div>
        </div>
    );
}
