import { Link, router } from '@inertiajs/react';
import { Building2, LogOut, Settings, Check } from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { ActiveOrganization, User } from '@/types';
import { update as updateCurrentOrganization } from '@/routes/current-organization';

type Props = {
    user: User;
    activeOrganizations: ActiveOrganization[];
    currentOrganizationId: number | null;
};

export function UserMenuContent({ user, activeOrganizations, currentOrganizationId }: Props) {
    const cleanup = useMobileNavigation();

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
            {activeOrganizations.length > 0 && (
                <>
                    <DropdownMenuLabel className="text-xs text-muted-foreground">
                        Organizations
                    </DropdownMenuLabel>
                    <DropdownMenuGroup>
                        {activeOrganizations.map((org) => (
                            <DropdownMenuItem
                                key={org.id}
                                onSelect={() => router.put(updateCurrentOrganization(), { organization_id: org.id })}
                            >
                                <Building2 className="mr-2" />
                                <span className="truncate">{org.name}</span>
                                {org.id === currentOrganizationId && <Check className="ml-auto" />}
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
