<script setup lang="ts">
import {
    ArrowUpDown,
    CheckCircle2,
    Clock,
    Download,
    FileSpreadsheet,
    Filter,
    ListTodo,
    Pencil,
    ShieldAlert,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import type { ActionItemTarget } from './ActionItemStatusModal.vue';

interface Props {
    items?: ActionItemTarget[];
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    items: () => [],
    loading: false,
});

const emit = defineEmits<{
    (e: 'select-item', item: ActionItemTarget): void;
    (e: 'export-csv'): void;
}>();

const { __ } = useTrans();

type StatusFilter = 'all' | 'pending' | 'in_progress' | 'resolved';
const activeStatusFilter = ref<StatusFilter>('all');
const searchQuery = ref('');

const filteredItems = computed(() => {
    let result = props.items;

    if (activeStatusFilter.value !== 'all') {
        result = result.filter(
            (item) => item.status === activeStatusFilter.value,
        );
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(
            (item) =>
                item.action_item.toLowerCase().includes(q) ||
                item.department.toLowerCase().includes(q) ||
                item.impact.toLowerCase().includes(q) ||
                item.id.toLowerCase().includes(q),
        );
    }

    return result;
});

const statusCounts = computed(() => {
    const all = props.items.length;
    const pending = props.items.filter((i) => i.status === 'pending').length;
    const inProgress = props.items.filter(
        (i) => i.status === 'in_progress',
    ).length;
    const resolved = props.items.filter((i) => i.status === 'resolved').length;

    return { all, pending, inProgress, resolved };
});

function exportActionPlanCsv() {
    emit('export-csv');
}
</script>

