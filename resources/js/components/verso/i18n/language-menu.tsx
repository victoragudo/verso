import { Languages } from 'lucide-react';
import {
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import { t } from '@/lib/i18n';
import { SITE_LANGUAGE, useAdminLanguage } from './use-admin-language';

// "Language" entry of the user menu with every admin language.
export function LanguageMenu() {
    const { locales, current, chosen, siteName, saving, choose } =
        useAdminLanguage();
    const currentName =
        locales.find((locale) => locale.code === current)?.name ?? current;

    if (locales.length < 2) {
        return null;
    }

    return (
        <DropdownMenuSub>
            <DropdownMenuSubTrigger disabled={saving}>
                <Languages aria-hidden="true" />
                <span className="flex-1">{t('Language')}</span>
                <span className="ms-2 truncate text-xs text-muted-foreground">
                    {currentName}
                </span>
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent className="max-h-[min(28rem,70svh)] w-60 overflow-y-auto">
                <DropdownMenuRadioGroup value={chosen} onValueChange={choose}>
                    <DropdownMenuRadioItem value={SITE_LANGUAGE}>
                        {t('Same as the site ({language})', {
                            language: siteName,
                        })}
                    </DropdownMenuRadioItem>
                    {locales.map((locale) => (
                        <DropdownMenuRadioItem
                            key={locale.code}
                            value={locale.code}
                        >
                            <span lang={locale.code} dir={locale.dir}>
                                {locale.name}
                            </span>
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    );
}
