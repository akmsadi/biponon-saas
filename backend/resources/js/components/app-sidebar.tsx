import { Link, usePage } from '@inertiajs/react';
import {
    ArrowLeftRight,
    Boxes,
    LayoutGrid,
    Settings,
    ShoppingCart,
    Tags,
    Users,
    Warehouse,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { OrganizationSwitcher } from '@/components/organization-switcher';
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
import { index as organizationSettings } from '@/routes/organization/settings';
import { index as purchaseOrdersIndex } from '@/routes/purchasing/orders';
import { index as suppliersIndex } from '@/routes/purchasing/suppliers';
import { create as transferStock } from '@/routes/operations/transfer';
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
        title: 'Purchasing',
        icon: ShoppingCart,
        items: [
            { title: 'Purchase orders', href: purchaseOrdersIndex() },
            { title: 'Suppliers', href: suppliersIndex() },
        ],
    },
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
];

// The current organization's settings; personal settings live in the user menu.
const settingsNavItem: SidebarNavItem = {
    title: 'Settings',
    href: organizationSettings(),
    icon: Settings,
};

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

    // Without an organization (e.g. a super-admin who belongs to none) the organization pages only redirect away.
    const organizationItems =
        auth.activeOrganizations.length > 0
            ? [
                  ...mainNavItems,
                  ...(auth.can.manageMembers ? adminNavItems : []),
                  settingsNavItem,
              ]
            : [];

    return (
        <Sidebar collapsible="icon" variant="sidebar">
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
                <OrganizationSwitcher />
            </SidebarHeader>

            <SidebarContent>
                {organizationItems.length > 0 && (
                    <NavMain items={organizationItems} />
                )}
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
