import { Link } from '@inertiajs/react';
import {
    RiAddLine,
    RiCalendarTodoLine,
    RiCashLine,
    RiHome5Line,
    RiLogoutBoxRLine,
    RiMagicLine,
    RiFileChartLine,
    RiSettings3Line,
    RiTeamLine,
    RiUser3Line,
} from '@remixicon/react';
import type { ComponentProps, ComponentType } from 'react';

import { destroy as logoutUser } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import {
    createStudentSet,
    home as dashboardHome,
    section as dashboardSection,
    showStudentSetDues,
    showCourseSchedule,
    showSetResults,
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
import { studentSetRouteParameters } from '@/lib/student-sets';
import type {
    Auth,
    DashboardContext,
    DashboardStudentSetContext,
} from '@/types';
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
    const profileSection =
        dashboard.role === 'adviser' ? 'settings' : 'profile-settings';

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
                                        ACMS
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
                    <SidebarMenu className="gap-3">
                        {dashboard.role === 'adviser' ? (
                            <AdviserNavigation
                                navigation={navigation}
                                dashboard={dashboard}
                            />
                        ) : (
                            navigation.map((item) => (
                                <NavigationItem
                                    key={item.title}
                                    item={item}
                                    dashboard={dashboard}
                                />
                            ))
                        )}
                    </SidebarMenu>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            tooltip={user.display_name}
                            asChild
                        >
                            <Link
                                href={dashboardSection({
                                    section: profileSection,
                                })}
                            >
                                <Avatar className="size-8">
                                    <AvatarImage
                                        src={user.avatar ?? undefined}
                                        alt={`${user.display_name} avatar`}
                                    />
                                    <AvatarFallback className="text-xs font-medium">
                                        {initialsFor(user.display_name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="flex min-w-0 flex-col leading-none">
                                    <span className="truncate font-medium">
                                        {user.display_name}
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
            'Courses',
            'courses',
            dashboardSection({ section: 'courses' }),
            RiCalendarTodoLine,
        ),
        nav(
            'Set Results',
            'set-results',
            dashboardSection({ section: 'set-results' }),
            RiFileChartLine,
        ),
        nav(
            'Student Dues',
            'student-dues',
            dashboardSection({ section: 'student-dues' }),
            RiCashLine,
        ),
        nav(
            'Settings',
            'settings',
            dashboardSection({ section: 'settings' }),
            RiSettings3Line,
        ),
        nav(
            'Ask AI',
            'assistant',
            dashboardSection({ section: 'assistant' }),
            RiMagicLine,
        ),
    ];
}

