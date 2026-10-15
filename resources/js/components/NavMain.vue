<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

const props = defineProps<{
    items: NavItem[];
}>();

const { isCurrentUrl, isCurrentOrParentUrl } = useCurrentUrl();

function isItemActive(item: NavItem): boolean {
    if (isCurrentUrl(item.href)) {
        return true;
    }

    if (!item.matchChildren) {
        return false;
    }

    // Trailing slash keeps `/early-warning` from matching `/early-warning-archive`.
    return isCurrentOrParentUrl(`${toUrl(item.href).replace(/\/$/, '')}/`);
}

function hasBadge(item: NavItem): boolean {
    return (
        item.badge !== null &&
        item.badge !== undefined &&
        item.badge !== '' &&
        Number(item.badge) > 0
    );
}

function badgeLabel(item: NavItem): string {
    return Number(item.badge) > 99 ? '99+' : String(item.badge);
}

const groupedItems = computed(() => {
    const groups: { name: string; items: NavItem[] }[] = [];
    const groupMap = new Map<string, NavItem[]>();

    for (const item of props.items) {
        const groupName = item.group || 'Platform';
        if (!groupMap.has(groupName)) {
            const list: NavItem[] = [];
            groupMap.set(groupName, list);
            groups.push({ name: groupName, items: list });
        }
        groupMap.get(groupName)!.push(item);
    }

    return groups;
});
</script>

<template>
    <div class="space-y-4">
        <SidebarGroup
            v-for="grp in groupedItems"
            :key="grp.name"
            class="px-2 py-0"
        >
            <SidebarGroupLabel
                class="px-3 text-[11px] font-bold tracking-wider text-slate-400 uppercase group-data-[collapsible=icon]:hidden dark:text-slate-500"
            >
                {{ grp.name }}
            </SidebarGroupLabel>
            <SidebarMenu class="space-y-0.5">
                <SidebarMenuItem v-for="item in grp.items" :key="item.title">
                    <SidebarMenuButton
                        as-child
                        :is-active="isItemActive(item)"
                        :tooltip="item.title"
                        class="rounded-lg text-slate-700 transition-colors hover:bg-red-50 hover:text-[#cc0000] data-[active=true]:bg-red-50 data-[active=true]:font-semibold data-[active=true]:text-[#cc0000] dark:text-slate-300 dark:hover:bg-red-950/40 dark:hover:text-red-300 dark:data-[active=true]:bg-red-950/40 dark:data-[active=true]:text-red-300"
                    >
                        <Link
                            :href="item.href"
                            :data-test="
                                item.testId ??
                                'nav-' +
                                    item.title
                                        .toLowerCase()
                                        .replace(/\s+/g, '-')
                            "
                        >
                            <component
                                :is="item.icon"
                                class="size-4 shrink-0"
                            />
                            <span class="truncate">{{ item.title }}</span>
                            <span
                                v-if="isItemActive(item) && !hasBadge(item)"
                                class="ml-auto size-1.5 rounded-full bg-[#cc0000] group-data-[collapsible=icon]:hidden"
                            />
                        </Link>
                    </SidebarMenuButton>
                    <SidebarMenuBadge
                        v-if="hasBadge(item)"
                        class="bg-[#cc0000] font-mono text-[10px] text-white tabular-nums group-data-[collapsible=icon]:hidden"
                        :data-test="`${item.testId ?? 'nav'}-badge`"
                    >
                        {{ badgeLabel(item) }}
                    </SidebarMenuBadge>
                    <span
                        v-if="hasBadge(item)"
                        class="pointer-events-none absolute top-1 right-1 hidden size-2 rounded-full bg-[#cc0000] ring-2 ring-white group-data-[collapsible=icon]:block dark:ring-slate-900"
                        :data-test="`${item.testId ?? 'nav'}-dot`"
                        aria-hidden="true"
                    />
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarGroup>
    </div>
</template>
