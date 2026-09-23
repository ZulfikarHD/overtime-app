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
        class="border-sidebar-border/70 flex min-h-14 shrink-0 items-center justify-between gap-2 border-b px-3 py-1.5 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:min-h-12 sm:min-h-16 sm:px-4 md:px-6"
    >
        <div class="flex min-w-0 items-center gap-2">
            <SidebarTrigger class="-ml-1 shrink-0" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <!-- Factory Operational Indicators & User Identity Pill -->
        <div class="flex shrink-0 items-center gap-1.5 sm:gap-3">
            <!-- Topbar Notification Bell (E03-05) -->
            <NotificationBell />

            <!-- Live WIB Clock — time first, shift below (shift hidden on xs) -->
            <div
                class="bg-muted/60 text-muted-foreground border-border/50 flex flex-col rounded-md border px-1.5 py-1 sm:px-2"
                data-test="live-wib-clock"
            >
                <div
                    class="inline-flex items-center gap-1 font-mono text-[11px] font-semibold text-slate-700 tabular-nums sm:gap-1.5 sm:text-xs dark:text-slate-200"
                >
                    <Clock class="size-3 shrink-0 sm:size-3.5" />
                    <span class="whitespace-nowrap">{{ timeString }} WIB</span>
                </div>
                <span
                    class="hidden pl-4 text-[10px] font-medium tracking-wide min-[400px]:block sm:pl-5"
                    data-test="topbar-active-shift"
                >
                    {{ currentShift.name }}
                </span>
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
