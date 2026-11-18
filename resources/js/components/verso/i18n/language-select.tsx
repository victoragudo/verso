import { Languages } from 'lucide-react';
import { useId } from 'react';
import { OptionSelect } from '@/components/verso/ui/option-select';
import { t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { SITE_LANGUAGE, useAdminLanguage } from './use-admin-language';

// A select with every admin language, for the login screens, the
// installer and the profile page. With withSiteChoice it also offers "Same as
// the site". Each language shows its own name, marked with its lang.
export function LanguageSelect({
    withSiteChoice = false,
    showLabel = false,
    className,
    onChange,
}: {
    withSiteChoice?: boolean;
    showLabel?: boolean;
    className?: string;
    onChange?: (locale: string) => void;
}) {
    const id = useId();
    const { locales, current, chosen, siteName, saving, choose } =
        useAdminLanguage();
    const value = withSiteChoice ? chosen : current;

    if (locales.length < 2) {
        return null;
    }

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <Languages
                className="size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <label
                htmlFor={id}
                className={cn(
                    'text-sm text-muted-foreground',
                    !showLabel && 'sr-only',
                )}
            >
                {t('Language')}
            </label>
            <OptionSelect
                id={id}
                value={value}
                disabled={saving}
                onValueChange={(locale) => {
                    onChange?.(locale);
                    choose(locale);
                }}
                options={[
                    ...(withSiteChoice
                        ? [
                              {
                                  value: SITE_LANGUAGE,
                                  label: t('Same as the site ({language})', {
                                      language: siteName,
                                  }),
                              },
                          ]
                        : []),
                    ...locales.map((locale) => ({
                        value: locale.code,
                        label: locale.name,
                        lang: locale.code,
                    })),
                ]}
                searchPlaceholder={t('Search languages')}
                className="w-auto min-w-0"
            />
        </div>
    );
}
