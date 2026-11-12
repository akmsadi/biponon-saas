import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Boxes,
    LayoutGrid,
    Settings,
    Tags,
    Users,
    Warehouse,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as colorsIndex } from '@/routes/catalog/colors';
import { index as productsIndex } from '@/routes/catalog/products';
import { index as sizesIndex } from '@/routes/catalog/sizes';
import { index as movementsIndex } from '@/routes/inventory/movements';
import { index as serialsIndex } from '@/routes/inventory/serials';
import { index as stockIndex } from '@/routes/inventory/stock';
import { create as dispatchStock } from '@/routes/operations/dispatch';
import { create as receiveStock } from '@/routes/operations/receive';
import { create as returnStock } from '@/routes/operations/return';
import { index as membersIndex } from '@/routes/organization/members';
import { create as transferStock } from '@/routes/operations/transfer';
import { edit as editProfile } from '@/routes/profile';
import { index as warehousesIndex } from '@/routes/warehouses';
import type { SidebarNavItem } from '@/types';

const mainNavItems: SidebarNavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    {
        title: 'Inventory',
        icon: Boxes,
        items: [
            { title: 'Stock overview', href: stockIndex() },
            { title: 'Serial numbers', href: serialsIndex() },
            { title: 'Stock movements', href: movementsIndex() },
        ],
    },
    {
        title: 'Catalog',
        icon: Tags,
        items: [
            { title: 'Products', href: productsIndex() },
            { title: 'Sizes', href: sizesIndex() },
            { title: 'Colors', href: colorsIndex() },
        ],
    },
    { title: 'Warehouses', href: warehousesIndex(), icon: Warehouse },
    {
        title: 'Operations',
        icon: ArrowLeftRight,
        items: [
            { title: 'Receive stock', href: receiveStock() },
            { title: 'Transfer stock', href: transferStock() },
            { title: 'Dispatch stock', href: dispatchStock() },
            { title: 'Return stock', href: returnStock() },
        ],
    },
    { title: 'Settings', href: editProfile(), icon: Settings },
];

// Only admins of the current organization manage its members.
const adminNavItems: SidebarNavItem[] = [
    {
        title: 'Organization',
        icon: Users,
        items: [{ title: 'Members', href: membersIndex() }],
    },
];

const footerNavItems: SidebarNavItem[] = [];

export function AppSidebar() {
    const { auth } = usePage().props;
    const isAdmin =
        auth.activeOrganizations.find(
            (organization) => organization.id === auth.currentOrganizationId,
        )?.role === 'admin';

    const navItems = isAdmin
        ? [
              ...mainNavItems.slice(0, -1),
              ...adminNavItems,
              ...mainNavItems.slice(-1),
          ]
        : mainNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={navItems} />
            </SidebarContent>

            <SidebarFooter>
                {footerNavItems.length > 0 && (
                    <NavFooter items={footerNavItems} className="mt-auto" />
                )}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
