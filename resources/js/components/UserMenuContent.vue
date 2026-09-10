<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Building2, Globe, Layers, LogOut, Settings } from '@lucide/vue';
import { ref } from 'vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import { useTrans } from '@/composables/useTrans';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

defineProps<Props>();

const { __ } = useTrans();
const isConfirmingLogout = ref(false);

const handleLogout = () => {
    router.flushAll();
};
</script>

<template>
    <div>
        <DropdownMenuLabel class="p-0 font-normal">
            <div class="flex items-center gap-2 px-2 py-2 text-left text-sm">
                <UserInfo
                    :user="user"
                    :show-email="true"
                    :show-npk="true"
                    :show-role="true"
                />
            </div>
        </DropdownMenuLabel>

        <!-- Department & Section Assignment Info -->
        <div
            class="text-muted-foreground bg-muted/40 border-border/40 mx-1 my-1 rounded-md border px-2 py-1.5 text-xs"
        >
            <div class="flex items-center gap-1.5 truncate py-0.5">
                <Building2 class="text-muted-foreground/70 size-3.5 shrink-0" />
                <span class="text-foreground/80 font-medium"
                    >{{ __('Department') }}:</span
                >
                <span class="truncate">{{
                    user.department?.name ?? __('Not Assigned')
                }}</span>
            </div>
            <div class="flex items-center gap-1.5 truncate py-0.5">
                <Layers class="text-muted-foreground/70 size-3.5 shrink-0" />
                <span class="text-foreground/80 font-medium"
                    >{{ __('Section') }}:</span
                >
                <span class="truncate">{{
                    user.section?.name ?? __('Not Assigned')
                }}</span>
            </div>
        </div>

        <DropdownMenuSeparator />

        <DropdownMenuGroup>
            <DropdownMenuItem :as-child="true">
                <Link
                    class="block w-full cursor-pointer"
                    :href="edit()"
                    prefetch
                >
                    <Settings class="mr-2 h-4 w-4" />
                    {{ __('Settings') }}
                </Link>
            </DropdownMenuItem>
        </DropdownMenuGroup>

        <DropdownMenuSeparator />

        <!-- Language Preference Section -->
        <div
            class="text-muted-foreground flex items-center justify-between px-2 py-1.5 text-xs"
        >
            <div class="flex items-center gap-1.5 font-medium">
                <Globe class="text-muted-foreground/70 size-3.5" />
                <span>{{ __('Language') }}</span>
            </div>
            <LanguageSwitcher
                size="sm"
                test-id-prefix="lang-switch-user-menu"
            />
        </div>

        <DropdownMenuSeparator />

        <!-- Sign Out with Inline Confirmation -->
        <div
            v-if="isConfirmingLogout"
            class="bg-destructive/5 border-destructive/20 mx-1 mb-1 space-y-2 rounded-md border p-2"
        >
            <p class="text-muted-foreground text-xs leading-relaxed">
                {{
                    __(
                        'Sign out of your account? Please ensure your current shift tasks are saved before leaving.',
                    )
                }}
            </p>
            <div class="flex items-center justify-end gap-2 pt-1">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-7 cursor-pointer px-2 text-xs"
                    @click.stop.prevent="isConfirmingLogout = false"
                >
                    {{ __('Cancel') }}
                </Button>
                <Link
                    class="bg-destructive text-destructive-foreground hover:bg-destructive/90 inline-flex h-7 cursor-pointer items-center justify-center rounded-md px-2.5 text-xs font-medium"
                    :href="logout()"
                    @click="handleLogout"
                    method="post"
                    as="button"
                    data-test="confirm-logout-button"
                >
                    {{ __('Yes, Sign Out') }}
                </Link>
            </div>
        </div>

        <div v-else class="px-1 py-1">
            <button
                type="button"
                class="text-destructive hover:bg-destructive/10 focus:bg-destructive/10 flex w-full cursor-pointer items-center rounded-sm px-2 py-1.5 text-sm transition-colors"
                @click.stop.prevent="isConfirmingLogout = true"
                data-test="logout-button"
            >
                <LogOut class="mr-2 h-4 w-4" />
                <span>{{ __('Sign Out') }}</span>
            </button>
        </div>
    </div>
</template>
