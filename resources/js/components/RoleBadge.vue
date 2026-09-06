<script setup lang="ts">
import { computed } from 'vue';
import { useTrans } from '@/composables/useTrans';
import type { UserRole } from '@/types';

const props = withDefaults(
    defineProps<{
        role: UserRole;
        size?: 'sm' | 'md';
    }>(),
    {
        size: 'sm',
    },
);

const { __ } = useTrans();

const label = computed(() => {
    const labels: Record<UserRole, string> = {
        admin: __('Administrator'),
        manager: __('Manager'),
        team_leader: __('Team Leader'),
        user: __('Operator / User'),
    };
    return labels[props.role] ?? props.role;
});

const variantClasses = computed(() => {
    switch (props.role) {
        case 'admin':
            return 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border-purple-200 dark:border-purple-800';
        case 'manager':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-200 dark:border-blue-800';
        case 'team_leader':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800';
        case 'user':
        default:
            return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border-slate-200 dark:border-slate-700';
    }
});

const sizeClasses = computed(() => {
    return props.size === 'md'
        ? 'px-2.5 py-1 text-xs font-semibold'
        : 'px-2 py-0.5 text-[11px] font-medium';
});
</script>

<template>
    <span
        :class="[
            'inline-flex items-center rounded-md border tracking-wide select-none',
            variantClasses,
            sizeClasses,
        ]"
    >
        {{ label }}
    </span>
</template>
