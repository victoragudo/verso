import { Link, router } from '@inertiajs/react';
import { Download, LayoutTemplate, Upload } from 'lucide-react';
import { useRef } from 'react';
import { Button } from '@/components/ui/button';
import { ChildThemeDialog } from '@/components/verso/site/child-theme-dialog';
import { t } from '@/lib/i18n';
import {
    exportMethod,
    importMethod,
    templates,
} from '@/routes/admin/site-editor';

// Header buttons of the Site Editor: the templates view, export and import of the customization as JSON,
// and a child theme made from the changes.
export function CustomizationActions({ themeName }: { themeName?: string }) {
    const fileInput = useRef<HTMLInputElement>(null);

    const upload = (file: File | undefined) => {
        if (!file) {
            return;
        }

        router.post(importMethod.url(), { file }, { forceFormData: true });

        if (fileInput.current) {
            fileInput.current.value = '';
        }
    };

    return (
        <div className="flex flex-wrap gap-2">
            <Button asChild variant="outline">
                <Link href={templates().url}>
                    <LayoutTemplate aria-hidden="true" />
                    {t('Templates')}
                </Link>
            </Button>
            <Button asChild variant="outline">
                <a href={exportMethod().url} download>
                    <Download aria-hidden="true" />
                    {t('Export')}
                </a>
            </Button>
            <Button
                type="button"
                variant="outline"
                onClick={() => fileInput.current?.click()}
            >
                <Upload aria-hidden="true" />
                {t('Import')}
            </Button>
            {themeName && <ChildThemeDialog themeName={themeName} />}
            <input
                ref={fileInput}
                type="file"
                accept="application/json,.json"
                className="sr-only"
                tabIndex={-1}
                aria-label={t('Customization file to import')}
                onChange={(event) => upload(event.target.files?.[0])}
            />
        </div>
    );
}
