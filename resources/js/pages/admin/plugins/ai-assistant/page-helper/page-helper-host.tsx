import { useEffect, useSyncExternalStore } from 'react';
import type { SlotComponentProps } from '@/components/verso/slots';
import { HeadlinePicker } from './headline-picker';
import { HelperMenu, SetupHint } from './helper-menu';
import { ImageDescriber } from './image-describer';
import { PageTranslator } from './page-translator';
import { SectionWriter } from './section-writer';
import { pageHelper } from './store';
import type { PageHelperProps } from './types';

// Slot component of the page editor. It hands the server props to the helper tools
// (the builder module adds the buttons when they say the helper is ready) and shows the open dialog.
export function PageHelperHost({ props }: SlotComponentProps) {
    const config = props as PageHelperProps;

    useEffect(() => {
        pageHelper.setConfig(config);

        return () => pageHelper.setConfig(null);
    }, [config]);

    const { config: current, request } = useSyncExternalStore(
        pageHelper.subscribe,
        pageHelper.get,
        pageHelper.get,
    );

    if (!current || !request) {
        return null;
    }

    const close = pageHelper.close;

    switch (request.kind) {
        case 'setup':
            return current.settingsUrl ? (
                <SetupHint settingsUrl={current.settingsUrl} onClose={close} />
            ) : null;
        case 'menu':
            return <HelperMenu context={request.context} onClose={close} />;
        case 'translate':
            return (
                <PageTranslator
                    config={current}
                    context={request.context}
                    onClose={close}
                />
            );
        case 'section-text':
            return (
                <SectionWriter
                    key={request.section.id}
                    config={current}
                    context={request.context}
                    section={request.section}
                    onClose={close}
                />
            );
        case 'headlines':
            return (
                <HeadlinePicker
                    key={request.section.id}
                    config={current}
                    context={request.context}
                    section={request.section}
                    onClose={close}
                />
            );
        case 'images':
            return (
                <ImageDescriber
                    key={request.section.id}
                    config={current}
                    context={request.context}
                    section={request.section}
                    onClose={close}
                />
            );
    }
}
