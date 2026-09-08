<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Calendar,
    CheckCheck,
    CheckCircle2,
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    Clock,
    Filter,
    RotateCcw,
    Users,
    X,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import ApprovalModal from '@/components/overtime/ApprovalModal.vue';
import BulkActionResultToast, {
    type BulkActionResult,
} from '@/components/overtime/BulkActionResultToast.vue';
import BulkApprovalConfirmModal from '@/components/overtime/BulkApprovalConfirmModal.vue';
import SubmissionQueueRow, {
    type QueueSubmission,
} from '@/components/overtime/SubmissionQueueRow.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useShiftInfo } from '@/composables/useShiftInfo';
import { useTrans } from '@/composables/useTrans';
import { dashboard } from '@/routes';
import { approvals as approvalsRoute } from '@/routes/overtime';
import { bulk as bulkRoute } from '@/routes/overtime/approvals';
import type { User } from '@/types';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
            {
                title: 'Persetujuan Lembur',
                href: approvalsRoute(),
            },
        ],
    },
});

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedSubmissions {
    data: QueueSubmission[];
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

interface DepartmentOption {
    id: number;
    code: string;
    name: string;
}

const props = defineProps<{
    submissions: PaginatedSubmissions;
    available_sections?: SectionOption[];
    available_departments?: DepartmentOption[];
    pending_count: number;
    pending_hours: number;
    filters: {
        status?: string;
        department_id?: string | number;
        section_id?: string | number;
        spkl_status?: string;
        date_from?: string;
        date_to?: string;
        sort?: string;
        direction?: string;
    };
}>();

const { __ } = useTrans();
const { timeString } = useShiftInfo();
const page = usePage();
const user = computed(() => page.props.auth?.user as User | undefined);
const isAdmin = computed(() => user.value?.role === 'admin');

const selectedStatus = ref(props.filters.status ?? 'SUBMITTED');
const selectedDepartment = ref(
    props.filters.department_id ? String(props.filters.department_id) : '',
);
const selectedSection = ref(
    props.filters.section_id ? String(props.filters.section_id) : '',
);
const selectedSpkl = ref(props.filters.spkl_status ?? '');
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const sortBy = ref(props.filters.sort ?? 'date');
const sortDirection = ref(props.filters.direction ?? 'desc');
const selectedIds = ref<number[]>([]);

watch(
    () => props.filters,
    (filters) => {
        selectedStatus.value = filters.status ?? 'SUBMITTED';
        selectedDepartment.value = filters.department_id
            ? String(filters.department_id)
            : '';
        selectedSection.value = filters.section_id
            ? String(filters.section_id)
            : '';
        selectedSpkl.value = filters.spkl_status ?? '';
        dateFrom.value = filters.date_from ?? '';
        dateTo.value = filters.date_to ?? '';
        sortBy.value = filters.sort ?? 'date';
        sortDirection.value = filters.direction ?? 'desc';
    },
    { deep: true },
);

watch(
    () => props.submissions.data,
    () => {
        selectedIds.value = [];
    },
);

const visibleIds = computed(() => props.submissions.data.map((s) => s.id));
const allPageSelected = computed(
    () =>
        visibleIds.value.length > 0 &&
        visibleIds.value.every((id) => selectedIds.value.includes(id)),
);

const hasActiveFilters = computed(() => {
    return (
        (selectedStatus.value !== '' && selectedStatus.value !== 'SUBMITTED') ||
        selectedDepartment.value !== '' ||
        selectedSection.value !== '' ||
        selectedSpkl.value !== '' ||
        sortBy.value !== 'date' ||
        sortDirection.value !== 'desc'
    );
});

const statusTabs = computed(() => [
    { value: 'SUBMITTED', label: __('Menunggu Review') },
    { value: 'PARTIALLY_APPROVED', label: __('Disetujui Sebagian') },
    { value: 'ALL', label: __('Semua') },
]);

function applyFilters(overrides: Record<string, string> = {}) {
    const params: Record<string, string> = {};

    const status = overrides.status ?? selectedStatus.value;
    if (status && status !== 'SUBMITTED') {
        params.status = status;
    }
    // SUBMITTED is the server default — omit from query string

    const departmentId = overrides.department_id ?? selectedDepartment.value;
    if (departmentId) {
        params.department_id = departmentId;
    }

    const sectionId = overrides.section_id ?? selectedSection.value;
    if (sectionId) {
        params.section_id = sectionId;
    }

    const spkl = overrides.spkl_status ?? selectedSpkl.value;
    if (spkl) {
        params.spkl_status = spkl;
    }

    const from = overrides.date_from ?? dateFrom.value;
    const to = overrides.date_to ?? dateTo.value;
    if (from) {
        params.date_from = from;
    }
    if (to) {
        params.date_to = to;
    }

    const sort = overrides.sort ?? sortBy.value;
    const direction = overrides.direction ?? sortDirection.value;
    if (sort && sort !== 'date') {
        params.sort = sort;
    }
    if (direction && direction !== 'desc') {
        params.direction = direction;
    }

    router.get(approvalsRoute.url(), params, {
        preserveState: true,
        preserveScroll: true,
    });
}

function setStatusTab(status: string) {
    selectedStatus.value = status;
    applyFilters({ status });
}

function setDatePreset(preset: 'today' | '7days' | 'month') {
    const now = new Date();
    const toIso = (d: Date) => d.toISOString().slice(0, 10);
    const end = toIso(now);
    let start = end;

    if (preset === '7days') {
        const from = new Date(now);
        from.setDate(from.getDate() - 6);
        start = toIso(from);
    } else if (preset === 'month') {
        start = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
    }

    dateFrom.value = start;
    dateTo.value = end;
    applyFilters({ date_from: start, date_to: end });
}

function resetFilters() {
    selectedStatus.value = 'SUBMITTED';
    selectedDepartment.value = '';
    selectedSection.value = '';
    selectedSpkl.value = '';
    sortBy.value = 'date';
    sortDirection.value = 'desc';

    const now = new Date();
    const toIso = (d: Date) => d.toISOString().slice(0, 10);
    const end = toIso(now);
    const from = new Date(now);
    from.setDate(from.getDate() - 6);
    dateFrom.value = toIso(from);
    dateTo.value = end;

    router.get(
        approvalsRoute.url(),
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

function toggleSelect(id: number) {
    if (selectedIds.value.includes(id)) {
        selectedIds.value = selectedIds.value.filter((x) => x !== id);
    } else {
        selectedIds.value = [...selectedIds.value, id];
    }
}

function toggleSelectAll() {
    if (allPageSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = [...visibleIds.value];
    }
}

// Bulk Actions State (E04-03)
const isBulkConfirmModalOpen = ref(false);
const bulkActionType = ref<'APPROVED' | 'REJECTED'>('APPROVED');
const isBulkProcessing = ref(false);
const bulkResult = ref<BulkActionResult | null>(null);

const selectedSubmissions = computed(() => {
    return props.submissions.data.filter((s) =>
        selectedIds.value.includes(s.id),
    );
});

const selectedSubmissionsHours = computed(() => {
    return selectedSubmissions.value.reduce((sum, s) => {
        const h =
            typeof s.total_hours_cached === 'string'
                ? parseFloat(s.total_hours_cached)
                : Number(s.total_hours_cached || 0);
        return sum + (Number.isNaN(h) ? 0 : h);
    }, 0);
});

const selectedSubmissionsHeadcount = computed(() => {
    return selectedSubmissions.value.reduce((sum, s) => {
        return sum + (s.items_count ?? s.items?.length ?? 0);
    }, 0);
});

function triggerBulkApprove() {
    if (selectedIds.value.length === 0) {
        return;
    }
    bulkActionType.value = 'APPROVED';
    isBulkConfirmModalOpen.value = true;
}

function triggerBulkReject() {
    if (selectedIds.value.length === 0) {
        return;
    }
    bulkActionType.value = 'REJECTED';
    isBulkConfirmModalOpen.value = true;
}

function clearSelection() {
    selectedIds.value = [];
}

async function handleBulkConfirm(payload: {
    action: 'APPROVED' | 'REJECTED';
    rejection_reason?: string;
}) {
    if (selectedIds.value.length === 0) {
        return;
    }

    isBulkProcessing.value = true;
    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') || '';

    try {
        const url = bulkRoute.url();
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                submission_ids: selectedIds.value,
                action: payload.action,
                rejection_reason: payload.rejection_reason,
            }),
        });

        const data = await res.json();

        if (!res.ok) {
            toastMessage.value =
                data.message ||
                __('Terjadi kesalahan saat memproses persetujuan massal.');
            return;
        }

        isBulkConfirmModalOpen.value = false;
        selectedIds.value = [];
        bulkResult.value = data as BulkActionResult;

        router.reload({
            only: ['submissions', 'pending_count', 'pending_hours'],
        });
    } catch (err: any) {
        console.error('Error executing bulk approvals:', err);
        toastMessage.value = __(
            'Terjadi kesalahan jaringan saat memproses persetujuan massal.',
        );
    } finally {
        isBulkProcessing.value = false;
    }
}

