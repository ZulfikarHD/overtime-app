<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { CheckCircle2, Clock, FileText, PlusCircle, Users } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';
import { index as submissionsIndex } from '@/routes/overtime/submissions';

export interface LastSubmissionSummary {
    submission_code: string;
    crew_count: number;
    total_hours: number;
    total_cost: number;
    due_date?: string | null;
    operational_date?: string | null;
    section_name?: string | null;
}

defineProps<{
    submission: LastSubmissionSummary;
}>();

const emit = defineEmits<{
    (e: 'dismiss'): void;
}>();

const { __ } = useTrans();
</script>

<template>
    <div
        class="rounded-xl border border-emerald-200 bg-emerald-50/70 p-4 shadow-sm dark:border-emerald-900/60 dark:bg-emerald-950/20"
        data-test="post-submission-success-card"
    >
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <CheckCircle2
                        class="size-5 text-emerald-600 dark:text-emerald-400"
                    />
                    <h3
                        class="text-sm font-bold text-emerald-950 dark:text-emerald-200"
                    >
                        {{ __('Pengajuan Lembur Berhasil Dikirim!') }}
                    </h3>
                </div>
                <div
                    class="flex flex-wrap items-center gap-2 text-xs text-emerald-800 dark:text-emerald-300"
                >
                    <span>{{ __('Kode Pengajuan:') }}</span>
                    <span
                        class="rounded bg-white px-2 py-0.5 font-mono text-xs font-bold text-emerald-900 shadow-2xs dark:bg-emerald-900/60 dark:text-emerald-100"
                        data-test="submission-code-badge"
                    >
                        {{ submission.submission_code }}
                    </span>
                    <span v-if="submission.section_name"
                        >· {{ submission.section_name }}</span
                    >
                    <span v-if="submission.operational_date"
                        >· {{ submission.operational_date }}</span
                    >
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="emit('dismiss')"
                    class="h-8 border-emerald-300 bg-white text-xs font-semibold text-emerald-900 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-slate-900 dark:text-emerald-200"
                    data-test="btn-new-submission"
                >
                    <PlusCircle class="mr-1.5 size-3.5" />
                    {{ __('Input Lembur Baru') }}
                </Button>
                <Link
                    :href="submissionsIndex()"
                    class="inline-flex h-8 items-center rounded-md bg-emerald-700 px-3 text-xs font-semibold text-white shadow-xs hover:bg-emerald-800 dark:bg-emerald-600 dark:hover:bg-emerald-700"
                    data-test="btn-view-history"
                >
                    <FileText class="mr-1.5 size-3.5" />
                    {{ __('Riwayat Pengajuan') }}
                </Link>
            </div>
        </div>

        <!-- Metrics Counters -->
        <div
            class="mt-3 grid grid-cols-3 gap-2 border-t border-emerald-200/80 pt-3 dark:border-emerald-900/40"
        >
            <div
                class="flex items-center gap-2 rounded-lg bg-white/80 p-2 dark:bg-slate-900/60"
            >
                <Users class="size-4 text-emerald-600 dark:text-emerald-400" />
                <div>
                    <div class="text-[10px] text-slate-500">
                        {{ __('Total Tenaga Kerja') }}
                    </div>
                    <div
                        class="font-mono text-xs font-bold text-slate-900 tabular-nums dark:text-white"
                        data-test="metric-crew-count"
                    >
                        {{ submission.crew_count }} {{ __('Orang') }}
                    </div>
                </div>
            </div>
            <div
                class="flex items-center gap-2 rounded-lg bg-white/80 p-2 dark:bg-slate-900/60"
            >
                <Clock class="size-4 text-emerald-600 dark:text-emerald-400" />
                <div>
                    <div class="text-[10px] text-slate-500">
                        {{ __('Total Jam Lembur') }}
                    </div>
                    <div
                        class="font-mono text-xs font-bold text-slate-900 tabular-nums dark:text-white"
                        data-test="metric-total-hours"
                    >
                        {{ submission.total_hours.toFixed(1) }} {{ __('Jam') }}
                    </div>
                </div>
            </div>
            <div
                class="flex items-center gap-2 rounded-lg bg-white/80 p-2 dark:bg-slate-900/60"
            >
                <span
                    class="font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400"
                    >Rp</span
                >
                <div>
                    <div class="text-[10px] text-slate-500">
                        {{ __('Estimasi Biaya (Snapshot)') }}
                    </div>
                    <div
                        class="font-mono text-xs font-bold text-slate-900 tabular-nums dark:text-white"
                        data-test="metric-total-cost"
                    >
                        {{ formatRupiah(submission.total_cost) }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Non-Blocking SPKL Banner -->
        <div
            class="mt-2.5 flex items-center justify-between rounded-md border border-amber-200 bg-amber-50/80 px-3 py-1.5 text-xs text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-300"
        >
            <div class="flex items-center gap-2">
                <span class="text-sm">📎</span>
                <span class="font-semibold">
                    {{ __('SPKL: Belum Dilampirkan (Non-blocking BR-05)') }}
                </span>
                <span
                    v-if="submission.due_date"
                    class="text-amber-700 dark:text-amber-400"
                >
                    ·
                    {{
                        __('Batas waktu: :date', { date: submission.due_date })
                    }}
                </span>
            </div>
            <span
                class="hidden text-[11px] text-amber-700 sm:inline dark:text-amber-400"
            >
                {{ __('Dokumen fisik dapat dilampirkan setelah shift.') }}
            </span>
        </div>
    </div>
</template>
