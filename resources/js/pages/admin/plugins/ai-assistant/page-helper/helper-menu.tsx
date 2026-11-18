import { Button } from '@/components/ui/button';
import { t } from '@/lib/i18n';
import type { PageEditorContext } from '@/lib/easy/page-editor-registry';
import { HelperDialog } from './helper-parts';
import { topSection } from './page-text';
import { pageHelper } from './store';

type ChoiceProps = {
    title: string;
    text: string;
    disabled?: boolean;
    onClick: () => void;
};

// One big button of the helper menu, with a title and a line of text.
function Choice({ title, text, disabled, onClick }: ChoiceProps) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className="flex min-h-11 w-full items-start rounded-lg border bg-card p-3 text-start transition-colors hover:bg-muted/60 focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
        >
            <span className="flex flex-col gap-0.5">
                <span className="text-sm font-medium">{title}</span>
                <span className="text-sm text-muted-foreground">{text}</span>
            </span>
        </button>
    );
}

// The "AI helper" button of the top bar: tools for the whole page.
export function HelperMenu({
    context,
    onClose,
}: {
    context: PageEditorContext;
    onClose: () => void;
}) {
    const top = topSection(context.blocks);

    return (
        <HelperDialog
            title={t('ai-assistant::AI helper')}
            description={t(
                'ai-assistant::Pick what the helper should do. You see every change before it is made.',
            )}
            onClose={onClose}
            footer={
                <Button
                    type="button"
                    variant="ghost"
                    className="h-11"
                    onClick={onClose}
                >
                    {t('Close')}
                </Button>
            }
        >
            <div className="flex flex-col gap-2">
                <Choice
                    title={t('ai-assistant::Translate this page')}
                    text={t(
                        'ai-assistant::Every text of the page in another language.',
                    )}
                    disabled={context.blocks.length === 0}
                    onClick={() =>
                        pageHelper.open({ kind: 'translate', context })
                    }
                />
                <Choice
                    title={t(
                        'ai-assistant::New headline for the top of the page',
                    )}
                    text={
                        top
                            ? t(
                                  'ai-assistant::A few headline ideas for the first heading of the page.',
                              )
                            : t(
                                  'ai-assistant::Add a section with a heading first.',
                              )
                    }
                    disabled={top === null}
                    onClick={() =>
                        top &&
                        pageHelper.open({
                            kind: 'headlines',
                            context,
                            section: top,
                        })
                    }
                />
            </div>
            <p className="text-sm text-muted-foreground">
                {t(
                    'ai-assistant::For one section, use its toolbar on the page: Write this section with AI, Suggest headlines and Describe images.',
                )}
            </p>
        </HelperDialog>
    );
}

// Shown to admins when the helper has no API key yet.
export function SetupHint({
    settingsUrl,
    onClose,
}: {
    settingsUrl: string;
    onClose: () => void;
}) {
    return (
        <HelperDialog
            title={t('ai-assistant::Set up the AI helper')}
            description={t(
                'ai-assistant::The AI helper can write section texts, suggest headlines, translate pages and describe pictures.',
            )}
            onClose={onClose}
            footer={
                <>
                    <Button
                        type="button"
                        variant="ghost"
                        className="h-11"
                        onClick={onClose}
                    >
                        {t('ai-assistant::Not now')}
                    </Button>
                    <Button asChild className="h-11">
                        <a href={settingsUrl}>
                            {t('ai-assistant::Open AI Assistant settings')}
                        </a>
                    </Button>
                </>
            }
        >
            <p className="text-sm">
                {t(
                    'ai-assistant::It needs an API key first. Add it in Settings, AI Assistant. Save your page before you go, so you keep your changes.',
                )}
            </p>
        </HelperDialog>
    );
}
