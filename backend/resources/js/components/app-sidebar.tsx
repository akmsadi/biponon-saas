import { Link } from '@inertiajs/react';
import {
    // ArrowLeftRight,
    // Boxes,
    LayoutGrid,
    Settings,
    Tags,
    // Users,
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
import { edit as editProfile } from '@/routes/profile';
import { index as warehousesIndex } from '@/routes/warehouses';
import type { SidebarNavItem } from '@/types';

// Unbuilt pages are commented out to avoid dead links; uncomment each as its route lands.
const mainNavItems: SidebarNavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutGrid },
    // {
    //     title: 'Inventory',
    //     icon: Boxes,
    //     items: [
    //         { title: 'Stock overview', href: '/inventory/stock' },
    //         { title: 'Serial numbers', href: '/inventory/serials' },
    //         { title: 'Stock movements', href: '/inventory/movements' },
    //     ],
    // },
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
    // {
    //     title: 'Operations',
    //     icon: ArrowLeftRight,
    //     items: [
    //         { title: 'Receive stock', href: '/operations/receive' },
    //         { title: 'Transfer stock', href: '/operations/transfer' },
    //         { title: 'Dispatch / returns', href: '/operations/dispatch' },
    //     ],
    // },
    // {
    //     title: 'Organization',
    //     icon: Users,
    //     items: [{ title: 'Members', href: '/organization/members' }],
    // },
    { title: 'Settings', href: editProfile(), icon: Settings },
];

const footerNavItems: SidebarNavItem[] = [];

export function AppSidebar() {
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
                <NavMain items={mainNavItems} />
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
