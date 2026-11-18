import { useId, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { OptionSelect } from '@/components/verso/ui/option-select';
import { languageName, t } from '@/lib/i18n';
import type { PageEditorContext } from '@/lib/easy/page-editor-registry';
import type { Block } from '@/types';
import { askAssistant } from '../ai-api';
import type { TranslateResult } from '../types';
import {
    BusyBar,
    ChangesPreview,
    HelperDialog,
    HelperError,
    useHelperTask,
} from './helper-parts';
import { changedTexts } from './page-text';
import type { PageHelperProps } from './types';

type Props = {
    config: PageHelperProps;
    context: PageEditorContext;
    onClose: () => void;
};

// "Translate this page": every text of the page in another language, shown before and after.
export function PageTranslator({ config, context, onClose }: Props) {
    const { busy, error, run } = useHelperTask();
    const [locale, setLocale] = useState(config.locales[0]?.code ?? '');
    const [translated, setTranslated] = useState<{
        blocks: Block[];
        language: string;
    } | null>(null);
    const selectId = useId();
    const changes = translated
        ? changedTexts(context.blocks, translated.blocks)
        : [];

    const translate = async () => {
        const language = languageName(locale);
        const result = await run((signal) =>
            askAssistant<TranslateResult>(
                config.endpoints.translate,
                {
                    title: context.entry.title,
                    blocks: context.blocks,
                    locale,
                },
                signal,
            ),
        );

        if (result) {
            setTranslated({ blocks: result.blocks, language });
        }
    };

    const accept = () => {
        if (!translated) {
            return;
        }

        onClose();
        context.change(
            translated.blocks,
            t('ai-assistant::Page translated to {language}', {
                language: translated.language,
            }),
        );
    };

    return (
        <HelperDialog
            title={t('ai-assistant::Translate this page')}
            description={t(
                'ai-assistant::The helper translates every text of this page. Nothing changes until you accept it.',
            )}
            busy={busy}
            status={
                translated
                    ? t(
                          'ai-assistant::{count, plural, =0 {No text needs a change.} one {# translated text is ready. Check it, then accept or discard.} other {# translated texts are ready. Check them, then accept or discard.}}',
                          { count: changes.length },
                      )
                    : ''
            }
            onClose={onClose}
            footer={
                <>
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-11"
                        onClick={onClose}
                    >
                        {t('ai-assistant::Discard')}
                    </Button>
                    {translated ? (
                        <Button
                            type="button"
                            className="h-11"
                            disabled={busy || changes.length === 0}
                            onClick={accept}
                        >
                            {t('ai-assistant::Use the {language} texts', {
                                language: translated.language,
                            })}
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            className="h-11"
                            disabled={
                                busy ||
                                locale === '' ||
                                context.blocks.length === 0
                            }
                            onClick={translate}
                        >
                            {t('ai-assistant::Translate the page')}
                        </Button>
                    )}
                </>
            }
        >
            {!translated && (
                <div className="flex flex-col gap-2">
                    <Label htmlFor={selectId}>
                        {t('ai-assistant::Language')}
                    </Label>
                    <OptionSelect
                        id={selectId}
                        value={locale}
                        disabled={busy}
                        onValueChange={setLocale}
                        options={config.locales.map((item) => ({
                            value: item.code,
                            label: languageName(item.code),
                        }))}
                        searchPlaceholder={t('ai-assistant::Search languages')}
                    />
                    <p className="text-xs text-muted-foreground">
                        {t(
                            'ai-assistant::The page name stays as it is. Save the page when you are happy with the texts.',
                        )}
                    </p>
                </div>
            )}
            {busy && (
                <BusyBar label={t('ai-assistant::Translating the page…')} />
            )}
            <HelperError message={error} />
            {translated && !busy && changes.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t('ai-assistant::The texts are already in {language}.', {
                        language: translated.language,
                    })}
                </p>
            )}
            {translated && !busy && changes.length > 0 && (
                <ChangesPreview changes={changes} />
            )}
        </HelperDialog>
    );
}
