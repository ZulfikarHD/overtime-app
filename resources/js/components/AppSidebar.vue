<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Calculator,
    ClipboardCheck,
    ClipboardList,
    Database,
    Flame,
    FolderKanban,
    LayoutGrid,
    ShieldCheck,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
} from '@/components/ui/sidebar';
import { useTrans } from '@/composables/useTrans';
import { dashboard } from '@/routes';
import { dashboard as myDashboard } from '@/routes/my';
import { administration, masterData } from '@/routes/admin';
import capexProjects from '@/routes/admin/capex-projects';
import { planning } from '@/routes/budgets';
import { burnIndex } from '@/routes/dashboard';
import { index as analyticsIndex } from '@/routes/analytics';
import { approvals as overtimeApprovals } from '@/routes/overtime';
import { index as reportsEmployees } from '@/routes/reports/employees';
import { create as overtimeCreate } from '@/routes/overtime/submissions';
import type { NavItem, User } from '@/types';

const { __ } = useTrans();
const page = usePage();
const user = computed(() => page.props.auth?.user as User | undefined);
const pendingApprovalsCount = computed(
    () => (page.props.pending_approvals_count as number | undefined) ?? 0,
);

const mainNavItems = computed<NavItem[]>(() => {
    const isOperator = user.value?.role === 'user';
    const role = user.value?.role;
    const items: NavItem[] = [];

    // Group 1: Operations & Overtime
    items.push({
        title: __('Dashboard'),
        href: isOperator ? myDashboard() : dashboard(),
        icon: LayoutGrid,
        group: __('Operations & Overtime'),
        testId: 'nav-dashboard',
    });

    if (role === 'admin' || role === 'team_leader') {
        items.push({
            title: __('Overtime Entry'),
            href: overtimeCreate(),
            icon: ClipboardList,
            group: __('Operations & Overtime'),
            testId: 'nav-overtime-entry',
        });
    }

    if (role === 'admin' || role === 'manager') {
        items.push({
            title: __('Overtime Approvals'),
            href: overtimeApprovals(),
            icon: ClipboardCheck,
            badge: pendingApprovalsCount.value,
            testId: 'nav-overtime-approvals',
            group: __('Operations & Overtime'),
        });
    }

    if (role === 'admin' || role === 'manager' || role === 'team_leader') {
        items.push({
            title: __('Employee Reports'),
            href: reportsEmployees(),
            icon: Users,
            testId: 'nav-employee-reports',
            group: __('Operations & Overtime'),
        });
    }

    // Group 2: Financial & Governance
    if (role === 'admin' || role === 'manager') {
        items.push({
            title: __('CapEx Projects'),
            href: capexProjects.index(),
            icon: FolderKanban,
            testId: 'nav-capex-projects',
            group: __('Financial & Governance'),
        });
        items.push({
            title: __('Burn Index'),
            href: burnIndex(),
            icon: Flame,
            testId: 'nav-burn-index',
            group: __('Financial & Governance'),
        });
        items.push({
            title: __('Analytics & Decisions'),
            href: analyticsIndex(),
            icon: TrendingUp,
            testId: 'nav-analytics',
            group: __('Financial & Governance'),
        });
        items.push({
            title: __('Budget Planning'),
            href: planning(),
            icon: Calculator,
            group: __('Financial & Governance'),
            testId: 'nav-budget-planning',
        });
    }

    // Group 3: System & Configuration
    if (role === 'admin') {
        items.push({
            title: __('Master Data'),
            href: masterData(),
            icon: Database,
            group: __('System & Configuration'),
            testId: 'nav-master-data',
        });
        items.push({
            title: __('Administration'),
            href: administration(),
            icon: ShieldCheck,
            group: __('System & Configuration'),
            testId: 'nav-administration',
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader class="border-sidebar-border/60 border-b p-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="h-auto p-0 hover:bg-transparent"
                    >
                        <Link
                            :href="
                                user?.role === 'user'
                                    ? myDashboard()
                                    : dashboard()
                            "
                            class="flex items-center"
                        >
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent class="py-2">
            <NavMain :items="mainNavItems" />

            <!-- Plant Badge Quick Info (from public/style-guide.html lines 351-380) -->
            <div
                class="mx-3 mt-6 mb-2 space-y-2 rounded-xl border border-slate-200 bg-slate-50/70 p-3 text-xs shadow-2xs group-data-[collapsible=icon]:hidden dark:border-slate-800 dark:bg-slate-900/60"
            >
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px]">{{ __('Facility') }}:</span>
                    <span
                        class="text-[11px] font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ __('Karawang Assembly') }}
                    </span>
                </div>
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px]">{{ __('Shift System') }}:</span>
                    <span
                        class="text-[11px] font-semibold text-slate-800 dark:text-slate-200"
                    >
                        {{ __('3 Shifts / 24 Hours') }}
                    </span>
                </div>
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px]"
                        >{{ __('Statutory Limit') }}:</span
                    >
                    <span
                        class="text-[11px] font-semibold text-[#cc0000] dark:text-red-400"
                    >
                        {{ __('Max 14 Hours/Week') }}
                    </span>
                </div>
            </div>
        </SidebarContent>

        <SidebarFooter>
            <div
                class="text-muted-foreground/70 flex items-center justify-between px-3 py-1 text-[11px] group-data-[collapsible=icon]:hidden"
            >
                <span>{{ __('OT-CapEx System v1.0.0 · Karawang') }}</span>
                <LanguageSwitcher
                    size="sm"
                    test-id-prefix="lang-switch-sidebar"
                />
            </div>
            <NavUser />
        </SidebarFooter>

        <SidebarRail />
    </Sidebar>
    <slot />
</template>
