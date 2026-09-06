<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LayoutGrid } from '@lucide/vue';
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
import type { NavItem } from '@/types';

const { __ } = useTrans();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: __('Dashboard'),
        href: dashboard(),
        icon: LayoutGrid,
    },
]);
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
