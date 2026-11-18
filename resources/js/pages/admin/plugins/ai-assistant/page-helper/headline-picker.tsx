import { useId, useState } from 'react';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import type { PageEditorContext } from '@/lib/easy/page-editor-registry';
import type { Block } from '@/types';
import { askAssistant } from '../ai-api';
import {
    BusinessField,
    BusyBar,
    ChangesPreview,
    HelperDialog,
    HelperError,
    useBusiness,
    useHelperTask,
} from './helper-parts';
import { firstHeading, sectionWords, withHeadline } from './page-text';
import type { HeadlinesResult, PageHelperProps } from './types';

type Props = {
    config: PageHelperProps;
    context: PageEditorContext;
    section: Block;
    onClose: () => void;
};

// "Suggest headlines": a few headline ideas for the first heading of a section. The chosen one replaces it.
export function HeadlinePicker({ config, context, section, onClose }: Props) {
    const { business, setBusiness, save } = useBusiness(config);
    const { busy, error, run } = useHelperTask();
    const [headlines, setHeadlines] = useState<string[] | null>(null);
    const [chosen, setChosen] = useState('');
    const groupId = useId();
    const heading = firstHeading(section);

    const suggest = async () => {
        const result = await run(async (signal) => {
            const description = await save(signal);

            return askAssistant<HeadlinesResult>(
                config.endpoints.headlines,
                {
                    headline: heading?.text ?? '',
                    context: sectionWords(section),
                    business: description,
                },
                signal,
            );
        });

        if (result) {
            setHeadlines(result.headlines);
            setChosen(result.headlines[0] ?? '');
        }
    };

    const accept = () => {
        if (!heading || chosen === '') {
            return;
        }

        onClose();
        context.change(
            withHeadline(context.blocks, heading.id, chosen),
            t('ai-assistant::Headline changed to {headline}', {
                headline: chosen,
            }),
        );
    };

    return (
        <HelperDialog
            title={t('ai-assistant::Suggest headlines')}
            description={t(
                'ai-assistant::Pick the headline you like best. Nothing changes until you accept it.',
            )}
            busy={busy}
            status={
                headlines
                    ? t(
                          'ai-assistant::{count, plural, one {# headline is ready. Pick it, then accept or discard.} other {# headlines are ready. Pick one, then accept or discard.}}',
                          { count: headlines.length },
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
                    {headlines ? (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                className="h-11"
                                disabled={busy}
                                onClick={suggest}
                            >
                                {t('ai-assistant::More ideas')}
                            </Button>
                            <Button
                                type="button"
                                className="h-11"
                                disabled={busy || chosen === ''}
                                onClick={accept}
                            >
                                {t('ai-assistant::Use this headline')}
                            </Button>
                        </>
                    ) : (
                        <Button
                            type="button"
                            className="h-11"
                            disabled={busy || business.trim() === ''}
                            onClick={suggest}
                        >
                            {t('ai-assistant::Suggest headlines')}
                        </Button>
                    )}
                </>
            }
        >
            {!headlines && (
                <BusinessField
                    value={business}
                    max={config.businessMax}
                    disabled={busy}
                    onChange={setBusiness}
                />
            )}
            {busy && (
                <BusyBar label={t('ai-assistant::Thinking of headlines…')} />
            )}
            <HelperError message={error} />
            {headlines && !busy && (
                <>
                    <fieldset className="flex flex-col gap-2">
                        <legend className="mb-2 text-sm font-medium">
                            {t('ai-assistant::Headline ideas')}
                        </legend>
                        {headlines.map((headline, index) => (
                            <label
                                key={headline}
                                htmlFor={`${groupId}-${index}`}
                                className="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border bg-card px-3 py-2 text-sm has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50 dark:has-[:checked]:bg-blue-950/40"
                            >
                                <input
                                    id={`${groupId}-${index}`}
                                    type="radio"
                                    name={groupId}
                                    value={headline}
                                    checked={chosen === headline}
                                    onChange={() => setChosen(headline)}
                                    className="size-4 shrink-0 accent-blue-600"
                                />
                                <span className="break-words">{headline}</span>
                            </label>
                        ))}
                    </fieldset>
                    {chosen !== '' && (
                        <ChangesPreview
                            changes={[
                                {
                                    key: 'headline',
                                    before: heading?.text ?? '',
                                    after: chosen,
                                },
                            ]}
                        />
                    )}
                </>
            )}
        </HelperDialog>
    );
}
