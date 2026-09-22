<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    Calendar,
    CheckCheck,
    CheckCircle2,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useTrans } from '@/composables/useTrans';
import { approve as approveGroupRoute } from '@/routes/overtime/approvals/spl';
import { bulk as bulkRoute } from '@/routes/overtime/approvals';
import { approvals as approvalsRoute } from '@/routes/overtime';
import { dashboard } from '@/routes';
import type { User } from '@/types';

// ─── Types ──────────────────────────────────────────────────────────────────

interface SplEntryItem {
    id: number;
    npk_snapshot: string;
    employee_name_snapshot: string;
    start_time: string;
    end_time: string;
    total_hours: string | number;
    jenis_pekerjaan: string | null;
    type_ot_code: number | null;
    keterangan_lembur: string | null;
    day_type: string;
    status: 'PENDING' | 'APPROVED' | 'REJECTED';
    lock_version: number;
    reviewed_at?: string | null;
    rejection_reason?: string | null;
}

interface SplGroup {
    section_id: number;
    date: string;
    day_type: 'HKN' | 'HLR';
    entries_count: number;
    total_hours: number;
    pending_count: number;
    approved_count: number;
    rejected_count: number;
    status: 'PENDING' | 'APPROVED' | 'REJECTED' | 'PARTIALLY_APPROVED';
    section: { id: number; name: string; code: string } | null;
    department: { id: number; name: string; code: string } | null;
}

interface Pagination {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from?: number | null;
    to?: number | null;
    links: Array<{ url: string | null; label: string; active: boolean }>;
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

// ─── Props ───────────────────────────────────────────────────────────────────

const props = defineProps<{
    groups: SplGroup[];
    pagination: Pagination;
    available_sections?: SectionOption[];
    available_departments?: DepartmentOption[];
    pending_count: number;
    pending_hours: number;
    filters: {
        status?: string;
        department_id?: string | number;
        section_id?: string | number;
        date_from?: string;
        date_to?: string;
        sort?: string;
        direction?: string;
    };
}>();

// ─── Layout ──────────────────────────────────────────────────────────────────

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Persetujuan Lembur', href: approvalsRoute() },
        ],
    },
});

const { __ } = useTrans();
const page = usePage();
const user = computed(() => page.props.auth?.user as User | null);

// ─── Filters ─────────────────────────────────────────────────────────────────

const selectedStatus = ref(props.filters.status ?? 'PENDING');
const selectedDepartment = ref(
    props.filters.department_id ? String(props.filters.department_id) : '',
);
const selectedSection = ref(
    props.filters.section_id ? String(props.filters.section_id) : '',
);
const dateFrom = ref(props.filters.date_from ?? '');
const dateTo = ref(props.filters.date_to ?? '');
const sortBy = ref(props.filters.sort ?? 'date');
const sortDirection = ref(props.filters.direction ?? 'desc');

const statusTabs = [
    { value: 'PENDING', label: 'Menunggu' },
    { value: 'PARTIALLY_APPROVED', label: 'Sebagian' },
    { value: 'APPROVED', label: 'Disetujui' },
    { value: 'REJECTED', label: 'Ditolak' },
    { value: 'ALL', label: 'Semua' },
];

function applyFilters(overrides: Record<string, string> = {}) {
    const params: Record<string, string> = {};
    const status = overrides.status ?? selectedStatus.value;
    if (status && status !== 'PENDING') {
        params.status = status;
    }
    const dept = overrides.department_id ?? selectedDepartment.value;
    if (dept) {
        params.department_id = dept;
    }
    const sec = overrides.section_id ?? selectedSection.value;
    if (sec) {
        params.section_id = sec;
    }
    if (dateFrom.value) {
        params.date_from = dateFrom.value;
    }
    if (dateTo.value) {
        params.date_to = dateTo.value;
    }
    if (sortBy.value !== 'date') {
        params.sort = sortBy.value;
    }
    if (sortDirection.value !== 'desc') {
        params.direction = sortDirection.value;
    }
    router.get(approvalsRoute(), params, { preserveScroll: true });
}

