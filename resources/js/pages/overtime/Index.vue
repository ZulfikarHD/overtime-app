<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Calendar,
    ChevronLeft,
    ChevronRight,
    Eye,
    FileText,
    Filter,
    ListPlus,
    Lock,
    Paperclip,
    Pencil,
    Plus,
    RotateCcw,
    Search,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import SpklUploadSheet, {
    type SpklTargetSubmission,
} from '@/components/overtime/SpklUploadSheet.vue';
import SubmissionDetailModal from '@/components/overtime/SubmissionDetailModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useTrans } from '@/composables/useTrans';
import { formatDateIndo, formatRupiah } from '@/lib/formatters';
import { dashboard } from '@/routes';
import {
    create as createSubmissionRoute,
    edit as editSubmissionRoute,
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
    submission_notes?: string | null;
    section?: { id: number; name: string; code: string } | null;
    department?: { id: number; name: string; code: string } | null;
    submitted_by?: { id: number; name: string; npk: string } | null;
    spkl_document?: {
        id: number;
        status: string;
        due_date: string;
        spkl_number?: string | null;
    } | null;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedSubmissions {
    data: SubmissionRecord[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
    links: PaginationLink[];
    prev_page_url?: string | null;
    next_page_url?: string | null;
}

interface SectionOption {
    id: number;
    department_id: number;
    code: string;
    name: string;
}

const props = defineProps<{
    submissions: PaginatedSubmissions;
    available_sections?: SectionOption[];
    filters: {
        status?: string;
        section_id?: string | number;
        spkl_status?: string;
        date_from?: string;
        date_to?: string;
    };
    detail_id?: number | null;
}>();

const { __ } = useTrans();

// Filter states
const selectedStatus = ref(props.filters.status ?? '');
const selectedSection = ref(
    props.filters.section_id ? String(props.filters.section_id) : '',
);
const selectedSpkl = ref(props.filters.spkl_status ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');

// Modal state
const selectedDetailId = ref<number | null>(props.detail_id ?? null);
const isDetailModalOpen = ref(Boolean(props.detail_id));

watch(
    () => props.detail_id,
    (newId) => {
        if (newId) {
            selectedDetailId.value = newId;
            isDetailModalOpen.value = true;
        }
    },
);

function openDetailModal(submissionId: number) {
    selectedDetailId.value = submissionId;
    isDetailModalOpen.value = true;
}

const isSpklSheetOpen = ref(false);
const selectedSpklSubmission = ref<SpklTargetSubmission | null>(null);

function openSpklSheet(sub: SubmissionRecord | SpklTargetSubmission) {
    const sectionName =
        'section' in sub && sub.section
            ? sub.section.name
            : 'section_name' in sub
              ? (sub.section_name ?? null)
              : null;

    const totalHours =
        'total_hours_cached' in sub
            ? sub.total_hours_cached
            : 'total_hours' in sub
              ? (sub.total_hours ?? null)
              : null;

    selectedSpklSubmission.value = {
        id: sub.id,
        submission_code: sub.submission_code,
        operational_date: sub.operational_date,
        section_name: sectionName,
        total_hours: totalHours,
        spkl_document: sub.spkl_document,
    };
    isSpklSheetOpen.value = true;
}

function handleSpklSuccess() {
    router.reload({ only: ['submissions'] });
}

function applyFilters() {
    const params: Record<string, string> = {};

    if (selectedStatus.value && selectedStatus.value !== 'ALL') {
        params.status = selectedStatus.value;
    }
    if (selectedSection.value) {
        params.section_id = selectedSection.value;
    }
    if (selectedSpkl.value && selectedSpkl.value !== 'ALL') {
        params.spkl_status = selectedSpkl.value;
    }
    if (dateFrom.value) {
        params.date_from = dateFrom.value;
    }
    if (dateTo.value) {
        params.date_to = dateTo.value;
    }

    router.get(indexSubmissionRoute.url(), params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function setStatusFilter(status: string) {
    selectedStatus.value = status;
    applyFilters();
}

function resetFilters() {
    selectedStatus.value = '';
    selectedSection.value = '';
    selectedSpkl.value = '';
    dateFrom.value = '';
    dateTo.value = '';

    router.get(
        indexSubmissionRoute.url(),
        {},
        {
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function handlePagination(url: string | null) {
    if (!url) {
        return;
    }
    router.visit(url, {
        preserveState: true,
        preserveScroll: true,
    });
}

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
        case 'DRAFT':
            return {
                label: __('Draf'),
                class: 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:text-slate-300',
            };
        default:
            return {
                label: __('Menunggu Review'),
                class: 'bg-amber-50 text-amber-800 border-amber-200 dark:bg-amber-950/60 dark:text-amber-300',
            };
    }
}

function isSpklOverdue(dueDateStr?: string | null): boolean {
    if (!dueDateStr) {
        return false;
    }
    const today = new Date().toISOString().slice(0, 10);
    return dueDateStr < today;
}

const statusFilterOptions = computed(() => [
    { value: '', label: __('Semua Status') },
    { value: 'SUBMITTED', label: __('🟡 Menunggu Review') },
    { value: 'PARTIALLY_APPROVED', label: __('🟠 Disetujui Sebagian') },
    { value: 'APPROVED', label: __('🟢 Disetujui') },
    { value: 'REJECTED', label: __('🔴 Ditolak') },
]);

const hasActiveFilters = computed(() => {
    return (
        selectedStatus.value !== '' ||
        selectedSection.value !== '' ||
        selectedSpkl.value !== '' ||
        dateFrom.value !== '' ||
        dateTo.value !== ''
    );
});
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

        <!-- Filter & Search Toolbar Card -->
        <Card
            class="border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
            data-test="history-filter-card"
        >
            <CardContent class="space-y-3 p-4">
                <!-- Status Quick Pills -->
                <div
                    class="flex flex-wrap items-center gap-1.5 border-b border-slate-100 pb-3 dark:border-slate-800"
                >
                    <span class="mr-2 text-xs font-semibold text-slate-500">
                        {{ __('Status:') }}
                    </span>
                    <button
                        v-for="opt in statusFilterOptions"
                        :key="opt.value"
                        type="button"
                        @click="setStatusFilter(opt.value)"
                        :class="
                            selectedStatus === opt.value
                                ? 'bg-slate-900 font-bold text-white shadow-xs dark:bg-white dark:text-slate-900'
                                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'
                        "
                        class="rounded-full px-3 py-1 text-xs transition-colors"
                        :data-test="`filter-status-${opt.value || 'all'}`"
                    >
                        {{ opt.label }}
                    </button>
                </div>

                <!-- Secondary Filters: Date Range, Section, SPKL -->
                <div
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end"
                >
                    <!-- Date From -->
                    <div>
                        <label
                            class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                        >
                            {{ __('Dari Tanggal') }}
                        </label>
                        <input
                            type="date"
                            v-model="dateFrom"
                            @change="applyFilters"
                            class="mt-1 h-8 w-full rounded-md border border-slate-300 bg-white px-2.5 font-mono text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            data-test="filter-date-from"
                        />
                    </div>

                    <!-- Date To -->
                    <div>
                        <label
                            class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                        >
                            {{ __('Sampai Tanggal') }}
                        </label>
                        <input
                            type="date"
                            v-model="dateTo"
                            @change="applyFilters"
                            class="mt-1 h-8 w-full rounded-md border border-slate-300 bg-white px-2.5 font-mono text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            data-test="filter-date-to"
                        />
                    </div>

                    <!-- Section Filter (if multiple sections) -->
                    <div>
                        <label
                            class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                        >
                            {{ __('Seksi') }}
                        </label>
                        <select
                            v-model="selectedSection"
                            @change="applyFilters"
                            class="mt-1 h-8 w-full rounded-md border border-slate-300 bg-white px-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            data-test="filter-section-select"
                        >
                            <option value="">{{ __('Semua Seksi') }}</option>
                            <option
                                v-for="sec in available_sections"
                                :key="sec.id"
                                :value="String(sec.id)"
                            >
                                {{ sec.code }} - {{ sec.name }}
                            </option>
                        </select>
                    </div>

                    <!-- SPKL Status Filter -->
                    <div>
                        <label
                            class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                        >
                            {{ __('Dokumen SPKL') }}
                        </label>
                        <select
                            v-model="selectedSpkl"
                            @change="applyFilters"
                            class="mt-1 h-8 w-full rounded-md border border-slate-300 bg-white px-2 text-xs text-slate-900 dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                            data-test="filter-spkl-select"
                        >
                            <option value="">{{ __('Semua SPKL') }}</option>
                            <option value="PENDING">
                                {{ __('Belum Dilampirkan') }}
                            </option>
                            <option value="ATTACHED">
                                {{ __('Terlampir') }}
                            </option>
                            <option value="VERIFIED">
                                {{ __('Terverifikasi') }}
                            </option>
                            <option value="OVERDUE">
                                {{ __('⚠️ Terlambat') }}
                            </option>
                        </select>
                    </div>

                    <!-- Action Buttons: Apply & Reset -->
                    <div class="flex items-center gap-2">
                        <Button
                            type="button"
                            size="sm"
                            @click="applyFilters"
                            class="h-8 flex-1 bg-slate-900 text-xs font-semibold text-white hover:bg-slate-800 dark:bg-slate-100 dark:text-slate-900"
                            data-test="btn-apply-filters"
                        >
                            <Filter class="mr-1.5 size-3.5" />
                            <span>{{ __('Terapkan') }}</span>
                        </Button>

                        <Button
                            v-if="hasActiveFilters"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="resetFilters"
                            class="h-8 px-2.5 text-xs text-slate-500 hover:text-red-600"
                            :title="__('Reset Filter')"
                            data-test="btn-reset-filters"
                        >
                            <RotateCcw class="size-3.5" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Submissions Table Card -->
        <Card
            class="border-slate-200 bg-white shadow-xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader
                class="flex flex-row items-center justify-between border-b border-slate-200 px-4 py-3 dark:border-slate-800"
            >
                <div class="flex items-center gap-2">
                    <CardTitle
                        class="text-sm font-bold text-slate-900 dark:text-white"
                    >
                        {{ __('Daftar Batch Pengajuan Lembur') }}
                    </CardTitle>
                    <Badge variant="outline" class="font-mono text-xs">
                        {{ submissions.total }} {{ __('Pengajuan') }}
                    </Badge>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <div
                    v-if="submissions.data.length === 0"
                    class="flex flex-col items-center justify-center p-12 text-center"
                    data-test="empty-history-state"
                >
                    <FileText
                        class="size-10 text-slate-300 dark:text-slate-600"
                    />
                    <p
                        class="mt-3 text-sm font-bold text-slate-900 dark:text-white"
                    >
                        {{ __('Belum Ada Pengajuan Lembur') }}
                    </p>
                    <p class="mt-1 max-w-sm text-xs text-slate-500">
                        {{
                            hasActiveFilters
                                ? __(
                                      'Tidak ada pengajuan yang cocok dengan kriteria filter saat ini.',
                                  )
                                : __(
                                      'Mulai dengan membuat pengajuan lembur pertama untuk seksi Anda.',
                                  )
                        }}
                    </p>
                    <div class="mt-4 flex items-center gap-2">
                        <Button
                            v-if="hasActiveFilters"
                            variant="outline"
                            size="sm"
                            @click="resetFilters"
                            class="text-xs"
                        >
                            {{ __('Bersihkan Filter') }}
                        </Button>
                        <Link
                            :href="createSubmissionRoute()"
                            class="inline-flex items-center rounded-md bg-[#cc0000] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#b30000]"
                        >
                            <Plus class="mr-1 size-3.5" />
                            <span>{{ __('Input Lembur Sekarang') }}</span>
                        </Link>
                    </div>
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
                                <th class="p-3 text-right">{{ __('Aksi') }}</th>
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
                                <!-- Kode Pengajuan -->
                                <td class="p-3">
                                    <button
                                        type="button"
                                        @click="openDetailModal(sub.id)"
                                        class="cursor-pointer rounded bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold text-slate-800 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                                        :data-test="`code-link-${sub.id}`"
                                    >
                                        {{ sub.submission_code }}
                                    </button>
                                </td>

                                <!-- Tanggal & Hari -->
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

                                <!-- Seksi -->
                                <td
                                    class="p-3 text-slate-700 dark:text-slate-300"
                                >
                                    {{ sub.section?.name ?? '-' }}
                                </td>

                                <!-- Diajukan Oleh -->
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

                                <!-- Total Jam -->
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

                                <!-- Estimasi Biaya -->
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

                                <!-- Status Persetujuan -->
                                <td class="p-3">
                                    <span
                                        class="inline-flex items-center gap-1 rounded border px-2 py-0.5 text-[10px] font-semibold"
                                        :class="
                                            getStatusBadge(sub.status).class
                                        "
                                        :data-test="`submission-status-${sub.id}`"
                                    >
                                        {{ getStatusBadge(sub.status).label }}
                                    </span>
                                </td>

                                <!-- Dokumen SPKL -->
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
                                        📎
                                        {{
                                            sub.spkl_document?.status ===
                                            'VERIFIED'
                                                ? __('Terverifikasi')
                                                : __('Terlampir')
                                        }}
                                    </span>
                                    <span
                                        v-else-if="
                                            isSpklOverdue(
                                                sub.spkl_document?.due_date,
                                            )
                                        "
                                        class="inline-flex items-center gap-1 rounded border border-red-200 bg-red-50 px-2 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-950 dark:text-red-300"
                                    >
                                        ⚠️ {{ __('SPKL Terlambat') }}
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 rounded border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-300"
                                    >
                                        ⏳ {{ __('Belum Dilampirkan') }}
                                    </span>
                                </td>

                                <!-- Aksi (Detail & Edit) -->
                                <td class="p-3 text-right">
                                    <div
                                        class="inline-flex items-center justify-end gap-1.5"
                                    >
                                        <!-- Detail Button -->
                                        <button
                                            type="button"
                                            @click="openDetailModal(sub.id)"
                                            class="inline-flex h-7 items-center gap-1 rounded border border-slate-300 bg-white px-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                            :data-test="`btn-detail-${sub.id}`"
                                        >
                                            <Eye
                                                class="size-3.5 text-slate-400"
                                            />
                                            <span>{{ __('Detail') }}</span>
                                        </button>

                                        <!-- Attach SPKL Button -->
                                        <button
                                            type="button"
                                            @click="openSpklSheet(sub)"
                                            class="inline-flex h-7 items-center gap-1 rounded border border-slate-300 bg-white px-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700"
                                            :data-test="`btn-attach-spkl-${sub.id}`"
                                        >
                                            <Paperclip
                                                class="size-3 text-slate-400"
                                            />
                                            <span>{{
                                                sub.spkl_document?.status ===
                                                    'ATTACHED' ||
                                                sub.spkl_document?.status ===
                                                    'VERIFIED'
                                                    ? __('SPKL')
                                                    : __('Lampirkan SPKL')
                                            }}</span>
                                        </button>

                                        <!-- Edit Button (Enabled only if SUBMITTED or DRAFT) -->
                                        <Link
                                            v-if="
                                                sub.status === 'SUBMITTED' ||
                                                sub.status === 'DRAFT'
                                            "
                                            :href="
                                                editSubmissionRoute.url(sub.id)
                                            "
                                            class="inline-flex h-7 items-center gap-1 rounded border border-amber-300 bg-amber-50 px-2 text-xs font-semibold text-amber-800 hover:bg-amber-100 dark:border-amber-800 dark:bg-amber-950/60 dark:text-amber-300"
                                            :data-test="`btn-edit-${sub.id}`"
                                        >
                                            <Pencil
                                                class="size-3 text-amber-600"
                                            />
                                            <span>{{ __('Edit') }}</span>
                                        </Link>

                                        <!-- Locked Indicator if APPROVED or PARTIALLY_APPROVED -->
                                        <span
                                            v-else
                                            class="dark:bg-slate-850 inline-flex h-7 cursor-not-allowed items-center gap-1 rounded border border-slate-200 bg-slate-100 px-2 text-[11px] font-medium text-slate-400 dark:border-slate-800 dark:text-slate-500"
                                            :title="
                                                __(
                                                    'Pengajuan sudah diproses oleh Manajer dan terkunci permanen.',
                                                )
                                            "
                                            :data-test="`btn-locked-${sub.id}`"
                                        >
                                            <Lock
                                                class="size-3 text-slate-400"
                                            />
                                            <span>{{ __('Terkunci') }}</span>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Server-Side Pagination Bar -->
                <div
                    v-if="submissions.total > 0"
                    class="flex flex-col items-center justify-between gap-3 border-t border-slate-200 p-4 sm:flex-row dark:border-slate-800"
                    data-test="pagination-bar"
                >
                    <div class="text-xs text-slate-500">
                        {{
                            __(
                                'Menampilkan :from sampai :to dari :total pengajuan',
                                {
                                    from: submissions.from ?? 1,
                                    to:
                                        submissions.to ??
                                        submissions.data.length,
                                    total: submissions.total,
                                },
                            )
                        }}
                    </div>

                    <div
                        v-if="submissions.last_page > 1"
                        class="flex items-center gap-1"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="size-8 p-0"
                            :disabled="!submissions.prev_page_url"
                            @click="
                                handlePagination(
                                    submissions.prev_page_url ?? null,
                                )
                            "
                            data-test="btn-pagination-prev"
                        >
                            <ChevronLeft class="size-4" />
                        </Button>

                        <template
                            v-for="(link, idx) in submissions.links.slice(
                                1,
                                -1,
                            )"
                            :key="idx"
                        >
                            <Button
                                type="button"
                                size="sm"
                                :variant="link.active ? 'default' : 'outline'"
                                class="h-8 min-w-8 px-2 text-xs"
                                :class="
                                    link.active
                                        ? 'bg-[#cc0000] text-white hover:bg-[#b30000]'
                                        : ''
                                "
                                :disabled="!link.url"
                                @click="handlePagination(link.url)"
                            >
                                <span v-html="link.label" />
                            </Button>
                        </template>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="size-8 p-0"
                            :disabled="!submissions.next_page_url"
                            @click="
                                handlePagination(
                                    submissions.next_page_url ?? null,
                                )
                            "
                            data-test="btn-pagination-next"
                        >
                            <ChevronRight class="size-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Read-Only Detail Modal Component -->
        <SubmissionDetailModal
            v-model:open="isDetailModalOpen"
            :submission-id="selectedDetailId"
            @attach-spkl="openSpklSheet"
            @verified="handleSpklSuccess"
        />

        <!-- Slide-in SPKL Upload Sheet Component -->
        <SpklUploadSheet
            v-model:open="isSpklSheetOpen"
            :submission="selectedSpklSubmission"
            @success="handleSpklSuccess"
        />
    </div>
</template>
