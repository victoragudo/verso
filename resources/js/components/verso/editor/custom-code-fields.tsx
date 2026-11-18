import { lazy, Suspense } from 'react';
import { t } from '@/lib/i18n';

const CodeView = lazy(() => import('./code-view'));

export type CustomCodeValue = { css: string; js: string };

export type CustomCodeScope = 'this page' | 'every page';

type Props = {
    value: CustomCodeValue;
    onChange: (value: CustomCodeValue) => void;
    can: { css: boolean; js: boolean };
    scope: CustomCodeScope;
};

// CSS and JavaScript editors with a short, plain explanation of each.
export function CustomCodeFields({ value, onChange, can, scope }: Props) {
    const loading = (
        <p className="min-h-64 text-sm text-muted-foreground">
            {t('Loading the code editor…')}
        </p>
    );

    return (
        <div className="grid gap-6">
            {can.css && (
                <section
                    className="grid gap-2"
                    aria-labelledby="custom-css-title"
                >
                    <h3 id="custom-css-title" className="font-medium">
                        CSS
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {scope === 'every page'
                            ? t(
                                  'Styles for every page. Unsafe parts, like @import or scripts, are removed when you save.',
                              )
                            : t(
                                  'Styles for this page. Unsafe parts, like @import or scripts, are removed when you save.',
                              )}
                    </p>
                    <Suspense fallback={loading}>
                        <CodeView
                            language="css"
                            label={t('Custom CSS')}
                            value={value.css}
                            onChange={(css) => onChange({ ...value, css })}
                        />
                    </Suspense>
                </section>
            )}
            {can.js && (
                <section
                    className="grid gap-2"
                    aria-labelledby="custom-js-title"
                >
                    <h3 id="custom-js-title" className="font-medium">
                        JavaScript
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        {scope === 'every page'
                            ? t(
                                  'Runs on every page after it loads, from its own file. Only add code you trust.',
                              )
                            : t(
                                  'Runs on this page after it loads, from its own file. Only add code you trust.',
                              )}
                    </p>
                    <Suspense fallback={loading}>
                        <CodeView
                            language="javascript"
                            label={t('Custom JavaScript')}
                            value={value.js}
                            onChange={(js) => onChange({ ...value, js })}
                        />
                    </Suspense>
                </section>
            )}
        </div>
    );
}
