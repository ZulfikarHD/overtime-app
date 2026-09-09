<script setup lang="ts">
import {
    AlertCircle,
    CheckCircle2,
    FolderKanban,
    Search,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';

export interface CapexProjectItem {
    id: number;
    project_code: string;
    asset_code: string | null;
    name: string;
    department_id: number;
    department_name: string;
    department_code: string;
    period_logged_hours: number;
    cumulative_logged_hours: number;
    allocated_labor_hours: number;
    allocated_labor_budget_idr: number;
    period_cost_idr: number;
    cumulative_cost_idr: number;
    variance_hours: number;
    physical_progress_pct: number;
    status: string;
    start_date: string | null;
    target_end_date: string | null;
    has_logged_hours: boolean;
}

const props = defineProps<{
    projects: CapexProjectItem[];
}>();

const { __ } = useTrans();
const searchQuery = ref('');

const filteredProjects = computed(() => {
    if (!searchQuery.value.trim()) {
        return props.projects;
    }
    const q = searchQuery.value.toLowerCase().trim();
    return props.projects.filter(
        (p) =>
            p.project_code.toLowerCase().includes(q) ||
            p.name.toLowerCase().includes(q) ||
            (p.asset_code && p.asset_code.toLowerCase().includes(q)) ||
            p.department_name.toLowerCase().includes(q) ||
            p.department_code.toLowerCase().includes(q),
    );
});

function getStatusBadgeClass(status: string): string {
    switch (status) {
        case 'ACTIVE':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800';
        case 'PLANNING':
            return 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/40 dark:text-sky-400 dark:border-sky-800';
        case 'COMPLETED':
            return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700';
        case 'ON_HOLD':
            return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800';
        case 'CLOSED':
        default:
            return 'bg-slate-100 text-slate-500 border-slate-200 dark:bg-slate-800/60 dark:text-slate-400 dark:border-slate-700';
    }
}
</script>

<template>
    <div class="space-y-3" data-test="capex-project-table-container">
        <!-- Filter and Search Header -->
        <div
            class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center"
        >
            <div class="flex items-center gap-2">
                <FolderKanban class="size-4 text-sky-600 dark:text-sky-400" />
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">
                    {{ __('Kinerja Jam Tenaga Kerja Proyek CapEx') }}
                </h3>
                <Badge variant="outline" class="font-mono text-xs">
                    {{ projects.length }} {{ __('Proyek') }}
                </Badge>
            </div>

            <div class="relative w-full sm:w-64">
                <Search
                    class="text-muted-foreground absolute top-2.5 left-2.5 size-3.5"
                />
                <Input
                    v-model="searchQuery"
                    :placeholder="__('Cari proyek atau aset...')"
                    class="h-8 pl-8 text-xs"
                    data-test="input-search-capex-projects"
                />
            </div>
        </div>

        <!-- Projects Table -->
        <div
            class="bg-card overflow-hidden rounded-lg border border-slate-200 dark:border-slate-800"
        >
            <div class="overflow-x-auto">
                <table
                    class="w-full text-left text-xs"
                    data-test="capex-projects-table"
                >
                    <thead
                        class="border-b border-slate-200 bg-slate-50/80 text-[11px] font-semibold text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300"
                    >
                        <tr>
                            <th class="px-3 py-2.5">{{ __('Kode & Aset') }}</th>
                            <th class="px-3 py-2.5">
                                {{ __('Nama Proyek & Dept') }}
                            </th>
                            <th class="px-3 py-2.5 text-right">
                                {{ __('Jam Periode') }}
                            </th>
                            <th class="px-3 py-2.5 text-right">
                                {{ __('Total Akumulasi') }}
                            </th>
                            <th class="px-3 py-2.5 text-right">
                                {{ __('Alokasi Kuota') }}
                            </th>
                            <th class="px-3 py-2.5 text-center">
                                {{ __('Deviasi (Variance)') }}
                            </th>
                            <th class="px-3 py-2.5">
                                {{ __('Progress Fisik') }}
                            </th>
                            <th class="px-3 py-2.5 text-center">
                                {{ __('Status') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-slate-100 dark:divide-slate-800/60"
                    >
                        <tr
                            v-if="filteredProjects.length === 0"
                            class="text-center"
                        >
                            <td
                                colspan="8"
                                class="text-muted-foreground px-3 py-8"
                            >
                                <div
                                    class="flex flex-col items-center justify-center gap-2"
                                >
                                    <AlertCircle
                                        class="size-6 text-slate-400"
                                    />
                                    <span>
                                        {{
                                            projects.length === 0
                                                ? __(
                                                      'Belum ada proyek CapEx pada periode ini',
                                                  )
                                                : __(
                                                      'Tidak ada proyek yang cocok dengan filter',
                                                  )
                                        }}
                                    </span>
                                </div>
                            </td>
                        </tr>

                        <tr
                            v-for="project in filteredProjects"
                            :key="project.id"
                            class="transition-colors hover:bg-slate-50/60 dark:hover:bg-slate-900/40"
                            :data-test="`capex-project-row-${project.id}`"
                        >
                            <!-- Project Code & Asset Code -->
                            <td class="px-3 py-2.5 font-mono">
                                <div
                                    class="font-bold text-sky-700 dark:text-sky-300"
                                >
                                    {{ project.project_code }}
                                </div>
                                <div
                                    v-if="project.asset_code"
                                    class="text-[10px] text-slate-500"
                                >
                                    {{ project.asset_code }}
                                </div>
                            </td>

                            <!-- Project Name & Department -->
                            <td class="px-3 py-2.5">
                                <div
                                    class="max-w-xs truncate font-medium text-slate-900 dark:text-white"
                                    :title="project.name"
                                >
                                    {{ project.name }}
                                </div>
                                <div class="text-[10px] text-slate-500">
                                    {{ project.department_code }} -
                                    {{ project.department_name }}
                                </div>
                            </td>

                            <!-- Period Logged Hours -->
                            <td
                                class="px-3 py-2.5 text-right font-mono tabular-nums"
                            >
                                <span
                                    class="font-semibold text-slate-900 dark:text-white"
                                >
                                    {{ project.period_logged_hours.toFixed(1) }}
                                </span>
                                <span class="ml-1 text-[10px] text-slate-400">{{
                                    __('jam')
                                }}</span>
                            </td>

                            <!-- Cumulative Logged Hours -->
                            <td
                                class="px-3 py-2.5 text-right font-mono tabular-nums"
                            >
                                <span
                                    class="font-semibold text-slate-900 dark:text-white"
                                >
                                    {{
                                        project.cumulative_logged_hours.toFixed(
                                            1,
                                        )
                                    }}
                                </span>
                                <span class="ml-1 text-[10px] text-slate-400">{{
                                    __('jam')
                                }}</span>
                            </td>

                            <!-- Allocated Budget Hours -->
                            <td
                                class="px-3 py-2.5 text-right font-mono tabular-nums"
                            >
                                <span
                                    class="text-slate-600 dark:text-slate-300"
                                >
                                    {{
                                        project.allocated_labor_hours.toFixed(1)
                                    }}
                                </span>
                                <span class="ml-1 text-[10px] text-slate-400">{{
                                    __('jam')
                                }}</span>
                            </td>

                            <!-- Variance (Variance = Cumulative Logged - Allocated Hours) -->
                            <td
                                class="px-3 py-2.5 text-center font-mono tabular-nums"
                            >
                                <Badge
                                    variant="outline"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold"
                                    :class="
                                        project.variance_hours > 0
                                            ? 'border-red-200 bg-red-50 text-[#cc0000] dark:border-red-900 dark:bg-red-950/40 dark:text-red-400'
                                            : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-400'
                                    "
                                >
                                    <TrendingUp
                                        v-if="project.variance_hours > 0"
                                        class="size-3"
                                    />
                                    <TrendingDown v-else class="size-3" />
                                    <span>
                                        {{
                                            project.variance_hours > 0
                                                ? `+${project.variance_hours.toFixed(1)}`
                                                : project.variance_hours.toFixed(
                                                      1,
                                                  )
                                        }}
                                        {{ __('jam') }}
                                    </span>
                                </Badge>
                            </td>

                            <!-- Physical Progress Bar -->
                            <td class="min-w-[120px] px-3 py-2.5">
                                <div
                                    class="mb-1 flex items-center justify-between font-mono text-[10px] tabular-nums"
                                >
                                    <span
                                        >{{
                                            project.physical_progress_pct.toFixed(
                                                0,
                                            )
                                        }}%</span
                                    >
                                    <span
                                        v-if="
                                            project.physical_progress_pct >= 100
                                        "
                                        class="flex items-center gap-0.5 text-emerald-600"
                                    >
                                        <CheckCircle2 class="size-3" />
                                    </span>
                                </div>
                                <div
                                    class="h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-300"
                                        :class="
                                            project.physical_progress_pct >= 100
                                                ? 'bg-emerald-500'
                                                : project.physical_progress_pct >=
                                                    50
                                                  ? 'bg-sky-500'
                                                  : 'bg-amber-500'
                                        "
                                        :style="{
                                            width: `${Math.min(100, Math.max(0, project.physical_progress_pct))}%`,
                                        }"
                                    />
                                </div>
                            </td>

                            <!-- Status Badge -->
                            <td class="px-3 py-2.5 text-center">
                                <Badge
                                    variant="outline"
                                    class="text-[10px] font-medium"
                                    :class="getStatusBadgeClass(project.status)"
                                >
                                    {{ project.status }}
                                </Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
