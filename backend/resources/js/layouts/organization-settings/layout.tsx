import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editGeneral } from '@/routes/organization/settings/general';
import { edit as editInventory } from '@/routes/organization/settings/inventory';
import { edit as editMembership } from '@/routes/organization/settings/membership';
import type { NavItem } from '@/types';

const navItems: NavItem[] = [
    { title: 'General', href: editGeneral(), icon: null },
    { title: 'Inventory defaults', href: editInventory(), icon: null },
    { title: 'Leave organization', href: editMembership(), icon: null },
];

/**
 * Sub-navigation for the current organization's settings, mirroring the personal settings layout.
 */
export default function OrganizationSettingsLayout({
    children,
}: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { auth } = usePage().props;
    const current = auth.activeOrganizations.find(
        (org) => org.id === auth.currentOrganizationId,
    );

    return (
        <div className="px-4 py-6">
            <Heading
                title="Organization settings"
                description={
                    auth.can.manageSettings
                        ? `Settings for ${current?.name ?? 'this organization'}`
                        : `Settings for ${current?.name ?? 'this organization'}. Only admins can change them.`
                }
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Organization settings"
                    >
                        {navItems.map((item) => (
                            <Button
                                key={toUrl(item.href)}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>{item.title}</Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
