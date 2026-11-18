import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { I18nRoot } from '@/components/verso/i18n/i18n-root';
import { registerEasySlots } from '@/components/verso/easy/slots';
import { loadPluginModules, registerCoreSlots } from '@/components/verso/slots';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import WorkspaceLayout from '@/layouts/workspace-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { bootI18n } from '@/lib/i18n/boot';
import { pageTitle } from '@/lib/page-title';
import { enablePageTransitions } from '@/lib/page-transitions';
import { installUserInvalidSync } from '@/lib/user-invalid';

// Pages that draw their own full screen frame instead of the admin shell.
const FULL_SCREEN_PAGES = [
    'admin/content/entries/edit',
    'admin/easy/page-editor',
];

// Builder pages: inside the admin frame, filling the screen height, with a focus mode that hides the sidebar.
const WORKSPACE_PAGES = [
    'admin/site-editor/edit',
    'admin/site-editor/layouts/edit',
    'admin/library/edit',
];

// Admin slot components: core ones first, then plugins/*/resources/js/index.tsx.
registerCoreSlots();
registerEasySlots();
loadPluginModules();

// The strings of the admin language load before the first render.
void bootI18n().then(() => createInertiaApp({
    title: pageTitle,
    layout: (name) => {
        switch (true) {
            case name.startsWith('install/'):
                return undefined;
            case name.startsWith('auth/'):
                return AuthLayout;
            case FULL_SCREEN_PAGES.includes(name):
                return null;
            case WORKSPACE_PAGES.includes(name):
                return WorkspaceLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <I18nRoot>
                <TooltipProvider delayDuration={0}>
                    {app}
                    <Toaster />
                </TooltipProvider>
            </I18nRoot>
        );
    },
    progress: {
        color: '#4B5563',
    },
}));

// This will set light / dark mode on load...
initializeTheme();

enablePageTransitions();

// Browser validation errors reach screen readers only after a field was used.
installUserInvalidSync();
