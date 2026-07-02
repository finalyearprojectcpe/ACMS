import { Link } from '@inertiajs/react';
import {
    RiBookOpenLine,
    RiBuilding2Line,
    RiBuildingLine,
    RiCalendarEventLine,
    RiCalendarTodoLine,
    RiCashLine,
    RiCheckboxCircleLine,
    RiFileChartLine,
    RiGraduationCapLine,
    RiHome5Line,
    RiLogoutBoxRLine,
    RiSettings3Line,
    RiShieldUserLine,
    RiTeamLine,
    RiUser3Line,
    RiUserSettingsLine,
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
    if (dashboard.role === 'school_admin') {
        return schoolAdminNavigation();
    }

    if (dashboard.role === 'faculty_head') {
        return facultyHeadNavigation();
    }

    if (dashboard.role === 'student') {
        return studentNavigation(dashboard.levels);
    }

    if (dashboard.role === 'hod') {
        return hodNavigation();
    }

    return staffNavigation(dashboard.levels, dashboard.courseScheduleLevels);
}

function schoolAdminNavigation(): NavItem[] {
    return [
        nav('Home', 'home', dashboardHome(), RiHome5Line),
        nav(
            'Academic Sessions',
            'academic-sessions',
            dashboardSection({ section: 'academic-sessions' }),
            RiCalendarEventLine,
        ),
        nav(
            'School Structure',
            'structure',
            dashboardSection({ section: 'structure' }),
            RiBuilding2Line,
        ),
        nav(
            'Faculty Heads',
            'faculty-heads',
            dashboardSection({ section: 'faculty-heads' }),
            RiShieldUserLine,
        ),
        nav(
            'Settings',
            'settings',
            dashboardSection({ section: 'settings' }),
            RiSettings3Line,
        ),
    ];
}

function facultyHeadNavigation(): NavItem[] {
    return [
        nav('Home', 'home', dashboardHome(), RiHome5Line),
        nav(
            'Departments',
            'departments',
            dashboardSection({ section: 'departments' }),
            RiBuildingLine,
        ),
        nav(
            'Staff',
            'staff',
            dashboardSection({ section: 'staff' }),
            RiTeamLine,
        ),
        nav(
            'Sessions & Courses',
            'sessions-courses',
            dashboardSection({ section: 'sessions-courses' }),
            RiCalendarTodoLine,
        ),
        nav(
            'Settings',
            'settings',
            dashboardSection({ section: 'settings' }),
            RiSettings3Line,
        ),
    ];
}

function staffNavigation(
    levels: DashboardLevel[],
    courseScheduleLevels: DashboardLevel[],
): NavItem[] {
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
            undefined,
            RiBookOpenLine,
            levelLinks('courses', courseScheduleLevels),
        ),
        nav(
            'Grades',
            'grades',
            undefined,
            RiGraduationCapLine,
            levelLinks('grades', levels),
        ),
        nav('Dues', 'dues', undefined, RiCashLine, dueLinks(levels)),
        nav(
            'Reports',
            'reports',
            dashboardSection({ section: 'reports' }),
            RiFileChartLine,
        ),
        nav(
            'Approvals',
            'approvals',
            dashboardSection({ section: 'approvals' }),
            RiCheckboxCircleLine,
        ),
        nav(
            'Settings',
            'settings',
            dashboardSection({ section: 'settings' }),
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
            'Course schedules',
            'course-schedules',
            dashboardSection({ section: 'course-schedules' }),
            RiCalendarTodoLine,
        ),
        nav(
            'Profile',
            'profile',
            dashboardSection({ section: 'profile' }),
            RiUser3Line,
        ),
    ];
}

function hodNavigation(): NavItem[] {
    return [
        nav('Home', 'home', dashboardHome(), RiHome5Line),
        nav(
            'Staff',
            'staff',
            dashboardSection({ section: 'staff' }),
            RiUserSettingsLine,
        ),
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
            'Student Results',
            'student-results',
            dashboardSection({ section: 'student-results' }),
            RiGraduationCapLine,
        ),
        nav(
            'Settings',
            'settings',
            dashboardSection({ section: 'settings' }),
            RiSettings3Line,
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
    section: 'courses' | 'grades' | 'results',
    levels: DashboardLevel[],
): NavSubItem[] {
    return levels.map((level) => ({
        title: level.title,
        section,
        levelSlug: level.slug,
        href: dashboardLevel({
            section,
            level: level.slug,
        }),
    }));
}

function dueLinks(levels: DashboardLevel[]): NavSubItem[] {
    return levels.map((level) => ({
        title: level.title,
        section: 'dues',
        levelSlug: level.slug,
        href: dashboardLevel({
            section: 'dues',
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