function studentNavigation(): NavItem[] {
    return [
        nav('Home', 'home', dashboardHome(), RiHome5Line),
        nav(
            'My Courses',
            'courses',
            dashboardSection({ section: 'courses' }),
            RiCalendarTodoLine,
        ),
        nav(
            'Results',
            'results',
            dashboardSection({ section: 'results' }),
            RiFileChartLine,
        ),
        nav('Dues', 'dues', dashboardSection({ section: 'dues' }), RiCashLine),
        nav(
            'Profile',
            'profile-settings',
            dashboardSection({ section: 'profile-settings' }),
            RiUser3Line,
        ),
        nav(
            'Ask AI',
            'assistant',
            dashboardSection({ section: 'assistant' }),
            RiMagicLine,
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
    return item.section === dashboard.section;
}

function AdviserNavigation({
    navigation,
    dashboard,
}: {
    navigation: NavItem[];
    dashboard: DashboardContext;
}) {
    const [home, ...remaining] = navigation;
    const coursesItem = remaining.find((item) => item.section === 'courses');
    const resultsItem = remaining.find(
        (item) => item.section === 'set-results',
    );
    const duesItem = remaining.find((item) => item.section === 'student-dues');
    const settingsItem = remaining.find((item) => item.section === 'settings');
    const assistantItem = remaining.find(
        (item) => item.section === 'assistant',
    );

    return (
        <>
            <NavigationItem item={home} dashboard={dashboard} />
            <SidebarMenuItem>
                <div className="flex h-8 items-center gap-2 px-2 text-sm font-medium text-sidebar-foreground group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0">
                    <RiTeamLine className="size-4 shrink-0" />
                    <span className="truncate group-data-[collapsible=icon]:hidden">
                        Student Sets
                    </span>
                </div>
                <SidebarMenuSub className="ml-8 translate-x-0">
                    {dashboard.advisedSets.map((studentSet) => (
                        <SidebarMenuSubItem key={studentSet.id}>
                            <SidebarMenuSubButton
                                asChild
                                isActive={
                                    dashboard.activeStudentSetId ===
                                        studentSet.id &&
                                    ['student-sets', 'students'].includes(
                                        dashboard.section,
                                    )
                                }
                            >
                                <Link
                                    href={studentSet.url}
                                    title={studentSet.name}
                                    prefetch
                                >
                                    <span>{studentSet.name}</span>
                                </Link>
                            </SidebarMenuSubButton>
                        </SidebarMenuSubItem>
                    ))}
                    <SidebarMenuSubItem className="flex justify-center">
                        <SidebarMenuSubButton
                            asChild
                            className="w-fit flex-none text-link opacity-80 hover:text-link hover:opacity-100 [&>svg]:text-link"
                        >
                            <Link href={createStudentSet()}>
                                <RiAddLine />
                                <span>Add a New Set</span>
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </SidebarMenuItem>
            {coursesItem ? (
                dashboard.advisedSets.length === 0 ? (
                    <NavigationItem item={coursesItem} dashboard={dashboard} />
                ) : (
                    <SidebarMenuItem>
                        <div className="flex h-8 items-center gap-2 px-2 text-sm font-medium text-sidebar-foreground group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0">
                            <RiCalendarTodoLine className="size-4 shrink-0" />
                            <span className="truncate group-data-[collapsible=icon]:hidden">
                                Courses
                            </span>
                        </div>
                        <AdviserSetSubmenu
                            dashboard={dashboard}
                            section="courses"
                            hrefForSet={(studentSet) =>
                                showCourseSchedule(
                                    studentSetRouteParameters(studentSet),
                                ).url
                            }
                        />
                    </SidebarMenuItem>
                )
            ) : null}
            {resultsItem ? (
                dashboard.advisedSets.length === 0 ? (
                    <NavigationItem item={resultsItem} dashboard={dashboard} />
                ) : (
                    <SidebarMenuItem>
                        <div className="flex h-8 items-center gap-2 px-2 text-sm font-medium text-sidebar-foreground group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0">
                            <RiFileChartLine className="size-4 shrink-0" />
                            <span className="truncate group-data-[collapsible=icon]:hidden">
                                Set Results
                            </span>
                        </div>
                        <AdviserSetSubmenu
                            dashboard={dashboard}
                            section="set-results"
                            hrefForSet={(studentSet) =>
                                showSetResults(
                                    studentSetRouteParameters(studentSet),
                                ).url
                            }
                        />
                    </SidebarMenuItem>
                )
            ) : null}
            {duesItem ? (
                dashboard.advisedSets.length === 0 ? (
                    <NavigationItem item={duesItem} dashboard={dashboard} />
                ) : (
                    <SidebarMenuItem>
                        <div className="flex h-8 items-center gap-2 px-2 text-sm font-medium text-sidebar-foreground group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:px-0">
                            <RiCashLine className="size-4 shrink-0" />
                            <span className="truncate group-data-[collapsible=icon]:hidden">
                                Student Dues
                            </span>
                        </div>
                        <AdviserSetSubmenu
                            dashboard={dashboard}
                            section="student-dues"
                            hrefForSet={(studentSet) =>
                                showStudentSetDues(
                                    studentSetRouteParameters(studentSet),
                                ).url
                            }
                        />
                    </SidebarMenuItem>
                )
            ) : null}
            {settingsItem ? (
                <NavigationItem item={settingsItem} dashboard={dashboard} />
            ) : null}
            {assistantItem ? (
                <NavigationItem item={assistantItem} dashboard={dashboard} />
            ) : null}
        </>
    );
}

function AdviserSetSubmenu({
    dashboard,
    section,
    hrefForSet,
}: {
    dashboard: DashboardContext;
    section: 'courses' | 'set-results' | 'student-dues';
    hrefForSet: (studentSet: DashboardStudentSetContext) => string;
}) {
    return (
        <SidebarMenuSub className="ml-8 translate-x-0">
            {dashboard.advisedSets.map((studentSet) => (
                <SidebarMenuSubItem key={studentSet.id}>
                    <SidebarMenuSubButton
                        asChild
                        isActive={
                            dashboard.section === section &&
                            dashboard.activeStudentSetId === studentSet.id
                        }
                    >
                        <Link
                            href={hrefForSet(studentSet)}
                            title={studentSet.name}
                            prefetch
                        >
                            <span>{studentSet.name}</span>
                        </Link>
                    </SidebarMenuSubButton>
                </SidebarMenuSubItem>
            ))}
        </SidebarMenuSub>
    );
}

function NavigationItem({
    item,
    dashboard,
}: {
    item: NavItem;
    dashboard: DashboardContext;
}) {
    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                tooltip={item.title}
                isActive={isActive(item, dashboard)}
                className="text-sm font-medium"
            >
                <Link href={item.href}>
                    <item.icon className="size-4" />
                    <span>{item.title}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}
