<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Calculator,
    ClipboardCheck,
    ClipboardList,
    Database,
    Flame,
    LayoutGrid,
    ShieldCheck,
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
} from '@/components/ui/sidebar';
import { useTrans } from '@/composables/useTrans';
import { dashboard } from '@/routes';
import { administration, masterData } from '@/routes/admin';
import { planning } from '@/routes/budgets';
import { burnIndex } from '@/routes/dashboard';
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
    const items: NavItem[] = [
        {
            title: __('Dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (user.value?.role === 'admin') {
        items.push({
            title: __('Master Data'),
            href: masterData(),
            icon: Database,
        });
        items.push({
            title: __('Administration'),
            href: administration(),
            icon: ShieldCheck,
        });
    }

    if (user.value?.role === 'admin' || user.value?.role === 'manager') {
        items.push({
            title: __('Burn Index'),
            href: burnIndex(),
            icon: Flame,
            testId: 'nav-burn-index',
        });
        items.push({
            title: __('Budget Planning'),
            href: planning(),
            icon: Calculator,
        });
        items.push({
            title: __('Persetujuan Lembur'),
            href: overtimeApprovals(),
            icon: ClipboardCheck,
            badge: pendingApprovalsCount.value,
            testId: 'nav-overtime-approvals',
        });
    }

    if (user.value?.role === 'admin' || user.value?.role === 'team_leader') {
        items.push({
            title: __('Input Lembur'),
            href: overtimeCreate(),
            icon: ClipboardList,
        });
    }

    if (
        user.value?.role === 'admin' ||
        user.value?.role === 'manager' ||
        user.value?.role === 'team_leader'
    ) {
        items.push({
            title: __('Laporan Karyawan'),
            href: reportsEmployees(),
            icon: Users,
            testId: 'nav-employee-reports',
        });
    }

    return items;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <div
                class="text-muted-foreground/70 px-3 py-1 text-[11px] group-data-[collapsible=icon]:hidden"
            >
                <span>OT-CapEx System v1.0.0</span>
            </div>
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
