import { AdminShell } from '@/components/verso/shell/admin-shell';
import type { AppLayoutProps } from '@/types';

// Admin layout: wraps every admin page in the Verso shell.
export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return <AdminShell breadcrumbs={breadcrumbs}>{children}</AdminShell>;
}