function resetFilters() {
    selectedStatus.value = 'PENDING';
    selectedDepartment.value = '';
    selectedSection.value = '';
    dateFrom.value = '';
    dateTo.value = '';
    sortBy.value = 'date';
    sortDirection.value = 'desc';
    applyFilters();
}

watch(selectedDepartment, () => {
    selectedSection.value = '';
});

// ─── Expand/Collapse ─────────────────────────────────────────────────────────

const expandedGroups = ref<Set<string>>(new Set());
const groupEntries = ref<Record<string, SplEntryItem[]>>({});
const loadingGroups = ref<Set<string>>(new Set());

function groupKey(g: SplGroup): string {
    return `${g.section_id}|${g.date}`;
}

async function toggleGroup(group: SplGroup) {
    const key = groupKey(group);
    if (expandedGroups.value.has(key)) {
        expandedGroups.value.delete(key);
        return;
    }
    expandedGroups.value.add(key);
    if (groupEntries.value[key]) return; // already loaded

    loadingGroups.value.add(key);
    try {
        const res = await fetch(
            `/overtime/approvals/spl/entries?section_id=${group.section_id}&date=${group.date}`,
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );
        if (res.ok) {
            groupEntries.value[key] = await res.json();
        }
    } finally {
        loadingGroups.value.delete(key);
    }
}

// ─── Approval Modal ───────────────────────────────────────────────────────────

interface Decision {
    entry_id: number;
    action: 'APPROVED' | 'REJECTED';
    lock_version: number;
    rejection_reason?: string;
}

const approvalModal = ref<{ group: SplGroup; entries: SplEntryItem[] } | null>(
    null,
);
const approvalDecisions = ref<Record<number, Decision>>({});
const globalAction = ref<'APPROVED' | 'REJECTED'>('APPROVED');
const globalRejReason = ref('');
const approving = ref(false);
const approvalError = ref<string | null>(null);

function openApprovalModal(group: SplGroup) {
    const key = groupKey(group);
    const entries = (groupEntries.value[key] ?? []).filter(
        (e) => e.status === 'PENDING',
    );
    if (!entries.length) return;
    approvalModal.value = { group, entries };
    approvalDecisions.value = Object.fromEntries(
        entries.map((e) => [
            e.id,
            {
                entry_id: e.id,
                action: 'APPROVED',
                lock_version: e.lock_version,
            },
        ]),
    );
    globalAction.value = 'APPROVED';
    globalRejReason.value = '';
    approvalError.value = null;
}

function setGlobalAction(action: 'APPROVED' | 'REJECTED') {
    globalAction.value = action;
    Object.values(approvalDecisions.value).forEach((d) => {
        d.action = action;
    });
}

async function submitApproval() {
    if (!approvalModal.value) return;
    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';
    approving.value = true;
    approvalError.value = null;

    const decisions = Object.values(approvalDecisions.value).map((d) => ({
        ...d,
        rejection_reason:
            d.action === 'REJECTED' ? globalRejReason.value.trim() : undefined,
    }));

    try {
        const res = await fetch(approveGroupRoute().url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                section_id: approvalModal.value.group.section_id,
                date: approvalModal.value.group.date,
                decisions,
            }),
        });
        const data = await res.json();
        if (!res.ok) {
            approvalError.value = data.message ?? 'Terjadi kesalahan.';
            return;
        }

        // Invalidate cached entries so next open refetches
        const key = groupKey(approvalModal.value.group);
        delete groupEntries.value[key];
        approvalModal.value = null;

        router.reload({
            only: ['groups', 'pagination', 'pending_count', 'pending_hours'],
        });
    } catch {
        approvalError.value = 'Kesalahan jaringan. Coba lagi.';
    } finally {
        approving.value = false;
    }
}