<template>
    <div data-test="management-action-table">
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader
                class="flex flex-col gap-3 pb-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <ListTodo class="size-5 text-[#cc0000]" />
                        <span>{{
                            __('Daftar Tindakan Manajemen (Action Plan)')
                        }}</span>
                    </CardTitle>
                    <CardDescription>
                        {{
                            __(
                                'Daftar prioritas mitigasi risiko yang dihasilkan secara otomatis dari deviasi anggaran, peringatan kelelahan, dan anomali lonjakan.',
                            )
                        }}
                    </CardDescription>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <!-- Export Action Plan Button -->
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 gap-1.5 border-slate-300 text-xs text-slate-700 shadow-2xs hover:bg-slate-100 hover:text-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                        data-test="btn-export-action-plan"
                        @click="exportActionPlanCsv"
                    >
                        <FileSpreadsheet
                            class="size-3.5 text-emerald-600 dark:text-emerald-400"
                        />
                        <span>{{ __('Ekspor Action Plan') }}</span>
                    </Button>
                </div>
            </CardHeader>

            <CardContent class="space-y-4">
                <!-- Filters Bar: Search & Status Pills -->
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                    data-test="action-table-filters"
                >
                    <!-- Status Filter Tabs / Pills -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold transition-all"
                            :class="
                                activeStatusFilter === 'all'
                                    ? 'bg-[#cc0000] text-white shadow-xs'
                                    : 'border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-300'
                            "
                            data-test="filter-status-all"
                            @click="activeStatusFilter = 'all'"
                        >
                            <span>{{ __('Semua') }}</span>
                            <span
                                class="py-0.2 rounded-full bg-white/20 px-1.5 font-mono text-[10px] tabular-nums"
                            >
                                {{ statusCounts.all }}
                            </span>
                        </button>

                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold transition-all"
                            :class="
                                activeStatusFilter === 'pending'
                                    ? 'bg-slate-800 text-white shadow-xs dark:bg-slate-200 dark:text-slate-900'
                                    : 'border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-300'
                            "
                            data-test="filter-status-pending"
                            @click="activeStatusFilter = 'pending'"
                        >
                            <span>{{ __('Tertunda') }}</span>
                            <span
                                class="py-0.2 rounded-full bg-slate-500/20 px-1.5 font-mono text-[10px] tabular-nums"
                            >
                                {{ statusCounts.pending }}
                            </span>
                        </button>

                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold transition-all"
                            :class="
                                activeStatusFilter === 'in_progress'
                                    ? 'bg-amber-600 text-white shadow-xs'
                                    : 'border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-300'
                            "
                            data-test="filter-status-in-progress"
                            @click="activeStatusFilter = 'in_progress'"
                        >
                            <span>{{ __('Dalam Pengerjaan') }}</span>
                            <span
                                class="py-0.2 rounded-full bg-amber-200/30 px-1.5 font-mono text-[10px] tabular-nums"
                            >
                                {{ statusCounts.inProgress }}
                            </span>
                        </button>

                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-md px-2.5 py-1 text-xs font-semibold transition-all"
                            :class="
                                activeStatusFilter === 'resolved'
                                    ? 'bg-emerald-600 text-white shadow-xs'
                                    : 'border border-slate-200 bg-slate-50 text-slate-600 hover:bg-slate-100 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-300'
                            "
                            data-test="filter-status-resolved"
                            @click="activeStatusFilter = 'resolved'"
                        >
                            <span>{{ __('Selesai') }}</span>
                            <span
                                class="py-0.2 rounded-full bg-emerald-200/30 px-1.5 font-mono text-[10px] tabular-nums"
                            >
                                {{ statusCounts.resolved }}
                            </span>
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full sm:w-64">
                        <input
                            v-model="searchQuery"
                            type="text"
                            :placeholder="
                                __('Cari tindakan atau departemen...')
                            "
                            class="h-8 w-full rounded-md border border-slate-300 bg-white px-2.5 text-xs text-slate-900 shadow-2xs focus:border-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            data-test="input-search-action-items"
                        />
                    </div>
                </div>

                <!-- High-Density Data Table -->
                <div
                    class="overflow-x-auto rounded-lg border border-slate-200 dark:border-slate-800"
                >
                    <table class="w-full text-left text-xs">
                        <thead
                            class="border-b border-slate-200 bg-slate-50 font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-800/60 dark:text-slate-300"
                        >
                            <tr>
                                <th class="w-24 px-3.5 py-2.5 text-center">
                                    {{ __('Prioritas') }}
                                </th>
                                <th class="px-3.5 py-2.5">
                                    {{ __('Tindakan Manajemen') }}
                                </th>
                                <th class="w-36 px-3.5 py-2.5">
                                    {{ __('Departemen') }}
                                </th>
                                <th class="hidden px-3.5 py-2.5 md:table-cell">
                                    {{ __('Estimasi Dampak') }}
                                </th>
                                <th class="w-28 px-3.5 py-2.5 text-center">
                                    {{ __('Batas Waktu') }}
                                </th>
                                <th class="w-36 px-3.5 py-2.5 text-center">
                                    {{ __('Status') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-200 dark:divide-slate-800"
                        >
                            <!-- Empty State -->
                            <tr
                                v-if="filteredItems.length === 0"
                                data-test="action-table-empty-row"
                            >
                                <td
                                    colspan="6"
                                    class="p-8 text-center text-slate-500"
                                >
                                    <div
                                        class="flex flex-col items-center justify-center gap-1.5"
                                    >
                                        <CheckCircle2
                                            class="size-6 text-emerald-600 dark:text-emerald-400"
                                        />
                                        <span class="font-medium">{{
                                            __(
                                                'Tidak ada tindakan manajemen dalam filter ini.',
                                            )
                                        }}</span>
                                        <span
                                            class="text-[11px] text-slate-400"
                                            >{{
                                                __(
                                                    'Semua item telah diselesaikan atau tidak ada risiko aktif yang terdeteksi.',
                                                )
                                            }}</span
                                        >
                                    </div>
                                </td>
                            </tr>

                            <!-- Data Rows -->
                            <tr
                                v-for="item in filteredItems"
                                :key="item.id"
                                class="transition-colors hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
                                data-test="action-item-row"
                            >
                                <!-- Priority -->
                                <td class="px-3.5 py-2.5 text-center">
                                    <Badge
                                        :variant="
                                            item.priority === 'high'
                                                ? 'destructive'
                                                : item.priority === 'medium'
                                                  ? 'secondary'
                                                  : 'outline'
                                        "
                                        class="px-2 py-0.5 text-[10px] font-bold uppercase"
                                        :class="
                                            item.priority === 'high'
                                                ? 'border-red-200 bg-red-50 text-[#cc0000] dark:border-red-900 dark:bg-red-950/40 dark:text-red-300'
                                                : item.priority === 'medium'
                                                  ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300'
                                                  : 'border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                        "
                                    >
                                        {{ item.priority_label }}
                                    </Badge>
                                </td>

                                <!-- Action Item Title & Notes -->
                                <td class="px-3.5 py-2.5">
                                    <div
                                        class="font-semibold text-slate-900 dark:text-white"
                                        data-test="action-item-title"
                                    >
                                        {{ item.action_item }}
                                    </div>
                                    <div
                                        v-if="item.resolution_note"
                                        class="mt-0.5 flex items-center gap-1 text-[11px] text-emerald-700 dark:text-emerald-400"
                                    >
                                        <span class="font-semibold">{{
                                            __('Catatan Resolusi:')
                                        }}</span>
                                        <span>{{ item.resolution_note }}</span>
                                    </div>
                                </td>

                                <!-- Department -->
                                <td
                                    class="px-3.5 py-2.5 text-slate-600 dark:text-slate-300"
                                >
                                    {{ item.department }}
                                </td>

                                <!-- Impact Description -->
                                <td
                                    class="hidden px-3.5 py-2.5 text-slate-500 md:table-cell dark:text-slate-400"
                                >
                                    {{ item.impact }}
                                </td>

                                <!-- Deadline -->
                                <td
                                    class="px-3.5 py-2.5 text-center font-mono text-slate-600 tabular-nums dark:text-slate-300"
                                >
                                    {{ item.deadline }}
                                </td>

                                <!-- Interactive Status Badge -->
                                <td class="px-3.5 py-2.5 text-center">
                                    <button
                                        type="button"
                                        class="group inline-flex cursor-pointer items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-bold transition-all hover:scale-105 active:scale-95"
                                        :class="
                                            item.status === 'resolved'
                                                ? 'border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
                                                : item.status === 'in_progress'
                                                  ? 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/50 dark:text-amber-300'
                                                  : 'border-slate-300 bg-slate-100 text-slate-700 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                        "
                                        data-test="action-status-badge"
                                        :data-item-id="item.id"
                                        @click="emit('select-item', item)"
                                    >
                                        <span
                                            class="size-1.5 rounded-full"
                                            :class="
                                                item.status === 'resolved'
                                                    ? 'bg-emerald-500'
                                                    : item.status ===
                                                        'in_progress'
                                                      ? 'animate-pulse bg-amber-500'
                                                      : 'bg-slate-400'
                                            "
                                        ></span>
                                        <span>{{ item.status_label }}</span>
                                        <Pencil
                                            class="size-2.5 opacity-40 transition-opacity group-hover:opacity-100"
                                        />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
