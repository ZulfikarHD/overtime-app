<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    Calendar,
    CheckCircle2,
    Clock,
    FileText,
    ListPlus,
    Plus,
    Search,
} from '@lucide/vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { formatDateIndo, formatRupiah } from '@/lib/formatters';
import { dashboard } from '@/routes';
import {
    create as createSubmissionRoute,
    index as indexSubmissionRoute,
} from '@/routes/overtime/submissions';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Riwayat Pengajuan Lembur',
                href: indexSubmissionRoute(),
            },
        ],
    },
});

interface SubmissionRecord {
    id: number;
    submission_code: string;
    operational_date: string;
    day_type: 'HKN' | 'HLR';
    status:
        | 'DRAFT'
        | 'SUBMITTED'
        | 'PARTIALLY_APPROVED'
        | 'APPROVED'
        | 'REJECTED';
    total_hours_cached: string | number;
    total_cost_cached?: string | number | null;
    section?: { id: number; name: string; code: string } | null;
    department?: { id: number; name: string; code: string } | null;
    submitted_by?: { id: number; name: string; npk: string } | null;
    spkl_document?: { id: number; status: string; due_date: string } | null;
}

interface PaginatedSubmissions {
    data: SubmissionRecord[];
    current_page: number;
    last_page: number;
    total: number;
}

defineProps<{
    submissions: PaginatedSubmissions;
    filters: {
        status?: string;
        date_from?: string;
        date_to?: string;
    };
}>();

const { __ } = useTrans();

function getStatusBadge(status: string) {
    switch (status) {
        case 'APPROVED':
            return {
                label: __('Disetujui'),
                class: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950 dark:text-emerald-300',
            };
        case 'PARTIALLY_APPROVED':
            return {
                label: __('Disetujui Sebagian'),
                class: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950 dark:text-amber-300',
            };
        case 'REJECTED':
            return {
                label: __('Ditolak'),
                class: 'bg-red-50 text-red-700 border-red-200 dark:bg-red-950 dark:text-red-300',
            };
        default:
            return {
                label: __('Menunggu Review'),
                class: 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300',
            };
    }
}
</script>