const isApprovalModalOpen = ref(false);
const selectedSubmissionId = ref<number | null>(null);
const toastMessage = ref<string | null>(null);

const selectedSubmissionData = computed(() => {
    if (!selectedSubmissionId.value) {
        return null;
    }
    return (
        props.submissions.data.find(
            (s) => s.id === selectedSubmissionId.value,
        ) ?? null
    );
});

function handleReview(id: number) {
    selectedSubmissionId.value = id;
    isApprovalModalOpen.value = true;
}

function handleApprovalSaved(payload: { message: string }) {
    toastMessage.value = payload.message;
    router.reload({
        only: ['submissions', 'pending_count', 'pending_hours'],
    });
    setTimeout(() => {
        if (toastMessage.value === payload.message) {
            toastMessage.value = null;
        }
    }, 10000);
}
</script>

<template>
    <div class="space-y-4 p-4 md:p-6" data-test="approval-queue-page">
        <Head :title="__('Persetujuan Lembur')" />

        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="space-y-1">
                <Heading
                    :title="__('Persetujuan Lembur')"
                    :description="
                        __(
                            'Antrian review lembur untuk standup pagi — tinjau, setujui, atau tolak per pengajuan.',
                        )
                    "
                />
                <div class="flex flex-wrap items-center gap-2 pt-1">
                    <span
                        v-if="user?.department?.name"
                        class="inline-flex items-center rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-semibold text-slate-700 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        data-test="department-badge"
                    >
                        {{ user.department.name }}
                    </span>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-red-200 bg-red-50 px-2.5 py-1 text-[11px] font-bold text-[#cc0000] dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                        data-test="pending-count-pill"
                    >
                        <ClipboardCheck class="size-3.5" />
                        {{
                            __(':count Pengajuan Menunggu Review', {
                                count: pending_count,
                            })
                        }}
                    </span>
                    <span
                        class="font-mono text-[11px] text-slate-500 tabular-nums"
                        data-test="pending-hours"
                    >
                        {{ Number(pending_hours).toFixed(1) }}
                        {{ __('jam pending') }}
                    </span>
                </div>
            </div>

            <div
                class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 bg-slate-50 px-2.5 py-1.5 font-mono text-xs font-medium text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
                data-test="approval-live-clock"
            >
                {{ timeString }} WIB
            </div>
        </div>

        <!-- Success Toast Banner -->
        <div
            v-if="toastMessage"
            class="flex items-center justify-between rounded-lg border border-emerald-300 bg-emerald-50 p-3 text-xs font-semibold text-emerald-800 shadow-xs dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200"
            data-test="approval-success-toast"
        >
            <div class="flex items-center gap-2">
                <CheckCircle2
                    class="size-4 text-emerald-600 dark:text-emerald-400"
                />
                <span>{{ toastMessage }}</span>
            </div>
            <button
                type="button"
                class="text-emerald-600 hover:text-emerald-800 dark:text-emerald-400"
                @click="toastMessage = null"
            >
                <X class="size-4" />
            </button>
        </div>

        <!-- Status Tabs -->
        <div
            class="flex flex-wrap items-center gap-2"
            data-test="status-filter-tabs"
        >
            <button
                v-for="tab in statusTabs"
                :key="tab.value"
                type="button"
                class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                :class="
                    selectedStatus === tab.value
                        ? 'border-[#cc0000] bg-[#cc0000] text-white'
                        : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'
                "
                :data-test="`status-tab-${tab.value.toLowerCase()}`"
                @click="setStatusTab(tab.value)"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Filters -->
        <Card class="border-slate-200 shadow-xs dark:border-slate-800">
            <CardHeader class="pb-2">
                <CardTitle
                    class="flex items-center gap-2 text-sm font-semibold text-slate-800 dark:text-slate-100"
                >
                    <Filter class="size-4 text-slate-400" />
                    {{ __('Filter Antrian') }}
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div
                    class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6"
                >
                    <div v-if="isAdmin">
                        <label
                            class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                        >
                            {{ __('Departemen') }}
                        </label>
                        <select
                            v-model="selectedDepartment"
                            class="mt-1 h-8 w-full rounded-md border border-slate-300 bg-white px-2 text-xs dark:border-slate-700 dark:bg-slate-800"
                            data-test="filter-department-select"
                            @change="applyFilters()"
                        >
                            <option value="">
                                {{ __('Semua Departemen') }}
                            </option>
                            <option
                                v-for="dept in available_departments"
                                :key="dept.id"
                                :value="String(dept.id)"
                            >
                                {{ dept.code }} - {{ dept.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                        >
                            {{ __('Seksi') }}
                        </label>
                        <select
                            v-model="selectedSection"
                            class="mt-1 h-8 w-full rounded-md border border-slate-300 bg-white px-2 text-xs dark:border-slate-700 dark:bg-slate-800"
                            data-test="filter-section-select"
                            @change="applyFilters()"
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

                    <div>
                        <label
                            class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                        >
                            {{ __('Dokumen SPKL') }}
                        </label>
                        <select
                            v-model="selectedSpkl"
                            class="mt-1 h-8 w-full rounded-md border border-slate-300 bg-white px-2 text-xs dark:border-slate-700 dark:bg-slate-800"
                            data-test="filter-spkl-select"
                            @change="applyFilters()"
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

                    <div>
                        <label
                            class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                        >
                            {{ __('Urutkan') }}
                        </label>
                        <select
                            v-model="sortBy"
                            class="mt-1 h-8 w-full rounded-md border border-slate-300 bg-white px-2 text-xs dark:border-slate-700 dark:bg-slate-800"
                            data-test="filter-sort-select"
                            @change="applyFilters()"
                        >
                            <option value="date">
                                {{ __('Tanggal') }}
                            </option>
                            <option value="section">
                                {{ __('Seksi') }}
                            </option>
                            <option value="total_hours">
                                {{ __('Total Jam') }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                        >
                            {{ __('Dari Tanggal') }}
                        </label>
                        <input
                            v-model="dateFrom"
                            type="date"
                            class="mt-1 h-8 w-full rounded-md border border-slate-300 bg-white px-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-800"
                            data-test="filter-date-from"
                            @change="applyFilters()"
                        />
                    </div>

                    <div>
                        <label
                            class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400"
                        >
                            {{ __('Sampai Tanggal') }}
                        </label>
                        <input
                            v-model="dateTo"
                            type="date"
                            class="mt-1 h-8 w-full rounded-md border border-slate-300 bg-white px-2 font-mono text-xs dark:border-slate-700 dark:bg-slate-800"
                            data-test="filter-date-to"
                            @change="applyFilters()"
                        />
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 text-xs"
                        data-test="date-preset-today"
                        @click="setDatePreset('today')"
                    >
                        <Calendar class="mr-1 size-3.5" />
                        {{ __('Hari Ini') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 text-xs"
                        data-test="date-preset-7days"
                        @click="setDatePreset('7days')"
                    >
                        {{ __('7 Hari Terakhir') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="h-8 text-xs"
                        data-test="date-preset-month"
                        @click="setDatePreset('month')"
                    >
                        {{ __('Bulan Ini') }}
                    </Button>
                    <Button
                        v-if="hasActiveFilters"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-8 text-xs text-slate-500"
                        data-test="btn-reset-filters"
                        @click="resetFilters"
                    >
                        <RotateCcw class="mr-1 size-3.5" />
                        {{ __('Reset Filter') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Queue Table -->
        <Card class="border-slate-200 shadow-xs dark:border-slate-800">
            <CardContent class="p-0">
                <div class="overflow-x-auto">
                    <table
                        class="w-full min-w-240 text-left text-xs"
                        data-test="approval-queue-table"
                    >
                        <thead>
                            <tr
                                class="border-b border-slate-200 bg-slate-50 text-[11px] font-semibold tracking-wide text-slate-500 uppercase dark:border-slate-800 dark:bg-slate-900/80"
                            >
                                <th class="w-10 px-3 py-2.5 text-center">
                                    <input
                                        type="checkbox"
                                        class="rounded border-slate-300 text-[#cc0000] focus:ring-[#cc0000]"
                                        :checked="allPageSelected"
                                        data-test="select-all-checkbox"
                                        @change="toggleSelectAll"
                                    />
                                </th>
                                <th class="px-3 py-2.5">
                                    {{ __('Kode & Tanggal') }}
                                </th>
                                <th class="px-3 py-2.5">
                                    {{ __('Seksi / Pengaju') }}
                                </th>
                                <th class="px-3 py-2.5 text-right">
                                    {{ __('Jam / Headcount') }}
                                </th>
                                <th class="px-3 py-2.5 text-right">
                                    {{ __('Estimasi Biaya') }}
                                </th>
                                <th class="px-3 py-2.5">{{ __('SPKL') }}</th>
                                <th class="px-3 py-2.5">{{ __('Status') }}</th>
                                <th class="px-3 py-2.5 text-right">
                                    {{ __('Aksi') }}
                                </th>
                            </tr>
                        </thead>

                        <SubmissionQueueRow
                            v-for="submission in submissions.data"
                            :key="submission.id"
                            :submission="submission"
                            :selected="selectedIds.includes(submission.id)"
                            @toggle-select="toggleSelect"
                            @review="handleReview"
                        />
                    </table>
                </div>

                <div
                    v-if="submissions.data.length === 0"
                    class="px-6 py-16 text-center"
                    data-test="approval-empty-state"
                >
                    <ClipboardCheck
                        class="mx-auto mb-3 size-10 text-slate-300 dark:text-slate-600"
                    />
                    <p
                        class="text-sm font-semibold text-slate-700 dark:text-slate-200"
                    >
                        {{ __('Tidak ada pengajuan menunggu review.') }}
                    </p>
                    <p class="mt-1 text-xs text-slate-500">
                        {{
                            __(
                                'Ubah filter status atau rentang tanggal untuk melihat pengajuan lain.',
                            )
                        }}
                    </p>
                </div>

                <div
                    v-if="submissions.last_page > 1"
                    class="flex items-center justify-between border-t border-slate-200 px-4 py-3 dark:border-slate-800"
                    data-test="approval-pagination"
                >
                    <p class="text-[11px] text-slate-500">
                        {{
                            __('Menampilkan :from–:to dari :total', {
                                from: submissions.from ?? 0,
                                to: submissions.to ?? 0,
                                total: submissions.total,
                            })
                        }}
                    </p>
                    <div class="flex items-center gap-1">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 px-2"
                            :disabled="!submissions.prev_page_url"
                            data-test="pagination-prev"
                            @click="
                                handlePagination(
                                    submissions.prev_page_url ?? null,
                                )
                            "
                        >
                            <ChevronLeft class="size-4" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-8 px-2"
                            :disabled="!submissions.next_page_url"
                            data-test="pagination-next"
                            @click="
                                handlePagination(
                                    submissions.next_page_url ?? null,
                                )
                            "
                        >
                            <ChevronRight class="size-4" />
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- E04-02: Item-Level Approval Modal -->
        <ApprovalModal
            v-model:open="isApprovalModalOpen"
            :submission-id="selectedSubmissionId"
            :initial-data="selectedSubmissionData as any"
            @saved="handleApprovalSaved"
        />

        <!-- E04-03: Floating Bulk Action Bar -->
        <transition
            enter-active-class="transition-all duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-8"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition-all duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-8"
        >
            <div
                v-if="selectedIds.length > 0"
                class="fixed bottom-6 left-1/2 z-40 flex w-[calc(100%-2rem)] max-w-xl -translate-x-1/2 items-center gap-3 rounded-xl border border-slate-700 bg-slate-900/95 px-4 py-3 text-white shadow-2xl backdrop-blur-md sm:w-auto dark:border-slate-600 dark:bg-slate-800/95"
                data-test="floating-bulk-bar"
            >
                <div
                    class="flex items-center gap-2 border-r border-slate-700 pr-2"
                >
                    <span
                        class="flex size-6 items-center justify-center rounded-full bg-[#cc0000] font-mono text-xs font-bold text-white tabular-nums"
                        data-test="bulk-selected-count"
                    >
                        {{ selectedIds.length }}
                    </span>
                    <span
                        class="hidden text-xs font-semibold text-slate-200 sm:inline"
                    >
                        {{ __('Pengajuan Dipilih') }}
                    </span>
                </div>

                <div
                    class="flex items-center gap-3 font-mono text-xs text-slate-300 tabular-nums"
                >
                    <span
                        class="inline-flex items-center gap-1"
                        data-test="bulk-headcount-stat"
                    >
                        <Users class="size-3.5 text-slate-400" />
                        {{ selectedSubmissionsHeadcount }} {{ __('Karyawan') }}
                    </span>
                    <span
                        class="inline-flex items-center gap-1"
                        data-test="bulk-hours-stat"
                    >
                        <Clock class="size-3.5 text-slate-400" />
                        {{ selectedSubmissionsHours.toFixed(1) }}
                        {{ __('Jam') }}
                    </span>
                </div>

                <div class="flex items-center gap-2 pl-2">
                    <Button
                        type="button"
                        size="sm"
                        class="h-8 bg-[#cc0000] px-3 text-xs text-white hover:bg-[#b30000]"
                        data-test="btn-bulk-approve"
                        @click="triggerBulkApprove"
                    >
                        <CheckCheck class="mr-1.5 size-3.5" />
                        {{ __('Setujui Terpilih') }}
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        class="h-8 bg-red-600 px-3 text-xs text-white hover:bg-red-700"
                        data-test="btn-bulk-reject"
                        @click="triggerBulkReject"
                    >
                        <XCircle class="mr-1.5 size-3.5" />
                        {{ __('Tolak Terpilih') }}
                    </Button>
                    <button
                        type="button"
                        class="p-1 text-xs text-slate-400 hover:text-white"
                        :title="__('Batal Pilihan')"
                        data-test="btn-bulk-clear"
                        @click="clearSelection"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>
        </transition>

        <!-- E04-03: Bulk Action Result Toast -->
        <BulkActionResultToast
            v-if="bulkResult"
            :result="bulkResult"
            @close="bulkResult = null"
        />

        <!-- E04-03: Bulk Action Confirmation Modal -->
        <BulkApprovalConfirmModal
            v-model:open="isBulkConfirmModalOpen"
            :action="bulkActionType"
            :selected-submissions="selectedSubmissions"
            :is-processing="isBulkProcessing"
            @confirm="handleBulkConfirm"
        />
    </div>
</template>
