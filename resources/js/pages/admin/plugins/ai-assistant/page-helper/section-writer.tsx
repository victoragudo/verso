import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import { sectionName } from '@/lib/easy/page-blocks';
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
import { changedTexts, withSection } from './page-text';
import type { PageHelperProps, SectionTextResult } from './types';

type Props = {
    config: PageHelperProps;
    context: PageEditorContext;
    section: Block;
    onClose: () => void;
};

// "Write this section with AI": new texts for one section from the business description, shown before and after.
export function SectionWriter({ config, context, section, onClose }: Props) {
    const { business, setBusiness, save } = useBusiness(config);
    const { busy, error, run } = useHelperTask();
    const [written, setWritten] = useState<Block | null>(null);
    const name = sectionName(context.blocks, section.id);
    const changes = written ? changedTexts([section], [written]) : [];

    const write = async () => {
        const result = await run(async (signal) => {
            const description = await save(signal);

            return askAssistant<SectionTextResult>(
                config.endpoints.sectionText,
                { section, business: description },
                signal,
            );
        });

        if (result) {
            setWritten(result.section);
        }
    };

    const accept = () => {
        if (!written) {
            return;
        }

        onClose();
        context.change(
            withSection(context.blocks, written),
            t('ai-assistant::New texts added to {section}', { section: name }),
        );
    };

    return (
        <HelperDialog
            title={t('ai-assistant::Write this section with AI')}
            description={t(
                'ai-assistant::The helper writes new texts for {section}. Nothing changes until you accept them.',
                { section: name },
            )}
            busy={busy}
            status={
                written
                    ? t(
                          'ai-assistant::{count, plural, =0 {The helper kept the same texts.} one {# new text is ready. Check it, then accept or discard.} other {# new texts are ready. Check them, then accept or discard.}}',
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
                    {written ? (
                        <>
                            <Button
                                type="button"
                                variant="outline"
                                className="h-11"
                                disabled={busy}
                                onClick={write}
                            >
                                {t('ai-assistant::Try again')}
                            </Button>
                            <Button
                                type="button"
                                className="h-11"
                                disabled={busy || changes.length === 0}
                                onClick={accept}
                                autoFocus
                            >
                                {t('ai-assistant::Accept new texts')}
                            </Button>
                        </>
                    ) : (
                        <Button
                            type="button"
                            className="h-11"
                            disabled={busy || business.trim() === ''}
                            onClick={write}
                        >
                            {t('ai-assistant::Write the texts')}
                        </Button>
                    )}
                </>
            }
        >
            {!written && (
                <BusinessField
                    value={business}
                    max={config.businessMax}
                    disabled={busy}
                    onChange={setBusiness}
                />
            )}
            {busy && <BusyBar label={t('ai-assistant::Writing new texts…')} />}
            <HelperError message={error} />
            {written && !busy && changes.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    {t(
                        'ai-assistant::The helper kept the same texts. Try again or change the description of your business.',
                    )}
                </p>
            )}
            {written && !busy && changes.length > 0 && (
                <ChangesPreview changes={changes} />
            )}
        </HelperDialog>
    );
}
