import type { SharedSlots } from '@/components/verso/slots/types';
import type { ViewAsState } from '@/types/access';
import type { Auth } from '@/types/auth';
import type { I18nProps } from '@/types/i18n';
import type { VersoNavItem } from '@/types/verso';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
        // The native switch look of a checkbox (input type="checkbox" switch).
        switch?: '';
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            siteName: string;
            siteTimezone: string;
            versoVersion: string;
            auth: Auth;
            sidebarOpen: boolean;
            versoNav: VersoNavItem[];
            versoSlots?: SharedSlots;
            can: Record<string, boolean>;
            viewAs: ViewAsState | null;
            i18n?: I18nProps;
            // The simple admin mode of the signed in person (Easy mode).
            simpleMode?: boolean | null;
            // Name of the current admin route, like "admin.site.menus.index".
            routeName?: string | null;
            [key: string]: unknown;
        };
    }
}
