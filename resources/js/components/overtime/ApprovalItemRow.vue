<script setup lang="ts">
import {
    AlertTriangle,
    Bot,
    Check,
    Clock,
    FolderKanban,
    History,
    X,
} from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';

export type ItemDecisionAction = 'APPROVED' | 'REJECTED' | 'PENDING';

export interface ItemDecision {
    item_id: number;
    action: ItemDecisionAction;
    rejection_reason?: string;
    lock_version: number;
}

export interface ApprovalItemData {
    id: number;
    employee_id: number;
    npk_snapshot: string;
    hours_production: string | number;
    hours_tpm: string | number;
    hours_project: string | number;
    hours_others: string | number;
    total_hours: string | number;
    hourly_rate_snapshot: string | number;
    total_cost_snapshot: string | number;
    rca_category?: string | null;
    rca_notes?: string | null;
    task_description?: string | null;
    status: string;
    rejection_reason?: string | null;
    lock_version: number;
    employee?: {
        id: number;
        npk: string;
        full_name: string;
        job_position: string;
        hourly_rate?: number | string | null;
    } | null;
    capex_project?: {
        id: number;
        project_code: string;
        name: string;
    } | null;
    anomaly_logs?: Array<{
        id: number;
        anomaly_score?: string | number;
        anomaly_reasons?: string[] | Record<string, unknown>;
        is_dismissed?: boolean;
    }>;
    policy_warning?: {
        level: 'none' | 'warning' | 'danger';
        message: string;
        weekly_total: number;
        consecutive_weeks: number;
        weekly_limit: number;
    } | null;
}

const props = defineProps<{
    item: ApprovalItemData;
    decision: ItemDecision;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:decision', value: ItemDecision): void;
    (e: 'openAudit', value: number): void;
}>();

const { __ } = useTrans();

function toNumber(val: string | number | null | undefined): number {
    if (val === null || val === undefined || val === '') {
        return 0;
    }
    const n = typeof val === 'string' ? parseFloat(val) : val;
    return Number.isNaN(n) ? 0 : n;
}

const activeAnomalyReasons = computed<string[]>(() => {
    if (!props.item.anomaly_logs || props.item.anomaly_logs.length === 0) {
        return [];
    }
    const reasons: string[] = [];
    for (const log of props.item.anomaly_logs) {
        if (!log.is_dismissed) {
            if (Array.isArray(log.anomaly_reasons)) {
                reasons.push(...log.anomaly_reasons.map(String));
            } else if (
                typeof log.anomaly_reasons === 'object' &&
                log.anomaly_reasons !== null
            ) {
                reasons.push(...Object.values(log.anomaly_reasons).map(String));
            } else if (log.anomaly_reasons) {
                reasons.push(String(log.anomaly_reasons));
            }
        }
    }
    return reasons;
});

const isRejectionReasonInvalid = computed(() => {
    if (props.decision.action !== 'REJECTED') {
        return false;
    }
    const reason = props.decision.rejection_reason?.trim() ?? '';
    return reason.length < 5;
});

function setAction(action: ItemDecisionAction) {
    if (props.disabled) {
        return;
    }
    emit('update:decision', {
        ...props.decision,
        action,
        rejection_reason:
            action === 'REJECTED'
                ? props.decision.rejection_reason ||
                  props.item.rejection_reason ||
                  ''
                : props.decision.rejection_reason,
    });
}

function updateRejectionReason(event: Event) {
    const val = (event.target as HTMLTextAreaElement).value;
    emit('update:decision', {
        ...props.decision,
        rejection_reason: val,
    });
}
</script>

