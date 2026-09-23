<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ClipboardList,
    Database,
    LayoutGrid,
    ShieldCheck,
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
import { index as reportsEmployees } from '@/routes/reports/employees';
import { index as planningOtIndex } from '@/routes/overtime/planning';
import { index as splIndex } from '@/routes/overtime/spl';
import type { NavItem, User } from '@/types';

const { __ } = useTrans();
const page = usePage();
const user = computed(() => page.props.auth?.user as User | undefined);

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
            title: __('Planning OT'),
            href: planningOtIndex(),
            icon: ClipboardList,
            group: __('Operations & Overtime'),
            testId: 'nav-planning-ot',
        });
    }

    if (role === 'admin' || role === 'manager') {
        items.push({
            title: __('Input Lembur (SPL)'),
            href: splIndex(),
            icon: ClipboardList,
            group: __('Operations & Overtime'),
            testId: 'nav-spl-import',
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

    // Group 2: System & Configuration
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

            <!-- Plant Badge Quick Info -->
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
            </div>
        </SidebarContent>

        <SidebarFooter>
            <div
                class="text-muted-foreground/70 flex items-center justify-between px-3 py-1 text-[11px] group-data-[collapsible=icon]:hidden"
            >
                <span>{{ __('OT System v1.0.0 · Karawang') }}</span>
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
