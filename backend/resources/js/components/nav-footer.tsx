import { ChevronRight } from 'lucide-react';
import type { ComponentPropsWithoutRef } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupContent,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { toUrl } from '@/lib/utils';
import type { SidebarNavItem } from '@/types';

const linkClass =
    'text-neutral-600 hover:text-neutral-800 dark:text-neutral-300 dark:hover:text-neutral-100';

export function NavFooter({
    items,
    className,
    ...props
}: ComponentPropsWithoutRef<typeof SidebarGroup> & {
    items: SidebarNavItem[];
}) {
    return (
        <SidebarGroup
            {...props}
            className={`group-data-[collapsible=icon]:p-0 ${className || ''}`}
        >
            <SidebarGroupContent>
                <SidebarMenu>
                    {items.map((item) =>
                        'items' in item ? (
                            <Collapsible
                                key={item.title}
                                asChild
                                className="group/collapsible"
                            >
                                <SidebarMenuItem>
                                    <CollapsibleTrigger asChild>
                                        <SidebarMenuButton className={linkClass}>
                                            {item.icon && <item.icon className="h-5 w-5" />}
                                            <span>{item.title}</span>
                                            <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                                        </SidebarMenuButton>
                                    </CollapsibleTrigger>
                                    <CollapsibleContent>
                                        <SidebarMenuSub>
                                            {item.items.map((child) => (
                                                <SidebarMenuSubItem key={child.title}>
                                                    <SidebarMenuSubButton asChild>
                                                        <a
                                                            href={toUrl(child.href)}
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                        >
                                                            <span>{child.title}</span>
                                                        </a>
                                                    </SidebarMenuSubButton>
                                                </SidebarMenuSubItem>
                                            ))}
                                        </SidebarMenuSub>
                                    </CollapsibleContent>
                                </SidebarMenuItem>
                            </Collapsible>
                        ) : (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton asChild className={linkClass}>
                                    <a
                                        href={toUrl(item.href)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        {item.icon && <item.icon className="h-5 w-5" />}
                                        <span>{item.title}</span>
                                    </a>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ),
                    )}
                </SidebarMenu>
            </SidebarGroupContent>
        </SidebarGroup>
    );
}
