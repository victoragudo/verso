import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { CustomCodeFields } from '@/components/verso/editor/custom-code-fields';
import { PageHeader, SectionCard } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import { update } from '@/routes/admin/editor/code';

type Props = {
    css: string;
    js: string;
    can: { css: boolean; js: boolean };
};

// "Custom code": CSS and JavaScript for every page of the site.
export default function CustomCodePage({ css, js, can }: Props) {
    const form = useForm({ css, js });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(), { preserveScroll: true });
    };

    return (
        <>
            <Head title={t('Custom code')} />
            <PageHeader
                icon="settings"
                title={t('Custom code')}
                description={t(
                    'Add your own CSS and JavaScript to every page. Single pages can add more in the editor.',
                )}
            />
            <form onSubmit={submit} className="flex flex-col gap-4">
                <SectionCard contentClassName="grid gap-4">
                    <CustomCodeFields
                        value={form.data}
                        onChange={(value) => form.setData(value)}
                        can={can}
                        scope="every page"
                    />
                    {(form.errors.css || form.errors.js) && (
                        <p role="alert" className="text-sm text-destructive">
                            {form.errors.css ?? form.errors.js}
                        </p>
                    )}
                </SectionCard>
                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {t('Save custom code')}
                    </Button>
                </div>
            </form>
        </>
    );
}
