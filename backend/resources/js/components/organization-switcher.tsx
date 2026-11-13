import { router, usePage } from '@inertiajs/react';
import { Building2, Check, ChevronsUpDown } from 'lucide-react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { useIsMobile } from '@/hooks/use-mobile';
import { update as updateCurrentOrganization } from '@/routes/current-organization';
import type { ActiveOrganization } from '@/types';

function capitalize(value: string): string {
    return value.charAt(0).toUpperCase() + value.slice(1);
}

function OrganizationInfo({
    organization,
}: {
    organization: ActiveOrganization;
}) {
    return (
        <>
            <div className="flex aspect-square size-8 shrink-0 items-center justify-center rounded-md border bg-background">
                <Building2 className="size-4" />
            </div>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">
                    {organization.name}
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    {capitalize(organization.role)}
                </span>
            </div>
        </>
    );
}

/**
 * Shows the organization the user is working in and their role there. With more than one organization it opens
 * a list to switch between them. Renders nothing for users without an organization (e.g. super-admins).
 */
export function OrganizationSwitcher() {
    const { auth } = usePage().props;
    const { state } = useSidebar();
    const isMobile = useIsMobile();

    const organizations = auth.activeOrganizations;
    const current =
        organizations.find((org) => org.id === auth.currentOrganizationId) ??
        organizations[0];

    if (!current) {
        return null;
    }

    if (organizations.length === 1) {
        return (
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        className="cursor-default hover:bg-transparent active:bg-transparent"
                        tooltip={current.name}
                        asChild
                    >
                        <div>
                            <OrganizationInfo organization={current} />
                        </div>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        );
    }

    return (
        <SidebarMenu>
            <SidebarMenuItem>
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <SidebarMenuButton
                            size="lg"
                            className="data-[state=open]:bg-sidebar-accent"
                            tooltip={current.name}
                        >
                            <OrganizationInfo organization={current} />
                            <ChevronsUpDown className="ml-auto size-4" />
                        </SidebarMenuButton>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
                        align="start"
                        side={
                            isMobile || state !== 'collapsed'
                                ? 'bottom'
                                : 'right'
                        }
                    >
                        <DropdownMenuLabel className="text-xs text-muted-foreground">
                            Organizations
                        </DropdownMenuLabel>
                        {organizations.map((org) => (
                            <DropdownMenuItem
                                key={org.id}
                                onSelect={() =>
                                    org.id !== current.id &&
                                    router.put(updateCurrentOrganization(), {
                                        organization_id: org.id,
                                    })
                                }
                            >
                                <Building2 />
                                <span className="truncate">{org.name}</span>
                                {org.id === current.id && (
                                    <Check className="ml-auto" />
                                )}
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuContent>
                </DropdownMenu>
            </SidebarMenuItem>
        </SidebarMenu>
    );
}
