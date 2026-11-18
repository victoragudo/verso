import { router } from '@inertiajs/react';
import { GitBranchPlus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import { childTheme } from '@/routes/admin/site-editor';

// Folder name made from a theme name, like the server does: "Harbor Light" becomes "harbor-light".
export function slugFromName(name: string): string {
    return name
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

type ChildThemeForm = { name: string; slug: string; activate: boolean };

// "Create child theme from these changes": saves the published parts, colors and fonts as a new theme folder.
export function ChildThemeDialog({ themeName }: { themeName: string }) {
    const [open, setOpen] = useState(false);
    const [data, setForm] = useState<ChildThemeForm>({
        name: t('{theme} custom', { theme: themeName }),
        slug: '',
        activate: false,
    });
    const [errors, setErrors] = useState<Record<string, string | undefined>>(
        {},
    );
    const [processing, setProcessing] = useState(false);
    const setData = <K extends keyof ChildThemeForm>(
        key: K,
        value: ChildThemeForm[K],
    ) => setForm((current) => ({ ...current, [key]: value }));

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.post(childTheme.url(), data, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => setOpen(false),
            onError: setErrors,
        });
    };

    return (
        <>
            <Button
                type="button"
                variant="outline"
                onClick={() => setOpen(true)}
            >
                <GitBranchPlus aria-hidden="true" />
                {t('Create child theme')}
            </Button>
            <Dialog
                open={open}
                onOpenChange={(next) => !processing && setOpen(next)}
            >
                <DialogContent>
                    <form onSubmit={submit} className="grid gap-4">
                        <DialogHeader>
                            <DialogTitle>
                                {t('Create a child theme from these changes')}
                            </DialogTitle>
                            <DialogDescription>
                                {t(
                                    'A new theme based on {theme}, with your published header, footer and other parts, and your colors and fonts built in. Drafts are not included.',
                                    { theme: themeName },
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="child-theme-name">
                                {t('Theme name')}
                            </Label>
                            <Input
                                id="child-theme-name"
                                value={data.name}
                                required
                                maxLength={80}
                                onChange={(event) =>
                                    setData('name', event.target.value)
                                }
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="child-theme-slug">
                                {t('Folder name')}
                            </Label>
                            <Input
                                id="child-theme-slug"
                                value={data.slug}
                                placeholder={slugFromName(data.name)}
                                maxLength={80}
                                aria-describedby="child-theme-slug-help"
                                onChange={(event) =>
                                    setData('slug', event.target.value)
                                }
                            />
                            <p
                                id="child-theme-slug-help"
                                className="text-xs text-muted-foreground"
                            >
                                {t('The theme is saved in themes/{folder}.', {
                                    folder:
                                        slugFromName(data.slug || data.name) ||
                                        '…',
                                })}
                            </p>
                            <InputError message={errors.slug} />
                        </div>
                        <div className="flex items-center gap-2.5">
                            <Checkbox
                                id="child-theme-activate"
                                checked={data.activate}
                                onCheckedChange={(checked) =>
                                    setData('activate', checked === true)
                                }
                            />
                            <Label
                                htmlFor="child-theme-activate"
                                className="font-normal"
                            >
                                {t('Use the new theme now')}
                            </Label>
                        </div>
                        <DialogFooter>
                            <Button
                                type="submit"
                                disabled={processing || data.name.trim() === ''}
                                className="min-h-10 md:min-h-9"
                            >
                                {processing
                                    ? t('Creating…')
                                    : t('Create theme')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
