import { Link, router, usePage } from '@inertiajs/react';
import { Building2, Inbox, LogOut, Settings } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { index as adminAccessRequestsIndex } from '@/routes/admin/access-requests';
import { index as adminOrganizationsIndex } from '@/routes/admin/organizations';
import { edit } from '@/routes/profile';
import type { NavItem, User } from '@/types';

type Props = {
    user: User;
};

/**
 * Platform administration pages, shown to super-admins only. Add new super-admin pages here.
 */
const platformItems: NavItem[] = [
    {
        title: 'Organizations',
        href: adminOrganizationsIndex(),
        icon: Building2,
    },
    {
        title: 'Access requests',
        href: adminAccessRequestsIndex(),
        icon: Inbox,
    },
];

/**
 * The user's own menu, plus platform administration for super-admins. Switching organization lives in the
 * sidebar's OrganizationSwitcher.
 */
export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { auth, newAccessRequests } = usePage().props;
    const { can } = auth;

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            {can.administerPlatform && (
                <>
                    <DropdownMenuGroup>
                        {platformItems.map((item) => (
                            <DropdownMenuItem key={item.title} asChild>
                                <Link
                                    className="flex w-full cursor-pointer items-center"
                                    href={item.href}
                                    prefetch
                                    onClick={cleanup}
                                >
                                    {item.icon && (
                                        <item.icon className="mr-2" />
                                    )}
                                    {item.title}
                                    {item.title === 'Access requests' &&
                                        newAccessRequests > 0 && (
                                            <span className="ml-auto rounded-full bg-primary px-1.5 text-xs font-medium text-primary-foreground tabular-nums">
                                                {newAccessRequests}
                                                <span className="sr-only">
                                                    {' '}
                                                    new
                                                </span>
                                            </span>
                                        )}
                                </Link>
                            </DropdownMenuItem>
                        ))}
                    </DropdownMenuGroup>
                    <DropdownMenuSeparator />
                </>
            )}
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
