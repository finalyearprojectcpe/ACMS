import { Link } from '@inertiajs/react';
import {
    RiBankCardLine,
    RiBookOpenLine,
    RiCalendarTodoLine,
    RiCashLine,
    RiGraduationCapLine,
    RiHome5Line,
    RiLogoutBoxRLine,
    RiSettings3Line,
    RiTeamLine,
    RiUser3Line,
} from '@remixicon/react';
import type { ComponentProps, ComponentType } from 'react';

import { destroy as logoutUser } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import {
    home as dashboardHome,
    level as dashboardLevel,
    section as dashboardSection,
} from '@/actions/App/Http/Controllers/DashboardController';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { dashboardSidebarRoleLabel } from '@/lib/dashboard-labels';
import { initialsFor } from '@/lib/person';
import type { Auth, DashboardContext, DashboardLevel } from '@/types';
import type { RouteDefinition } from '@/wayfinder';

type DashboardRoute = RouteDefinition<'get'>;

type NavItem = {
    title: string;
    section: string;
    href?: DashboardRoute;
    icon: ComponentType<{ className?: string }>;
    items?: NavSubItem[];
};

type NavSubItem = {
    title: string;
    section: string;
    href: DashboardRoute;
    levelSlug?: string;
};

type ActiveItem = {
    section: string;
    levelSlug?: string;
};

export function AppSidebar({
    dashboard,
    user,
    ...props
}: ComponentProps<typeof Sidebar> & {
    dashboard: DashboardContext;
    user: Auth['user'];
}) {
    const navigation = navigationFor(dashboard);
    const sidebarRoleLabel = dashboardSidebarRoleLabel(dashboard);
    const profileSettingsSection = 'profile-settings';

    return (
        <Sidebar collapsible="icon" {...props}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardHome()}>
                                <img
                                    src="/logo.png"
                                    alt=""
                                    className="size-8 rounded-none border bg-background object-contain p-1 dark:bg-foreground"
                                />
                                <div className="flex min-w-0 flex-col gap-0.5 leading-none">
                                    <span className="truncate font-medium">
                                        GradeManager
                                    </span>
                                    <span className="truncate text-muted-foreground">
                                        {sidebarRoleLabel}
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <SidebarGroup>
                    <SidebarMenu>
                        {navigation.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                {item.href && !item.items?.length ? (
                                    <SidebarMenuButton
                                        asChild
                                        tooltip={item.title}
                                        isActive={isActive(item, dashboard)}
                                    >
                                        <Link href={item.href}>
                                            <item.icon className="size-4" />
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                ) : (
                                    <SidebarMenuButton
                                        tooltip={item.title}
                                        isActive={false}
                                        className="cursor-default hover:bg-transparent hover:text-sidebar-foreground active:bg-transparent active:text-sidebar-foreground data-open:hover:bg-transparent data-open:hover:text-sidebar-foreground"
                                    >
                                        <>
                                            <item.icon className="size-4" />
                                            <span>{item.title}</span>
                                        </>
                                    </SidebarMenuButton>
                                )}

                                {item.items?.length ? (
                                    <SidebarMenuSub>
                                        {item.items.map((subItem) => (
                                            <SidebarMenuSubItem
                                                key={`${subItem.title}-${subItem.levelSlug ?? ''}`}
                                            >
                                                <SidebarMenuSubButton
                                                    asChild
                                                    isActive={isActive(
                                                        subItem,
                                                        dashboard,
                                                    )}
                                                >
                                                    <Link href={subItem.href}>
                                                        {subItem.title}
                                                    </Link>
                                                </SidebarMenuSubButton>
                                            </SidebarMenuSubItem>
                                        ))}
                                    </SidebarMenuSub>
                                ) : null}
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            tooltip={`${user.name} (${user.email})`}
                            asChild
                        >
                            <Link
                                href={dashboardSection({
                                    section: profileSettingsSection,
                                })}
                            >
                                <Avatar className="size-8">
                                    <AvatarImage
                                        src={user.avatar ?? undefined}
                                        alt={`${user.name} avatar`}
                                    />
                                    <AvatarFallback className="text-xs font-medium">
                                        {initialsFor(user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="flex min-w-0 flex-col gap-0.5 leading-none">
                                    <span className="truncate font-medium">
                                        {user.name}
                                    </span>
                                    <span className="truncate text-muted-foreground">
                                        {user.email}
                                    </span>
                                </div>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                    <SidebarMenuItem>
                        <SidebarMenuButton asChild tooltip="Logout">
                            <Link href={logoutUser()} as="button">
                                <RiLogoutBoxRLine className="size-4" />
                                <span>Logout</span>
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarFooter>
            <SidebarRail />
        </Sidebar>
    );
}

function navigationFor(dashboard: DashboardContext): NavItem[] {
    if (dashboard.role === 'student') {
        return studentNavigation(dashboard.levels);
    }

    return staffNavigation();
}

function staffNavigation(): NavItem[] {
    return [
        nav('Home', 'home', dashboardHome(), RiHome5Line),
        nav(
            'Students',
            'students',
            dashboardSection({ section: 'students' }),
            RiTeamLine,
        ),
        nav(
            'Course Offerings',
            'courses',
            dashboardSection({ section: 'courses' }),
            RiBookOpenLine,
        ),
        nav(
            'Course Registrations',
            'course-registration',
            dashboardSection({ section: 'course-registration' }),
            RiCalendarTodoLine,
        ),
        nav(
            'Result Management',
            'result-management',
            dashboardSection({ section: 'result-management' }),
            RiGraduationCapLine,
        ),
        nav('Dues', 'dues', dashboardSection({ section: 'dues' }), RiCashLine),
        nav(
            'Paystack Settings',
            'payment-settings',
            dashboardSection({ section: 'payment-settings' }),
            RiBankCardLine,
        ),
        nav(
            'Profile Settings',
            'profile-settings',
            dashboardSection({ section: 'profile-settings' }),
            RiSettings3Line,
        ),
    ];
}

function studentNavigation(levels: DashboardLevel[]): NavItem[] {
    return [
        nav('Home', 'home', dashboardHome(), RiHome5Line),
        nav(
            'Results',
            'results',
            undefined,
            RiGraduationCapLine,
            levelLinks('results', levels),
        ),
        nav('Dues', 'dues', dashboardSection({ section: 'dues' }), RiCashLine),
        nav(
            'Course Registration',
            'course-registration',
            dashboardSection({ section: 'course-registration' }),
            RiCalendarTodoLine,
        ),
        nav(
            'Profile Settings',
            'profile-settings',
            dashboardSection({ section: 'profile-settings' }),
            RiUser3Line,
        ),
    ];
}

function nav(
    title: string,
    section: string,
    href: DashboardRoute | undefined,
    icon: ComponentType<{ className?: string }>,
    items?: NavSubItem[],
): NavItem {
    return { title, section, href, icon, items };
}

function levelLinks(
    section: 'results',
    levels: DashboardLevel[],
): NavSubItem[] {
    return levels.map((level) => ({
        title: level.title,
        section,
        levelSlug: level.slug,
        href: dashboardLevel({
            level: level.slug,
        }),
    }));
}

function isActive(item: ActiveItem, dashboard: DashboardContext): boolean {
    if (item.section !== dashboard.section) {
        return false;
    }

    if (
        item.levelSlug !== undefined &&
        item.levelSlug !== dashboard.levelSlug
    ) {
        return false;
    }

    return true;
}
