import { NAV_WIDTH_LIMITS } from '@/hooks/use-admin-nav-width';
import { t } from '@/lib/i18n';
import { ResizeHandle } from './resize-handle';

type Props = {
    width: number;
    onResize: (width: number) => void;
    onReset: () => void;
};

// The handle on the inner edge of the admin sidebar.
export function NavResizer({ width, onResize, onReset }: Props) {
    return (
        <ResizeHandle
            width={width}
            limits={NAV_WIDTH_LIMITS}
            label={t(
                'Resize the menu. Use the arrow keys, or double click to reset.',
            )}
            onResize={onResize}
            onReset={onReset}
            slot="admin-nav-resizer"
        />
    );
}