<template>
    <div class="space-y-4 p-4 md:p-6" data-test="overtime-history-page">
        <Head :title="__('Riwayat Pengajuan Lembur')" />

        <!-- Top Navigation Switcher -->
        <div
            class="flex items-center justify-between border-b border-slate-200 pb-3 dark:border-slate-800"
        >
            <div class="flex items-center gap-2">
                <Link
                    :href="createSubmissionRoute()"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300 dark:hover:bg-slate-800"
                    data-test="tab-form-create"
                >
                    <ListPlus class="size-4 text-slate-400" />
                    <span>{{ __('Form Input Lembur') }}</span>
                </Link>
                <Link
                    :href="indexSubmissionRoute()"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#cc0000] px-3.5 py-1.5 text-xs font-bold text-white shadow-xs"
                    data-test="tab-history-active"
                >
                    <FileText class="size-4" />
                    <span>{{ __('Riwayat Pengajuan') }}</span>
                </Link>
            </div>

            <Link
                :href="createSubmissionRoute()"
                class="inline-flex h-8 items-center gap-1.5 rounded-md bg-[#cc0000] px-3 text-xs font-bold text-white shadow-xs hover:bg-[#b30000]"
                data-test="btn-new-overtime"
            >
                <Plus class="size-3.5" />
                <span>{{ __('Input Lembur Baru') }}</span>
            </Link>
        </div>

        <!-- Submissions Table Card -->
        <Card
            class="border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader
                class="flex flex-row items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800"
            >
                <CardTitle
                    class="text-sm font-bold text-slate-900 dark:text-white"
                >
                    {{ __('Daftar Batch Pengajuan Lembur') }}
                </CardTitle>
                <Badge variant="outline" class="font-mono text-xs">
                    {{ submissions.total }} {{ __('Pengajuan') }}
                </Badge>
            </CardHeader>

            <CardContent class="p-0">
                <div
                    v-if="submissions.data.length === 0"
                    class="flex flex-col items-center justify-center p-8 text-center"
                    data-test="empty-history-state"
                >
                    <FileText class="size-8 text-slate-400" />
                    <p
                        class="mt-2 text-sm font-semibold text-slate-900 dark:text-white"
                    >
                        {{ __('Belum Ada Pengajuan Lembur') }}
                    </p>
                    <p class="text-xs text-slate-500">
                        {{
                            __(
                                'Mulai dengan membuat pengajuan lembur pertama untuk seksi Anda.',
                            )
                        }}
                    </p>
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr
                                class="dark:bg-slate-850 border-b border-slate-200 bg-slate-50 text-[11px] font-semibold tracking-wider text-slate-500 uppercase dark:border-slate-800 dark:text-slate-400"
                            >
                                <th class="p-3">{{ __('Kode Pengajuan') }}</th>
                                <th class="p-3">{{ __('Tanggal & Hari') }}</th>
                                <th class="p-3">{{ __('Seksi') }}</th>
                                <th class="p-3">{{ __('Diajukan Oleh') }}</th>
                                <th class="p-3 text-right">
                                    {{ __('Total Jam') }}
                                </th>
                                <th class="p-3 text-right">
                                    {{ __('Estimasi Biaya') }}
                                </th>
                                <th class="p-3">
                                    {{ __('Status Persetujuan') }}
                                </th>
                                <th class="p-3">{{ __('Dokumen SPKL') }}</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr
                                v-for="sub in submissions.data"
                                :key="sub.id"
                                class="transition-colors hover:bg-slate-50/70 dark:hover:bg-slate-800/50"
                                :data-test="`submission-row-${sub.id}`"
                            >
                                <td class="p-3">
                                    <span
                                        class="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-800 dark:bg-slate-800 dark:text-slate-200"
                                    >
                                        {{ sub.submission_code }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    <div
                                        class="font-medium text-slate-900 dark:text-white"
                                    >
                                        {{
                                            formatDateIndo(sub.operational_date)
                                        }}
                                    </div>
                                    <span
                                        class="py-0.2 mt-0.5 inline-block rounded px-1.5 text-[10px] font-bold"
                                        :class="
                                            sub.day_type === 'HKN'
                                                ? 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                                                : 'bg-red-50 text-[#cc0000] dark:bg-red-950 dark:text-red-300'
                                        "
                                    >
                                        {{ sub.day_type }}
                                    </span>
                                </td>
                                <td
                                    class="p-3 text-slate-700 dark:text-slate-300"
                                >
                                    {{ sub.section?.name ?? '-' }}
                                </td>
                                <td
                                    class="p-3 text-slate-700 dark:text-slate-300"
                                >
                                    <div>
                                        {{ sub.submitted_by?.name ?? '-' }}
                                    </div>
                                    <div
                                        class="font-mono text-[10px] text-slate-400"
                                    >
                                        {{ sub.submitted_by?.npk ?? '' }}
                                    </div>
                                </td>
                                <td
                                    class="p-3 text-right font-mono font-bold text-slate-900 tabular-nums dark:text-white"
                                >
                                    {{
                                        Number(sub.total_hours_cached).toFixed(
                                            1,
                                        )
                                    }}
                                    jam
                                </td>
                                <td
                                    class="p-3 text-right font-mono font-bold text-[#cc0000] tabular-nums dark:text-red-400"
                                    :data-test="`submission-cost-${sub.id}`"
                                >
                                    {{
                                        formatRupiah(
                                            Number(sub.total_cost_cached ?? 0),
                                        )
                                    }}
                                </td>
                                <td class="p-3">
                                    <span
                                        class="inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[10px] font-semibold"
                                        :class="
                                            getStatusBadge(sub.status).class
                                        "
                                    >
                                        {{ getStatusBadge(sub.status).label }}
                                    </span>
                                </td>
                                <td class="p-3">
                                    <span
                                        v-if="
                                            sub.spkl_document?.status ===
                                                'ATTACHED' ||
                                            sub.spkl_document?.status ===
                                                'VERIFIED'
                                        "
                                        class="inline-flex items-center gap-1 rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                                    >
                                        📎 {{ __('Terlampir') }}
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                                    >
                                        ⏳ {{ __('Belum Dilampirkan') }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