<template>
    <div
        class="rounded-lg border border-slate-200 bg-white p-3.5 transition-all dark:border-slate-800 dark:bg-slate-900"
        :class="{
            'border-emerald-300 ring-1 ring-emerald-200 dark:border-emerald-800 dark:ring-emerald-900/50':
                decision.action === 'APPROVED',
            'border-red-300 ring-1 ring-red-200 dark:border-red-800 dark:ring-red-900/50':
                decision.action === 'REJECTED',
            'border-slate-200 dark:border-slate-800':
                decision.action === 'PENDING',
        }"
        :data-test="`item-row-${item.id}`"
    >
        <!-- Header Info Row -->
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <div class="space-y-0.5">
                    <div class="flex items-center gap-2">
                        <span
                            class="font-mono text-xs font-semibold text-slate-500"
                            :data-test="`item-npk-${item.id}`"
                        >
                            {{ item.npk_snapshot }}
                        </span>
                        <h4
                            class="text-sm font-bold text-slate-900 dark:text-white"
                            :data-test="`item-name-${item.id}`"
                        >
                            {{ item.employee?.full_name || '-' }}
                        </h4>
                        <span
                            v-if="item.employee?.job_position"
                            class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ item.employee.job_position }}
                        </span>
                    </div>

                    <!-- Task & RCA -->
                    <div
                        v-if="item.task_description || item.rca_category"
                        class="flex flex-wrap items-center gap-2 pt-1 text-xs text-slate-600 dark:text-slate-400"
                    >
                        <span v-if="item.task_description" class="italic">
                            &ldquo;{{ item.task_description }}&rdquo;
                        </span>
                        <span
                            v-if="item.rca_category"
                            class="rounded-full bg-slate-100 px-2 py-0.5 font-mono text-[10px] font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                        >
                            RCA: {{ item.rca_category }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Actions & Decision Segmented Control -->
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-7 px-2 text-xs text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white"
                    :data-test="`btn-audit-trail-${item.id}`"
                    :title="__('Lihat Riwayat Perubahan Item')"
                    @click="emit('openAudit', item.id)"
                >
                    <History class="mr-1 size-3.5 text-[#cc0000]" />
                    <span>{{ __('Riwayat') }}</span>
                </Button>

                <div
                    class="inline-flex rounded-lg border border-slate-200 bg-slate-100 p-0.5 dark:border-slate-700 dark:bg-slate-800"
                    data-test="decision-control"
                >
                    <button
                        type="button"
                        :disabled="disabled"
                        class="flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-semibold transition-all disabled:opacity-50"
                        :class="
                            decision.action === 'APPROVED'
                                ? 'bg-emerald-600 text-white shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white'
                        "
                        :data-test="`btn-decision-approve-${item.id}`"
                        @click="setAction('APPROVED')"
                    >
                        <Check class="size-3.5" />
                        {{ __('Setuju') }}
                    </button>

                    <button
                        type="button"
                        :disabled="disabled"
                        class="flex items-center gap-1 rounded-md px-2.5 py-1 text-xs font-semibold transition-all disabled:opacity-50"
                        :class="
                            decision.action === 'REJECTED'
                                ? 'bg-red-600 text-white shadow-xs'
                                : 'text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white'
                        "
                        :data-test="`btn-decision-reject-${item.id}`"
                        @click="setAction('REJECTED')"
                    >
                        <X class="size-3.5" />
                        {{ __('Tolak') }}
                    </button>

                    <button
                        type="button"
                        :disabled="disabled"
                        class="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium transition-all disabled:opacity-50"
                        :class="
                            decision.action === 'PENDING'
                                ? 'bg-white text-slate-800 shadow-xs dark:bg-slate-700 dark:text-slate-200'
                                : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'
                        "
                        :data-test="`btn-decision-pending-${item.id}`"
                        @click="setAction('PENDING')"
                    >
                        <Clock class="size-3" />
                        {{ __('Pending') }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Hours Breakdown & Financial Figures Grid -->
        <div
            class="dark:bg-slate-850 mt-3 grid grid-cols-2 gap-2 rounded-md bg-slate-50 p-2 text-xs sm:grid-cols-6"
        >
            <div>
                <span class="block text-[10px] text-slate-500 uppercase">{{
                    __('Produksi')
                }}</span>
                <span
                    class="font-mono font-medium text-slate-800 tabular-nums dark:text-slate-200"
                >
                    {{ toNumber(item.hours_production).toFixed(1) }} jam
                </span>
            </div>

            <div>
                <span class="block text-[10px] text-slate-500 uppercase">{{
                    __('TPM')
                }}</span>
                <span
                    class="font-mono font-medium text-slate-800 tabular-nums dark:text-slate-200"
                >
                    {{ toNumber(item.hours_tpm).toFixed(1) }} jam
                </span>
            </div>

            <div>
                <span class="block text-[10px] text-slate-500 uppercase">{{
                    __('CapEx Project')
                }}</span>
                <div class="flex items-center gap-1">
                    <span
                        class="font-mono font-semibold text-sky-700 tabular-nums dark:text-sky-300"
                    >
                        {{ toNumber(item.hours_project).toFixed(1) }} jam
                    </span>
                    <span
                        v-if="
                            toNumber(item.hours_project) > 0 &&
                            item.capex_project
                        "
                        class="py-0.2 inline-flex items-center gap-0.5 rounded border border-sky-300 bg-sky-100 px-1.5 text-[9px] font-bold text-sky-800 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-300"
                        :title="item.capex_project.name"
                        :data-test="`capex-project-pill-${item.id}`"
                    >
                        <FolderKanban class="size-2.5" />
                        {{ item.capex_project.project_code }}
                    </span>
                </div>
            </div>

            <div>
                <span class="block text-[10px] text-slate-500 uppercase">{{
                    __('Lainnya')
                }}</span>
                <span
                    class="font-mono font-medium text-slate-600 tabular-nums dark:text-slate-400"
                >
                    {{ toNumber(item.hours_others).toFixed(1) }} jam
                </span>
            </div>

            <div>
                <span class="block text-[10px] text-slate-500 uppercase">{{
                    __('Total Jam')
                }}</span>
                <span
                    class="font-mono text-sm font-bold text-slate-900 tabular-nums dark:text-white"
                    :data-test="`item-total-hours-${item.id}`"
                >
                    {{ toNumber(item.total_hours).toFixed(1) }} jam
                </span>
            </div>

            <div class="text-right">
                <span class="block text-[10px] text-slate-500 uppercase">{{
                    __('Estimasi Biaya')
                }}</span>
                <span
                    class="font-mono text-sm font-bold text-slate-900 tabular-nums dark:text-white"
                    :data-test="`item-total-cost-${item.id}`"
                >
                    {{ formatRupiah(toNumber(item.total_cost_snapshot)) }}
                </span>
            </div>
        </div>

        <!-- Policy Warning Alert Banner (if applicable) -->
        <div
            v-if="item.policy_warning && item.policy_warning.level !== 'none'"
            class="mt-2.5 flex items-center gap-2 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs text-amber-800 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300"
            :data-test="`policy-warning-pill-${item.id}`"
        >
            <AlertTriangle class="size-4 shrink-0 text-amber-600" />
            <span>{{ item.policy_warning.message }}</span>
        </div>

        <!-- ML Anomaly Alert Banner (if flagged) -->
        <div
            v-if="activeAnomalyReasons.length > 0"
            class="mt-2.5 flex items-start gap-2 rounded-md border border-red-200 bg-red-50/70 px-2.5 py-1.5 text-xs text-[#cc0000] dark:border-red-900/60 dark:bg-red-950/30 dark:text-red-300"
            :data-test="`anomaly-banner-${item.id}`"
        >
            <Bot
                class="mt-0.5 size-4 shrink-0 text-[#cc0000] dark:text-red-400"
            />
            <div>
                <span class="font-bold">{{ __('Peringatan Anomali:') }}</span>
                <span class="ml-1">{{ activeAnomalyReasons.join(', ') }}</span>
            </div>
        </div>

        <!-- Mandatory Rejection Reason Text Area (when action is REJECTED) -->
        <div
            v-if="decision.action === 'REJECTED'"
            class="mt-3 space-y-1 rounded-md border border-red-200 bg-red-50/40 p-2.5 dark:border-red-900/50 dark:bg-red-950/20"
            :data-test="`rejection-reason-container-${item.id}`"
        >
            <label
                :for="`rejection-reason-${item.id}`"
                class="flex items-center justify-between text-xs font-bold text-red-900 dark:text-red-200"
            >
                <span>{{ __('Alasan Penolakan (Wajib Diisi)') }} *</span>
                <span
                    class="font-mono text-[10px]"
                    :class="
                        (decision.rejection_reason?.trim().length || 0) < 5
                            ? 'font-semibold text-red-600 dark:text-red-400'
                            : 'text-slate-500'
                    "
                >
                    {{ decision.rejection_reason?.trim().length || 0 }}/5 min
                </span>
            </label>
            <textarea
                :id="`rejection-reason-${item.id}`"
                rows="2"
                :value="decision.rejection_reason || ''"
                :disabled="disabled"
                :placeholder="
                    __(
                        'Contoh: Target shift terpenuhi, lembur tidak dialokasikan atau salah kategori.',
                    )
                "
                class="w-full rounded-md border border-red-300 bg-white p-2 text-xs text-slate-900 focus:border-red-500 focus:ring-1 focus:ring-red-500 focus:outline-hidden disabled:opacity-50 dark:border-red-800 dark:bg-slate-900 dark:text-white"
                :data-test="`input-rejection-reason-${item.id}`"
                @input="updateRejectionReason"
            />
            <p
                v-if="isRejectionReasonInvalid"
                class="text-[11px] font-medium text-red-600 dark:text-red-400"
                :data-test="`rejection-reason-error-${item.id}`"
            >
                {{
                    __(
                        'Alasan penolakan wajib diisi minimal 5 karakter sebelum menyimpan keputusan.',
                    )
                }}
            </p>
        </div>
    </div>
</template>