// ─── Bulk Approval ────────────────────────────────────────────────────────────

const selectedGroupKeys = ref<Set<string>>(new Set());
const bulkModal = ref(false);
const bulkAction = ref<'APPROVED' | 'REJECTED'>('APPROVED');
const bulkRejReason = ref('');
const bulkProcessing = ref(false);
const bulkResult = ref<{
    message: string;
    processed: number;
    skipped: number;
} | null>(null);

const selectedGroups = computed(() =>
    props.groups.filter((g) => selectedGroupKeys.value.has(groupKey(g))),
);

function toggleGroupSelect(group: SplGroup) {
    const key = groupKey(group);
    if (selectedGroupKeys.value.has(key)) selectedGroupKeys.value.delete(key);
    else selectedGroupKeys.value.add(key);
}

function selectAll() {
    props.groups
        .filter((g) => g.pending_count > 0)
        .forEach((g) => selectedGroupKeys.value.add(groupKey(g)));
}
function clearSelection() {
    selectedGroupKeys.value.clear();
}

async function submitBulk() {
    if (!selectedGroups.value.length) return;
    bulkProcessing.value = true;
    const csrfToken =
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? '';

    try {
        const res = await fetch(bulkRoute().url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({
                groups: selectedGroups.value.map((g) => ({
                    section_id: g.section_id,
                    date: g.date,
                })),
                action: bulkAction.value,
                rejection_reason:
                    bulkAction.value === 'REJECTED'
                        ? bulkRejReason.value
                        : undefined,
            }),
        });
        const data = await res.json();
        bulkResult.value = data;
        bulkModal.value = false;
        selectedGroupKeys.value.clear();
        // Clear cached entries
        Object.keys(groupEntries.value).forEach(
            (k) => delete groupEntries.value[k],
        );
        router.reload({
            only: ['groups', 'pagination', 'pending_count', 'pending_hours'],
        });
    } finally {
        bulkProcessing.value = false;
    }
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const statusBadgeClass: Record<string, string> = {
    PENDING:
        'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
    APPROVED:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300',
    REJECTED: 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300',
    PARTIALLY_APPROVED:
        'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300',
};

const statusLabel: Record<string, string> = {
    PENDING: 'Menunggu',
    APPROVED: 'Disetujui',
    REJECTED: 'Ditolak',
    PARTIALLY_APPROVED: 'Sebagian',
};

function formatTime(t: string): string {
    return t ? t.substring(0, 5) : '-';
}

