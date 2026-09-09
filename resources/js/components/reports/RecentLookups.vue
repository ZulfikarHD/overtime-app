<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Clock, Trash2, User as UserIcon } from '@lucide/vue';
import {
    useRecentLookups,
    type RecentLookupEmployee,
} from '@/composables/useRecentLookups';
import { useTrans } from '@/composables/useTrans';
import { show } from '@/routes/reports/employees';

const { recentLookups, clearLookups } = useRecentLookups();
const { __ } = useTrans();

const selectRecent = (employee: RecentLookupEmployee) => {
    router.visit(show.url({ npk: employee.npk }));
};
</script>

<template>
    <div
        v-if="recentLookups.length > 0"
        data-test="recent-lookups"
        class="flex flex-col gap-2"
    >
        <div
            class="text-muted-foreground flex items-center justify-between text-xs"
        >
            <div class="flex items-center gap-1.5 font-medium">
                <Clock class="text-muted-foreground size-3.5" />
                <span>{{ __('Pencarian Terakhir') }}</span>
            </div>

            <button
                type="button"
                data-test="recent-lookups-clear"
                class="text-muted-foreground hover:text-destructive flex items-center gap-1 text-[11px] transition-colors"
                @click="clearLookups"
            >
                <Trash2 class="size-3" />
                <span>{{ __('Hapus Riwayat') }}</span>
            </button>
        </div>

        <div
            class="flex scrollbar-none items-center gap-2 overflow-x-auto pb-1"
        >
            <button
                v-for="employee in recentLookups"
                :key="employee.npk"
                type="button"
                data-test="recent-lookup-pill"
                class="border-border bg-card hover:border-primary/50 hover:bg-muted/50 hover:text-primary inline-flex shrink-0 cursor-pointer items-center gap-2 rounded-full border px-3 py-1 text-xs font-normal shadow-2xs transition-all"
                @click="selectRecent(employee)"
            >
                <div
                    class="bg-primary/10 text-primary flex size-5 shrink-0 items-center justify-center rounded-full"
                >
                    <UserIcon class="size-3" />
                </div>

                <span
                    class="text-foreground max-w-[140px] truncate font-medium"
                >
                    {{ employee.name }}
                </span>

                <span
                    class="text-muted-foreground bg-muted py-0.2 rounded px-1.5 font-mono text-[11px] tabular-nums"
                >
                    {{ employee.npk }}
                </span>
            </button>
        </div>
    </div>
</template>
