<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { Clock } from '@lucide/vue';
import { computed } from 'vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import RoleBadge from '@/components/RoleBadge.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useShiftInfo } from '@/composables/useShiftInfo';
import type { BreadcrumbItem, User } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { timeString, currentShift } = useShiftInfo();
const page = usePage();
const user = computed(() => page.props.auth?.user as User | undefined);
</script>

<template>
    <header
        class="border-sidebar-border/70 flex h-16 shrink-0 items-center justify-between gap-2 border-b px-4 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-6"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1 shrink-0" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <!-- Factory Operational Indicators & User Identity Pill -->
        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <!-- Active Shift Badge -->
            <div
                class="hidden items-center gap-1.5 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-800 shadow-xs sm:inline-flex dark:text-emerald-300"
                data-test="active-shift-badge"
            >
                <span
                    class="size-1.5 animate-pulse rounded-full bg-emerald-500"
                />
                <span>{{ currentShift.badgeText }}</span>
            </div>

            <!-- Topbar Notification Bell (E03-05) -->
            <NotificationBell />

            <!-- Live WIB Clock -->
            <div
                class="bg-muted/60 text-muted-foreground border-border/50 inline-flex items-center gap-1.5 rounded-md border px-2 py-1 font-mono text-xs font-medium"
                data-test="live-wib-clock"
            >
                <Clock class="size-3.5 shrink-0" />
                <span>{{ timeString }} WIB</span>
            </div>

            <!-- Topbar Role Badge for active user -->
            <div
                v-if="user && user.role"
                class="border-border/60 hidden items-center gap-2 border-l pl-1 md:flex"
            >
                <RoleBadge
                    :role="user.role"
                    size="sm"
                    data-test="topbar-role-badge"
                />
                <span
                    v-if="user.npk"
                    class="text-muted-foreground font-mono text-xs"
                    >{{ user.npk }}</span
                >
            </div>
        </div>
    </header>
</template>
