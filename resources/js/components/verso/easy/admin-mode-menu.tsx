import { LayoutDashboard } from 'lucide-react';
import {
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import { saveSimpleMode } from '@/components/verso/easy/simple-mode-switch';
import { t } from '@/lib/i18n';

const SIMPLE = 'simple';
const ADVANCED = 'advanced';

// "Admin mode" entry of the user menu: simple shows the main tools, advanced shows all of them.
export function AdminModeMenu({ simpleMode }: { simpleMode: boolean }) {
    return (
        <DropdownMenuSub>
            <DropdownMenuSubTrigger>
                <LayoutDashboard aria-hidden="true" />
                <span className="flex-1">{t('Admin mode')}</span>
                <span className="ms-2 truncate text-xs text-muted-foreground">
                    {simpleMode ? t('Simple') : t('Advanced')}
                </span>
            </DropdownMenuSubTrigger>
            <DropdownMenuSubContent className="w-60">
                <DropdownMenuRadioGroup
                    value={simpleMode ? SIMPLE : ADVANCED}
                    onValueChange={(value) => saveSimpleMode(value === SIMPLE)}
                >
                    <DropdownMenuRadioItem value={SIMPLE}>
                        {t('Simple mode')}
                    </DropdownMenuRadioItem>
                    <DropdownMenuRadioItem value={ADVANCED}>
                        {t('Advanced mode')}
                    </DropdownMenuRadioItem>
                </DropdownMenuRadioGroup>
            </DropdownMenuSubContent>
        </DropdownMenuSub>
    );
}
