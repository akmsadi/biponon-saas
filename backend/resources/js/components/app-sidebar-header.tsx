import { usePage } from '@inertiajs/react';
import { Store } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    // Only set while the current organization's online shop is open.
    const { organizationShopLink } = usePage().props;

    return (
        <header className="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {organizationShopLink && (
                <Button variant="outline" size="sm" className="ml-auto" asChild>
                    <a
                        href={organizationShopLink}
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="Visit shop (opens in a new tab)"
                    >
                        <Store />
                        <span className="hidden sm:inline">Visit shop</span>
                    </a>
                </Button>
            )}
        </header>
    );
}
