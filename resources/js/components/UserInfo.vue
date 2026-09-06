<script setup lang="ts">
import { computed } from 'vue';
import RoleBadge from '@/components/RoleBadge.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
    showNpk?: boolean;
    showRole?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
    showNpk: true,
    showRole: true,
});

const { getInitials } = useInitials();

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);
</script>

<template>
    <Avatar class="h-8 w-8 overflow-hidden rounded-lg">
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback
            class="rounded-lg font-medium text-black dark:text-white"
        >
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>

    <div class="grid flex-1 gap-0.5 text-left text-sm leading-tight">
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="truncate font-medium">{{ user.name }}</span>
            <RoleBadge v-if="showRole && user.role" :role="user.role" />
        </div>
        <div
            class="text-muted-foreground flex items-center gap-2 truncate text-xs"
        >
            <span v-if="showNpk && user.npk" class="font-mono">{{
                user.npk
            }}</span>
            <span v-if="showEmail" class="truncate">{{ user.email }}</span>
        </div>
    </div>
</template>
