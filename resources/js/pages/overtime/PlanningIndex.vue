<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CalendarDays, FilePlus2, Pencil, Trash2 } from '@lucide/vue';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import { dashboard } from '@/routes';
import {
    index as planningIndex,
    create as planningCreate,
    edit as planningEdit,
    destroy as planningDestroy,
} from '@/routes/overtime/planning';

const { __ } = useTrans();

interface PlanItem {
    id: number;
    plan_code: string;
    fiscal_year: number;
    fiscal_month: number;
    status: 'DRAFT' | 'PUBLISHED';
    items_count: number;
    section: { id: number; name: string; code: string } | null;
    department: { id: number; name: string; code: string } | null;
    submitted_by: { id: number; name: string } | null;
    created_at: string;
    updated_at: string;
}

interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

defineProps<{
    plans: Paginated<PlanItem>;
    sections: Array<{ id: number; code: string; name: string }>;
    filters: {
        section_id?: string;
        fiscal_year?: string;
        fiscal_month?: string;
    };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Planning OT', href: planningIndex() },
        ],
    },
});

const MONTHS: string[] = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];

function monthLabel(plan: PlanItem): string {
    return `${MONTHS[plan.fiscal_month - 1]} ${plan.fiscal_year}`;
}

function deletePlan(plan: PlanItem) {
    if (!confirm(__('Hapus planning :code?', { code: plan.plan_code }))) return;
    router.delete(planningDestroy.url(plan.id));
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col p-4 sm:p-6">
        <Head :title="__('Planning Overtime')" />

        <div class="mb-6 flex items-start justify-between gap-4">
            <Heading
                :title="__('Planning Overtime')"
                :description="__('Daftar rencana lembur bulanan per seksi.')"
            />
            <Link :href="planningCreate()">
                <Button
                    size="sm"
                    class="bg-[#cc0000] text-white hover:bg-[#b30000]"
                >
                    <FilePlus2 class="mr-1 size-4" />
                    {{ __('Buat Planning Baru') }}
                </Button>
            </Link>
        </div>

        <!-- Empty state -->
        <div
            v-if="plans.data.length === 0"
            class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-12 text-center dark:border-slate-700 dark:bg-slate-900"
        >
            <CalendarDays class="mx-auto mb-3 size-10 text-slate-300" />
            <p class="text-sm text-slate-500">
                {{
                    __(
                        'Belum ada planning. Klik Buat Planning Baru untuk memulai.',
                    )
                }}
            </p>
        </div>

        <!-- Plans table -->
        <div
            v-else
            class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
        >
            <table class="w-full text-xs">
                <thead>
                    <tr
                        class="dark:bg-slate-850 border-b border-slate-200 bg-slate-50 text-[11px] font-semibold tracking-wider text-slate-500 uppercase dark:border-slate-800"
                    >
                        <th class="p-3">{{ __('Kode') }}</th>
                        <th class="p-3">{{ __('Periode') }}</th>
                        <th class="p-3">{{ __('Seksi') }}</th>
                        <th class="p-3 text-center">{{ __('Item') }}</th>
                        <th class="p-3 text-center">{{ __('Status') }}</th>
                        <th class="p-3 text-right">{{ __('Aksi') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <tr
                        v-for="plan in plans.data"
                        :key="plan.id"
                        class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/50"
                    >
                        <td
                            class="p-3 font-mono text-[11px] text-slate-700 dark:text-slate-300"
                        >
                            {{ plan.plan_code }}
                        </td>
                        <td
                            class="p-3 font-semibold text-slate-900 dark:text-white"
                        >
                            {{ monthLabel(plan) }}
                        </td>
                        <td class="p-3 text-slate-600 dark:text-slate-300">
                            <div>{{ plan.section?.name ?? '–' }}</div>
                            <div class="text-[10px] text-slate-400">
                                {{ plan.department?.name }}
                            </div>
                        </td>
                        <td
                            class="p-3 text-center font-mono text-slate-700 tabular-nums dark:text-slate-300"
                        >
                            {{ plan.items_count }}
                        </td>
                        <td class="p-3 text-center">
                            <Badge
                                variant="outline"
                                :class="
                                    plan.status === 'PUBLISHED'
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                        : 'border-amber-200 bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                                "
                            >
                                {{
                                    plan.status === 'PUBLISHED'
                                        ? __('Dipublikasikan')
                                        : __('Draft')
                                }}
                            </Badge>
                        </td>
                        <td class="p-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <Link :href="planningEdit(plan.id)">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        class="size-7"
                                    >
                                        <Pencil class="size-3.5" />
                                    </Button>
                                </Link>
                                <Button
                                    v-if="plan.status === 'DRAFT'"
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 text-red-500 hover:text-red-600"
                                    @click="deletePlan(plan)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
