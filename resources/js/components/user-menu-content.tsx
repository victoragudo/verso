import { Link, router, usePage } from '@inertiajs/react';
import { LogOut, Settings } from 'lucide-react';
import {
    DropdownMenuCheckboxItem,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { saveSimpleMode } from '@/components/verso/easy/simple-mode-switch';
import { LanguageMenu } from '@/components/verso/i18n/language-menu';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { t } from '@/lib/i18n';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { simpleMode } = usePage().props;

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="me-2" />
                        {t('Settings')}
                    </Link>
                </DropdownMenuItem>
                {simpleMode != null && (
                    <DropdownMenuCheckboxItem
                        checked={simpleMode}
                        onCheckedChange={(checked) =>
                            saveSimpleMode(checked === true)
                        }
                        className="min-h-10 md:min-h-8"
                    >
                        {t('Simple mode')}
                    </DropdownMenuCheckboxItem>
                )}
                <LanguageMenu />
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="me-2" />
                    {t('Log out')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
