import { AdminShell } from '@/components/verso/shell/admin-shell';
import type { AppLayoutProps } from '@/types';

// Admin layout of the builders (theme builder, Site Editor parts, library items): the page fills the screen.
export default function WorkspaceLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AdminShell breadcrumbs={breadcrumbs} workspace>
            {children}
        </AdminShell>
    );
}