function formatDate(d: string): string {
    if (!d) return '-';
    return new Date(d).toLocaleDateString('id-ID', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <Head :title="__('Persetujuan Lembur')" />

    <div class="flex h-full flex-1 flex-col p-4 sm:p-6">
        <!-- Header -->
        <div class="mb-6 flex items-start justify-between gap-4">
            <Heading
                :title="__('Persetujuan Lembur')"
                :description="
                    __('Tinjau dan setujui data lembur dari upload SPL.')
                "
            />
            <div class="flex items-center gap-2">
                <span
                    v-if="pending_count > 0"
                    class="rounded-full bg-[#cc0000] px-2.5 py-0.5 text-xs font-bold text-white"
                    data-test="pending-count-pill"
                >
                    {{ pending_count }} {{ __('grup pending') }}
                </span>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div
                class="rounded-lg border bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <ClipboardCheck class="size-4 text-amber-500" />{{
                        __('Grup Pending')
                    }}
                </div>
                <div
                    class="mt-1 text-2xl font-bold text-slate-900 tabular-nums dark:text-white"
                >
                    {{ pending_count }}
                </div>
            </div>
            <div
                class="rounded-lg border bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <Clock class="size-4 text-blue-500" />{{
                        __('Jam Pending')
                    }}
                </div>
                <div
                    class="mt-1 text-2xl font-bold text-slate-900 tabular-nums dark:text-white"
                >
                    {{ pending_hours.toFixed(1) }}
                </div>
            </div>
            <div
                class="rounded-lg border bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <Users class="size-4 text-slate-400" />{{
                        __('Total Grup')
                    }}
                </div>
                <div
                    class="mt-1 text-2xl font-bold text-slate-900 tabular-nums dark:text-white"
                >
                    {{ pagination.total }}
                </div>
            </div>
            <div
                class="rounded-lg border bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900"
            >
                <div class="flex items-center gap-2 text-xs text-slate-500">
                    <Calendar class="size-4 text-slate-400" />{{
                        __('Halaman')
                    }}
                </div>
                <div
                    class="mt-1 text-2xl font-bold text-slate-900 tabular-nums dark:text-white"
                >
                    {{ pagination.current_page }}/{{ pagination.last_page }}
                </div>
            </div>
        </div>

        <!-- Filter Toolbar -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <!-- Status tabs -->
            <div class="flex flex-wrap gap-1">
                <button
                    v-for="tab in statusTabs"
                    :key="tab.value"
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                    :data-test="`status-tab-${tab.value.toLowerCase()}`"
                    :class="
                        selectedStatus === tab.value
                            ? 'border-[#cc0000] bg-[#cc0000] text-white'
                            : 'border-slate-200 text-slate-600 hover:border-slate-400 dark:border-slate-700 dark:text-slate-300'
                    "
                    @click="
                        selectedStatus = tab.value;
                        applyFilters();
                    "
                >
                    {{ tab.label }}
                </button>
            </div>

            <div class="flex flex-1 flex-wrap items-center gap-2">
                <template v-if="available_departments?.length">
                    <Select
                        v-model="selectedDepartment"
                        @update:model-value="applyFilters()"
                    >
                        <SelectTrigger class="h-8 w-40 text-xs">
                            <SelectValue :placeholder="__('Dept')" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">{{
                                __('Semua Dept')
                            }}</SelectItem>
                            <SelectItem
                                v-for="d in available_departments"
                                :key="d.id"
                                :value="String(d.id)"
                                >{{ d.code }}</SelectItem
                            >
                        </SelectContent>
                    </Select>
                </template>
                <Select
                    v-model="selectedSection"
                    @update:model-value="applyFilters()"
                >
                    <SelectTrigger class="h-8 w-40 text-xs">
                        <SelectValue :placeholder="__('Seksi')" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="">{{
                            __('Semua Seksi')
                        }}</SelectItem>
                        <SelectItem
                            v-for="s in available_sections"
                            :key="s.id"
                            :value="String(s.id)"
                            >{{ s.name }}</SelectItem
                        >
                    </SelectContent>
                </Select>
                <Input
                    v-model="dateFrom"
                    type="date"
                    class="h-8 w-36 text-xs"
                    @change="applyFilters()"
                />
                <span class="text-xs text-slate-400">—</span>
                <Input
                    v-model="dateTo"
                    type="date"
                    class="h-8 w-36 text-xs"
                    @change="applyFilters()"
                />
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-8 text-xs"
                    @click="resetFilters"
                >
                    <RotateCcw class="mr-1 size-3.5" />{{ __('Reset') }}
                </Button>
            </div>
        </div>

        <!-- Bulk Action Bar -->
        <div
            v-if="selectedGroupKeys.size > 0"
            class="mb-3 flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 dark:border-amber-700 dark:bg-amber-900/30"
            data-test="floating-bulk-bar"
        >
            <span
                class="text-xs font-semibold text-amber-800 dark:text-amber-200"
                data-test="bulk-selected-count"
                >{{ selectedGroupKeys.size }} {{ __('grup dipilih') }}</span
            >
            <Button
                size="sm"
                class="h-7 bg-emerald-600 text-xs text-white hover:bg-emerald-700"
                data-test="btn-bulk-approve"
                @click="
                    bulkAction = 'APPROVED';
                    bulkModal = true;
                "
            >
                <CheckCheck class="mr-1 size-3.5" />{{ __('Setujui Semua') }}
            </Button>
            <Button
                size="sm"
                class="h-7 bg-red-600 text-xs text-white hover:bg-red-700"
                data-test="btn-bulk-reject"
                @click="
                    bulkAction = 'REJECTED';
                    bulkModal = true;
                "
            >
                <XCircle class="mr-1 size-3.5" />{{ __('Tolak Semua') }}
            </Button>
            <Button
                variant="ghost"
                size="sm"
                class="ml-auto h-7 text-xs"
                data-test="btn-bulk-clear"
                @click="clearSelection"
            >
                <X class="size-3.5" />
            </Button>
        </div>

        <!-- Table -->
        <div
            class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
        >
            <div
                class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5 dark:border-slate-800"
            >
                <span
                    class="text-xs font-semibold text-slate-600 dark:text-slate-400"
                >
                    {{ pagination.from }}–{{ pagination.to }} /
                    {{ pagination.total }} {{ __('grup') }}
                </span>
                <button
                    type="button"
                    class="text-xs text-[#cc0000] hover:underline"
                    @click="selectAll"
                >
                    {{ __('Pilih semua pending di halaman ini') }}
                </button>
            </div>

            <!-- Empty state -->
            <div
                v-if="!groups.length"
                class="py-16 text-center text-sm text-slate-400"
                data-test="approval-empty-state"
            >
                <ClipboardCheck class="mx-auto mb-2 size-10 opacity-30" />
                {{ __('Tidak ada data lembur untuk ditinjau.') }}
            </div>

            <template v-for="group in groups" :key="groupKey(group)">
                <!-- Group row -->
                <div
                    class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 transition-colors last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40"
                    :class="
                        selectedGroupKeys.has(groupKey(group))
                            ? 'bg-amber-50 dark:bg-amber-900/10'
                            : ''
                    "
                >
                    <!-- Checkbox -->
                    <input
                        type="checkbox"
                        class="size-4 cursor-pointer rounded accent-[#cc0000]"
                        :checked="selectedGroupKeys.has(groupKey(group))"
                        :disabled="group.pending_count === 0"
                        :data-test="`approval-row-checkbox-${groupKey(group)}`"
                        @change="toggleGroupSelect(group)"
                    />

                    <!-- Date + day type -->
                    <div class="w-36 shrink-0">
                        <div
                            class="text-xs font-semibold text-slate-800 tabular-nums dark:text-white"
                        >
                            {{ formatDate(group.date) }}
                        </div>
                        <span
                            class="text-[10px] font-bold"
                            :class="
                                group.day_type === 'HLR'
                                    ? 'text-[#cc0000]'
                                    : 'text-slate-400'
                            "
                            >{{ group.day_type }}</span
                        >
                    </div>

                    <!-- Section -->
                    <div class="min-w-0 flex-1">
                        <div
                            class="truncate text-sm font-semibold text-slate-900 dark:text-white"
                        >
                            {{ group.section?.name ?? '-' }}
                        </div>
                        <div class="text-xs text-slate-500">
                            {{ group.department?.name ?? '-' }}
                        </div>
                    </div>

                    <!-- Counts -->
                    <div
                        class="flex shrink-0 items-center gap-3 text-xs text-slate-500"
                    >
                        <span class="flex items-center gap-1 tabular-nums">
                            <Users class="size-3.5" />{{ group.entries_count }}
                        </span>
                        <span class="flex items-center gap-1 tabular-nums">
                            <Clock class="size-3.5" />{{
                                group.total_hours.toFixed(1)
                            }}j
                        </span>
                    </div>

                    <!-- Status badge -->
                    <span
                        class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
                        :class="
                            statusBadgeClass[group.status] ??
                            statusBadgeClass.PENDING
                        "
                    >
                        {{ statusLabel[group.status] ?? group.status }}
                    </span>

                    <!-- Actions -->
                    <div class="flex shrink-0 items-center gap-1">
                        <Button
                            v-if="group.pending_count > 0"
                            size="sm"
                            class="h-7 bg-[#cc0000] px-2.5 text-xs text-white hover:bg-[#b30000]"
                            :disabled="
                                !groupEntries[groupKey(group)]?.length &&
                                loadingGroups.has(groupKey(group))
                            "
                            @click="
                                async () => {
                                    if (!groupEntries[groupKey(group)])
                                        await toggleGroup(group);
                                    openApprovalModal(group);
                                }
                            "
                        >
                            <CheckCircle2 class="mr-1 size-3.5" />{{
                                __('Tinjau')
                            }}
                        </Button>
                        <button
                            type="button"
                            class="rounded p-1 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700"
                            :data-test="`expand-row-${groupKey(group)}`"
                            @click="toggleGroup(group)"
                        >
                            <ChevronDown
                                v-if="!expandedGroups.has(groupKey(group))"
                                class="size-4"
                            />
                            <ChevronUp v-else class="size-4" />
                        </button>
                    </div>
                </div>

                <!-- Expanded entries -->
                <div
                    v-if="expandedGroups.has(groupKey(group))"
                    class="border-b border-slate-100 bg-slate-50 dark:border-slate-800 dark:bg-slate-950"
                >
                    <div
                        v-if="loadingGroups.has(groupKey(group))"
                        class="py-4 text-center text-xs text-slate-400"
                    >
                        Memuat data...
                    </div>
                    <div
                        v-else-if="!groupEntries[groupKey(group)]?.length"
                        class="py-4 text-center text-xs text-slate-400"
                    >
                        Klik tombol di atas untuk memuat detail.
                    </div>
                    <table v-else class="w-full text-xs">
                        <thead
                            class="border-b border-slate-200 bg-slate-100 text-[10px] font-semibold text-slate-500 uppercase dark:border-slate-700 dark:bg-slate-900"
                        >
                            <tr>
                                <th class="px-4 py-1.5 text-left">Karyawan</th>
                                <th class="px-3 py-1.5 text-left">
                                    Mulai–Selesai
                                </th>
                                <th class="px-3 py-1.5 text-right">Jam</th>
                                <th class="px-3 py-1.5 text-left">Pekerjaan</th>
                                <th class="px-3 py-1.5 text-left">Status</th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-slate-100 dark:divide-slate-800"
                        >
                            <tr
                                v-for="entry in groupEntries[groupKey(group)]"
                                :key="entry.id"
                                class="hover:bg-white dark:hover:bg-slate-900"
                            >
                                <td class="px-4 py-2">
                                    <div
                                        class="font-semibold text-slate-900 dark:text-white"
                                    >
                                        {{ entry.employee_name_snapshot }}
                                    </div>
                                    <div class="text-slate-400">
                                        {{ entry.npk_snapshot }}
                                    </div>
                                </td>
                                <td
                                    class="px-3 py-2 font-mono text-slate-600 dark:text-slate-400"
                                >
                                    {{ formatTime(entry.start_time) }} –
                                    {{ formatTime(entry.end_time) }}
                                </td>
                                <td
                                    class="px-3 py-2 text-right font-mono font-bold text-slate-900 dark:text-white"
                                >
                                    {{ Number(entry.total_hours).toFixed(1) }}
                                </td>
                                <td
                                    class="max-w-[200px] px-3 py-2 text-slate-500"
                                >
                                    <div class="truncate">
                                        {{ entry.jenis_pekerjaan ?? '-' }}
                                    </div>
                                    <div
                                        v-if="entry.keterangan_lembur"
                                        class="truncate text-slate-400"
                                    >
                                        {{ entry.keterangan_lembur }}
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                        :class="
                                            statusBadgeClass[entry.status] ??
                                            statusBadgeClass.PENDING
                                        "
                                    >
                                        {{
                                            statusLabel[entry.status] ??
                                            entry.status
                                        }}
                                    </span>
                                    <div
                                        v-if="entry.rejection_reason"
                                        class="mt-0.5 text-[10px] text-red-500"
                                    >
                                        {{ entry.rejection_reason }}
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>

        <!-- Pagination -->
        <div
            v-if="pagination.last_page > 1"
            class="mt-4 flex items-center justify-center gap-1"
        >
            <Button
                variant="outline"
                size="sm"
                class="h-7 w-7 p-0"
                :disabled="!pagination.prev_page_url"
                @click="
                    router.get(
                        pagination.prev_page_url!,
                        {},
                        { preserveScroll: true },
                    )
                "
            >
                <ChevronLeft class="size-4" />
            </Button>
            <span class="text-xs text-slate-500"
                >{{ pagination.current_page }} /
                {{ pagination.last_page }}</span
            >
            <Button
                variant="outline"
                size="sm"
                class="h-7 w-7 p-0"
                :disabled="!pagination.next_page_url"
                @click="
                    router.get(
                        pagination.next_page_url!,
                        {},
                        { preserveScroll: true },
                    )
                "
            >
                <ChevronRight class="size-4" />
            </Button>
        </div>

        <!-- ─── Approval Modal ──────────────────────────────────────────────── -->
        <Dialog
            :open="!!approvalModal"
            @update:open="
                (v) => {
                    if (!v && !approving) approvalModal = null;
                }
            "
        >
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ __('Tinjau Lembur') }}</DialogTitle>
                    <DialogDescription v-if="approvalModal">
                        {{ approvalModal.group.section?.name }} ·
                        {{ formatDate(approvalModal.group.date) }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="approvalModal" class="space-y-3">
                    <!-- Global action selector -->
                    <div class="flex gap-2">
                        <button
                            type="button"
                            class="flex-1 rounded-lg border py-2 text-xs font-bold transition-colors"
                            :class="
                                globalAction === 'APPROVED'
                                    ? 'border-emerald-500 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 text-slate-500 hover:border-emerald-300'
                            "
                            @click="setGlobalAction('APPROVED')"
                        >
                            <CheckCircle2 class="mx-auto mb-0.5 size-4" />{{
                                __('Setujui Semua')
                            }}
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-lg border py-2 text-xs font-bold transition-colors"
                            :class="
                                globalAction === 'REJECTED'
                                    ? 'border-red-500 bg-red-50 text-red-700'
                                    : 'border-slate-200 text-slate-500 hover:border-red-300'
                            "
                            @click="setGlobalAction('REJECTED')"
                        >
                            <XCircle class="mx-auto mb-0.5 size-4" />{{
                                __('Tolak Semua')
                            }}
                        </button>
                    </div>

                    <!-- Rejection reason -->
                    <div v-if="globalAction === 'REJECTED'">
                        <textarea
                            v-model="globalRejReason"
                            rows="3"
                            class="w-full rounded-md border border-slate-300 p-2 text-xs focus:border-[#cc0000] focus:outline-none"
                            :placeholder="
                                __('Alasan penolakan (min. 5 karakter)...')
                            "
                        />
                    </div>

                    <!-- Entry list -->
                    <div
                        class="max-h-60 space-y-1 overflow-y-auto rounded border border-slate-100 p-2 dark:border-slate-800"
                    >
                        <div
                            v-for="entry in approvalModal.entries"
                            :key="entry.id"
                            class="flex items-center justify-between gap-2 text-xs"
                        >
                            <div class="min-w-0">
                                <span
                                    class="font-semibold text-slate-900 dark:text-white"
                                    >{{ entry.employee_name_snapshot }}</span
                                >
                                <span class="ml-1 text-slate-400">{{
                                    entry.npk_snapshot
                                }}</span>
                            </div>
                            <span class="text-slate-600 tabular-nums"
                                >{{
                                    Number(entry.total_hours).toFixed(1)
                                }}j</span
                            >
                        </div>
                    </div>

                    <p v-if="approvalError" class="text-xs text-red-600">
                        {{ approvalError }}
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="approving"
                        @click="approvalModal = null"
                        >{{ __('Batal') }}</Button
                    >
                    <Button
                        size="sm"
                        :disabled="
                            approving ||
                            (globalAction === 'REJECTED' &&
                                globalRejReason.trim().length < 5)
                        "
                        :class="
                            globalAction === 'REJECTED'
                                ? 'bg-red-600 hover:bg-red-700'
                                : 'bg-[#cc0000] hover:bg-[#b30000]'
                        "
                        @click="submitApproval"
                    >
                        {{
                            approving
                                ? __('Memproses...')
                                : globalAction === 'APPROVED'
                                  ? __('Konfirmasi & Setujui')
                                  : __('Konfirmasi & Tolak')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ─── Bulk Confirmation Modal ──────────────────────────────────── -->
        <Dialog
            :open="bulkModal"
            @update:open="
                (v) => {
                    if (!v && !bulkProcessing) bulkModal = false;
                }
            "
        >
            <DialogContent
                class="max-w-md"
                data-test="bulk-approval-confirm-modal"
            >
                <DialogHeader>
                    <DialogTitle data-test="bulk-modal-title">{{
                        bulkAction === 'APPROVED'
                            ? __('Setujui Massal')
                            : __('Tolak Massal')
                    }}</DialogTitle>
                    <DialogDescription
                        >{{ selectedGroups.length }}
                        {{ __('grup dipilih') }}</DialogDescription
                    >
                </DialogHeader>
                <div class="space-y-3">
                    <div
                        class="max-h-36 overflow-y-auto rounded border border-slate-100 p-2 text-xs dark:border-slate-800"
                    >
                        <div
                            v-for="g in selectedGroups"
                            :key="groupKey(g)"
                            class="flex items-center justify-between py-0.5"
                        >
                            <span class="font-semibold">{{
                                g.section?.name ?? '-'
                            }}</span>
                            <span class="text-slate-500 tabular-nums"
                                >{{ g.date }} · {{ g.pending_count }} item</span
                            >
                        </div>
                    </div>
                    <div
                        v-if="bulkAction === 'REJECTED'"
                        data-test="bulk-rejection-reason-container"
                    >
                        <textarea
                            v-model="bulkRejReason"
                            rows="3"
                            class="w-full rounded-md border p-2 text-xs"
                            data-test="bulk-rejection-reason-input"
                            :placeholder="__('Alasan penolakan...')"
                        />
                    </div>
                </div>
                <DialogFooter>
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="bulkProcessing"
                        @click="bulkModal = false"
                        >{{ __('Batal') }}</Button
                    >
                    <Button
                        size="sm"
                        :disabled="
                            bulkProcessing ||
                            (bulkAction === 'REJECTED' &&
                                bulkRejReason.trim().length < 5)
                        "
                        :class="
                            bulkAction === 'REJECTED'
                                ? 'bg-red-600 hover:bg-red-700'
                                : 'bg-[#cc0000] hover:bg-[#b30000]'
                        "
                        data-test="btn-bulk-confirm"
                        @click="submitBulk"
                        >{{
                            bulkProcessing
                                ? __('Memproses...')
                                : __('Konfirmasi')
                        }}</Button
                    >
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <!-- ─── Bulk Result Toast ────────────────────────────────────────── -->
        <div
            v-if="bulkResult"
            class="fixed top-5 right-5 z-50 rounded-lg border border-emerald-300 bg-emerald-50 px-4 py-3 shadow-lg"
            data-test="bulk-action-result-toast"
        >
            <div class="flex items-center gap-2">
                <CheckCircle2 class="size-4 text-emerald-600" />
                <span class="text-xs font-semibold">{{
                    bulkResult.message
                }}</span>
                <button type="button" @click="bulkResult = null">
                    <X class="size-4 text-slate-400" />
                </button>
            </div>
        </div>
    </div>
</template>
