import { usePage } from '@inertiajs/react';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { t } from '@/lib/i18n';

// Avatar button in the top bar with profile, language and log out links.
export function UserMenu() {
    const { auth } = usePage().props;
    const getInitials = useInitials();

    if (!auth.user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className="flex size-10 items-center justify-center rounded-full transition-colors outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 data-[state=open]:bg-accent"
                data-test="sidebar-menu-button"
            >
                <Avatar className="size-8 overflow-hidden rounded-full">
                    <AvatarImage src={auth.user.avatar} alt="" />
                    <AvatarFallback className="bg-muted text-xs font-medium text-foreground">
                        {getInitials(auth.user.name)}
                    </AvatarFallback>
                </Avatar>
                <span className="sr-only">{t('Open the account menu')}</span>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="w-60" align="end" sideOffset={6}>
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
