import { Link } from '@inertiajs/react';
import {
    RiBankCardLine,
    RiBookOpenLine,
    RiCalendarTodoLine,
    RiCashLine,
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
    SidebarRail,
} from '@/components/ui/sidebar';
import { dashboardSidebarRoleLabel } from '@/lib/dashboard-labels';
import { initialsFor } from '@/lib/person';
import type { Auth, DashboardContext } from '@/types';
import type { RouteDefinition } from '@/wayfinder';

type DashboardRoute = RouteDefinition<'get'>;

type NavItem = {
    title: string;
    section: string;
    href: DashboardRoute;
    icon: ComponentType<{ className?: string }>;
};

type ActiveItem = {
    section: string;
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
        return studentNavigation();
    }

    return adviserNavigation();
}

function adviserNavigation(): NavItem[] {
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

function studentNavigation(): NavItem[] {
    return [
        nav('Home', 'home', dashboardHome(), RiHome5Line),
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
    href: DashboardRoute,
    icon: ComponentType<{ className?: string }>,
): NavItem {
    return { title, section, href, icon };
}

function isActive(item: ActiveItem, dashboard: DashboardContext): boolean {
    if (item.section !== dashboard.section) {
        return false;
    }

    return true;
}
