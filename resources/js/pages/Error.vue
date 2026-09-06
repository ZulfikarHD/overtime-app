<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ArrowLeft, ShieldAlert } from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import { dashboard } from '@/routes';
import type { UserRole } from '@/types';

const props = defineProps<{
    status?: number;
    message?: string;
}>();

const { __ } = useTrans();
const page = usePage();

const userRole = computed(() => {
    const role =
        (page.props.auth?.user?.role as UserRole | undefined) ?? 'user';
    const roleLabels: Record<string, string> = {
        admin: 'Administrator',
        manager: 'Manager',
        team_leader: 'Team Leader',
        user: 'Operator / User',
    };
    return roleLabels[role] ?? role;
});

const displayMessage = computed(() => {
    if (props.message && props.message.trim() !== '') {
        return props.message;
    }

    return __(
        'Your account (:role) does not have permission to view this section. Please return to Dashboard.',
        { role: userRole.value },
    );
});
</script>

<template>
    <div
        class="flex min-h-[70vh] flex-col items-center justify-center p-6 text-center"
    >
        <Head :title="__('Access Restricted')" />

        <div class="mx-auto flex max-w-md flex-col items-center">
            <!-- Security / Lock Shield Icon -->
            <div
                class="mb-6 flex size-20 items-center justify-center rounded-2xl border border-amber-500/20 bg-amber-500/10 text-amber-600 shadow-sm dark:text-amber-400"
            >
                <ShieldAlert class="size-10" />
            </div>

            <!-- Friendly Title -->
            <h1
                class="text-foreground text-2xl font-bold tracking-tight sm:text-3xl"
            >
                {{ __('Access Restricted') }}
            </h1>

            <!-- Descriptive Human-Friendly Copy -->
            <p
                class="text-muted-foreground mt-3 text-sm leading-relaxed sm:text-base"
            >
                {{ displayMessage }}
            </p>

            <!-- Recovery Action -->
            <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                <Button
                    as-child
                    class="h-12 gap-2 px-6 text-base font-medium shadow-sm"
                >
                    <Link :href="dashboard()">
                        <ArrowLeft class="size-4" />
                        {{ __('Return to Dashboard') }}
                    </Link>
                </Button>
            </div>

            <!-- HR/IT Support Contact Hint -->
            <p class="text-muted-foreground/80 mt-8 text-xs">
                {{ __('Having trouble logging in? Contact HR / IT Admin') }}
            </p>
        </div>
    </div>
</template>
