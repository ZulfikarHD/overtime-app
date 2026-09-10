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

    // Group 1: Operasional & Lembur
    items.push({
        title: __('Dashboard'),
        href: isOperator ? myDashboard() : dashboard(),
        icon: LayoutGrid,
        group: __('Operasional & Lembur'),
    });

    if (role === 'admin' || role === 'team_leader') {
        items.push({
            title: __('Input Lembur'),
            href: overtimeCreate(),
            icon: ClipboardList,
            group: __('Operasional & Lembur'),
        });
    }

    if (role === 'admin' || role === 'manager') {
        items.push({
            title: __('Persetujuan Lembur'),
            href: overtimeApprovals(),
            icon: ClipboardCheck,
            badge: pendingApprovalsCount.value,
            testId: 'nav-overtime-approvals',
            group: __('Operasional & Lembur'),
        });
    }

    if (role === 'admin' || role === 'manager' || role === 'team_leader') {
        items.push({
            title: __('Laporan Karyawan'),
            href: reportsEmployees(),
            icon: Users,
            testId: 'nav-employee-reports',
            group: __('Operasional & Lembur'),
        });
    }

    // Group 2: Finansial & Tata Kelola
    if (role === 'admin' || role === 'manager') {
        items.push({
            title: __('Proyek CapEx'),
            href: capexProjects.index(),
            icon: FolderKanban,
            testId: 'nav-capex-projects',
            group: __('Finansial & Tata Kelola'),
        });
        items.push({
            title: __('Burn Index'),
            href: burnIndex(),
            icon: Flame,
            testId: 'nav-burn-index',
            group: __('Finansial & Tata Kelola'),
        });
        items.push({
            title: __('Analitik & Keputusan'),
            href: analyticsIndex(),
            icon: TrendingUp,
            testId: 'nav-analytics',
            group: __('Finansial & Tata Kelola'),
        });
        items.push({
            title: __('Budget Planning'),
            href: planning(),
            icon: Calculator,
            group: __('Finansial & Tata Kelola'),
        });
    }

    // Group 3: Sistem & Konfigurasi
    if (role === 'admin') {
        items.push({
            title: __('Master Data'),
            href: masterData(),
            icon: Database,
            group: __('Sistem & Konfigurasi'),
        });
        items.push({
            title: __('Administration'),
            href: administration(),
            icon: ShieldCheck,
            group: __('Sistem & Konfigurasi'),
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
                    <span class="text-[11px]">{{ __('Fasilitas') }}:</span>
                    <span
                        class="text-[11px] font-semibold text-slate-800 dark:text-slate-200"
                    >
                        Karawang Assembly
                    </span>
                </div>
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px]">{{ __('Sistem Shift') }}:</span>
                    <span
                        class="text-[11px] font-semibold text-slate-800 dark:text-slate-200"
                    >
                        3 Shift / 24 Jam
                    </span>
                </div>
                <div class="flex items-center justify-between text-slate-500">
                    <span class="text-[11px]"
                        >{{ __('Ambang Depnaker') }}:</span
                    >
                    <span
                        class="text-[11px] font-semibold text-[#cc0000] dark:text-red-400"
                    >
                        Maks 14 Jam/Minggu
                    </span>
                </div>
            </div>
        </SidebarContent>

        <SidebarFooter>
            <div
                class="text-muted-foreground/70 px-3 py-1 text-[11px] group-data-[collapsible=icon]:hidden"
            >
                <span>OT-CapEx System v1.0.0 · Karawang</span>
            </div>
            <NavUser />
        </SidebarFooter>

        <SidebarRail />
    </Sidebar>
    <slot />
</template>
