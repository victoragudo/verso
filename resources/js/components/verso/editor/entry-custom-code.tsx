import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { t } from '@/lib/i18n';
import { entry as entryRoute } from '@/routes/admin/editor/code';
import { update as updateEntry } from '@/routes/admin/editor/code/entry';
import { CustomCodeFields } from './custom-code-fields';
import type { CustomCodeValue } from './custom-code-fields';

// Reads the CSRF token that Laravel keeps in the XSRF-TOKEN cookie.
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
}

async function send(
    url: string,
    method: 'GET' | 'PUT',
    body?: CustomCodeValue,
) {
    const response = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        body: body ? JSON.stringify(body) : undefined,
    });

    if (!response.ok) {
        throw new Error(String(response.status));
    }

    return (await response.json()) as CustomCodeValue & {
        can?: { css: boolean; js: boolean };
    };
}

// "Custom code" of one page in the editor sidebar: a dialog with CSS and JavaScript editors.
export function EntryCustomCode({ entryId }: { entryId: number }) {
    const [open, setOpen] = useState(false);
    const [value, setValue] = useState<CustomCodeValue | null>(null);
    const [can, setCan] = useState({ css: false, js: false });
    const [status, setStatus] = useState('');

    const load = async () => {
        setStatus('');

        try {
            const data = await send(entryRoute.url(entryId), 'GET');
            setValue({ css: data.css, js: data.js });
            setCan(data.can ?? { css: false, js: false });
        } catch {
            setStatus(t('You can not change the custom code of this page.'));
        }
    };

    const save = async () => {
        if (!value) {
            return;
        }

        try {
            const saved = await send(updateEntry.url(entryId), 'PUT', value);
            setValue(saved);
            setStatus(t('Custom code saved.'));
        } catch {
            setStatus(
                t(
                    'The custom code could not be saved. Check its length and try again.',
                ),
            );
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next) {
                    void load();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button type="button" variant="outline" size="sm">
                    {t('Custom CSS and JavaScript')}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>{t('Custom code of this page')}</DialogTitle>
                    <DialogDescription>
                        {t(
                            'Saved at once, apart from the page. Visitors see it when the page is published.',
                        )}
                    </DialogDescription>
                </DialogHeader>
                {value && (
                    <CustomCodeFields
                        value={value}
                        onChange={setValue}
                        can={can}
                        scope="this page"
                    />
                )}
                <p role="status" className="text-sm text-muted-foreground">
                    {status}
                </p>
                <DialogFooter>
                    <Button
                        type="button"
                        onClick={() => void save()}
                        disabled={!value || (!can.css && !can.js)}
                    >
                        {t('Save custom code')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
