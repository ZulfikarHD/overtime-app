<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Building2,
    Calendar,
    CheckCircle2,
    Clock,
    Edit2,
    FolderKanban,
    PartyPopper,
    RefreshCw,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import CapexProjectDrawer, {
    type CapexProjectRecord,
} from '@/components/admin/CapexProjectDrawer.vue';
import ProjectStatusTransitionModal from '@/components/admin/ProjectStatusTransitionModal.vue';
import CapexBurnIndexPanel, {
    type ProjectBurnMetrics,
} from '@/components/capex/CapexBurnIndexPanel.vue';
import CapexLaborTimelineChart, {
    type TimelineWeekItem,
} from '@/components/capex/CapexLaborTimelineChart.vue';
import CapexTeamContributionTable, {
    type ContributorItem,
} from '@/components/capex/CapexTeamContributionTable.vue';
import InlineProgressEditor from '@/components/capex/InlineProgressEditor.vue';
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
import { formatDateIndo, formatRupiah } from '@/lib/formatters';
import capexProjectsRoute from '@/routes/admin/capex-projects';

export interface ExtendedProjectMetrics extends ProjectBurnMetrics {
    top_contributors?: ContributorItem[];
    weekly_timeline?: TimelineWeekItem[];
}

const props = defineProps<{
    project: CapexProjectRecord & {
        department?: {
            id: number;
            code: string;
            name: string;
        };
    };
    metrics: ExtendedProjectMetrics;
}>();

const { __ } = useTrans();

const isDrawerOpen = ref(false);
const isStatusModalOpen = ref(false);
const initialTargetStatus = ref<
    'PLANNING' | 'ACTIVE' | 'ON_HOLD' | 'COMPLETED' | 'CLOSED' | null
>(null);
const isPromptDismissed = ref(false);

const showCompletionPrompt = computed(() => {
    return (
        !isPromptDismissed.value &&
        Number(props.metrics.physical_progress_pct) >= 100 &&
        !['COMPLETED', 'CLOSED'].includes(props.project.status)
    );
});

function handleRefresh() {
    router.reload();
}

function handleProgressUpdated() {
    // Page props are automatically refreshed by Inertia upon redirect()->back()
}

function openStatusModalWithTarget(
    targetStatus?:
        | 'PLANNING'
        | 'ACTIVE'
        | 'ON_HOLD'
        | 'COMPLETED'
        | 'CLOSED'
        | null,
) {
    initialTargetStatus.value = targetStatus ?? null;
    isStatusModalOpen.value = true;
}

function getStatusBadgeClass(status: string) {
    switch (status) {
        case 'PLANNING':
            return 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300';
        case 'ACTIVE':
            return 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300';
        case 'ON_HOLD':
            return 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300';
        case 'COMPLETED':
            return 'bg-sky-50 text-sky-700 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300';
        case 'CLOSED':
            return 'bg-zinc-100 text-zinc-600 border-zinc-300 dark:bg-zinc-800 dark:text-zinc-400';
        default:
            return 'bg-slate-100 text-slate-700';
    }
}
</script>

<template>
    <Head :title="`${project.project_code} — ${project.name}`" />

    <div class="space-y-6" data-test="capex-project-detail-page">
        <!-- Persistent Project Header & Breadcrumb -->
        <div class="border-border space-y-3 border-b pb-5">
            <div class="flex items-center gap-2">
                <Link
                    :href="
                        capexProjectsRoute.index.url({
                            query: { tab: 'portfolio' },
                        })
                    "
                    class="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-xs font-medium transition-colors"
                    data-test="link-back-to-hub"
                >
                    <ArrowLeft class="size-3.5" />
                    {{ __('Kembali ke Hub Proyek CapEx') }}
                </Link>
            </div>

            <div
                class="flex flex-col justify-between gap-4 lg:flex-row lg:items-center"
            >
                <div class="space-y-1.5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="rounded-md border border-red-200 bg-red-50 px-2.5 py-0.5 font-mono text-sm font-bold tracking-tight text-[#cc0000] dark:border-red-900 dark:bg-red-950/40"
                            data-test="badge-project-code"
                        >
                            {{ project.project_code }}
                        </span>
                        <span
                            v-if="project.asset_code"
                            class="text-muted-foreground bg-muted border-border rounded-md border px-2 py-0.5 font-mono text-xs"
                            data-test="badge-asset-code"
                        >
                            {{ __('Aset:') }} {{ project.asset_code }}
                        </span>
                        <span
                            class="text-muted-foreground inline-flex items-center gap-1 text-xs font-medium"
                        >
                            <Building2 class="size-3" />
                            {{ project.department?.code }} —
                            {{ project.department?.name }}
                        </span>
                        <Badge
                            variant="outline"
                            :class="getStatusBadgeClass(project.status)"
                            class="text-xs font-semibold uppercase"
                            data-test="badge-status"
                        >
                            {{ project.status }}
                        </Badge>
                    </div>

                    <h1
                        class="text-foreground text-2xl font-bold tracking-tight"
                        data-test="project-detail-name"
                    >
                        {{ project.name }}
                    </h1>

                    <div
                        class="text-muted-foreground flex flex-wrap items-center gap-4 text-xs"
                    >
                        <span class="flex items-center gap-1">
                            <Calendar class="size-3.5" />
                            {{ __('Mulai:') }}
                            {{ formatDateIndo(project.start_date) }}
                        </span>
                        <span class="flex items-center gap-1">
                            <Calendar class="size-3.5" />
                            {{ __('Target Selesai:') }}
                            {{ formatDateIndo(project.target_end_date) }}
                        </span>
                        <span
                            v-if="metrics.is_overdue"
                            class="font-bold text-[#cc0000]"
                        >
                            ⚠️
                            {{
                                __('Lewat :days hari dari target selesai', {
                                    days: Math.abs(metrics.days_remaining),
                                })
                            }}
                        </span>
                        <span
                            v-else-if="
                                !['COMPLETED', 'CLOSED'].includes(
                                    project.status,
                                )
                            "
                            class="font-medium text-sky-700 dark:text-sky-300"
                        >
                            {{
                                __('Sisa :days hari pengerjaan', {
                                    days: metrics.days_remaining,
                                })
                            }}
                        </span>
                    </div>
                </div>

                <!-- Quick Action Buttons -->
                <div class="flex items-center gap-2">
                    <Button
                        variant="outline"
                        class="gap-1.5 text-xs font-semibold"
                        @click="openStatusModalWithTarget(null)"
                        data-test="btn-open-status-modal"
                    >
                        <RefreshCw class="size-3.5 text-sky-600" />
                        {{ __('Ubah Status Proyek') }}
                    </Button>
                    <Button
                        class="gap-1.5 bg-[#cc0000] text-xs font-semibold text-white hover:bg-[#b30000]"
                        @click="isDrawerOpen = true"
                        data-test="btn-open-edit-drawer"
                    >
                        <Edit2 class="size-3.5" />
                        {{ __('Edit Data Master') }}
                    </Button>
                </div>
            </div>
        </div>

        <!-- Completion Milestone Prompt Banner (100% Progress) -->
        <div
            v-if="showCompletionPrompt"
            class="relative flex flex-col justify-between gap-4 rounded-xl border border-sky-300 bg-sky-50 p-4 shadow-xs sm:flex-row sm:items-center dark:border-sky-800 dark:bg-sky-950/40"
            data-test="completion-milestone-banner"
        >
            <div class="flex items-start gap-3">
                <div
                    class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg bg-sky-600 text-white shadow-xs"
                >
                    <PartyPopper class="size-4.5" />
                </div>
                <div class="space-y-0.5">
                    <h3 class="text-foreground text-sm font-bold">
                        🎉 {{ __('Kemajuan Fisik Mencapai 100%') }}
                    </h3>
                    <p class="text-muted-foreground text-xs leading-relaxed">
                        {{
                            __(
                                'Pekerjaan fisik proyek telah selesai 100%. Apakah Anda ingin memperbarui status proyek menjadi COMPLETED?',
                            )
                        }}
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 sm:shrink-0">
                <Button
                    size="sm"
                    class="h-8 gap-1.5 bg-sky-700 text-xs font-semibold text-white hover:bg-sky-800"
                    @click="openStatusModalWithTarget('COMPLETED')"
                    data-test="btn-transition-completed"
                >
                    <CheckCircle2 class="size-3.5" />
                    {{ __('Ubah Status ke COMPLETED Sekarang') }}
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    class="text-muted-foreground hover:text-foreground h-8 text-xs"
                    @click="isPromptDismissed = true"
                    data-test="btn-dismiss-milestone-banner"
                >
                    {{ __('Nanti Saja') }}
                </Button>
            </div>
        </div>

        <!-- 4 Macro KPI Cockpit Cards -->
        <div data-test="detail-kpi-cockpit">
            <CapexBurnIndexPanel :metrics="metrics" />
        </div>

        <!-- Zero Hours Empty State Notice -->
        <Card
            v-if="metrics.consumed_hours === 0"
            class="border-sky-200 bg-sky-50/40 p-6 text-center dark:border-sky-900/50 dark:bg-sky-950/20"
            data-test="zero-hours-state"
        >
            <div class="mx-auto max-w-md space-y-2">
                <Clock class="mx-auto size-8 text-sky-600 dark:text-sky-400" />
                <h3 class="text-foreground text-sm font-bold">
                    {{ __('Belum Ada Jam Lembur Tercatat') }}
                </h3>
                <p class="text-muted-foreground text-xs leading-relaxed">
                    {{
                        __(
                            'Belum ada jam lembur tercatat — Proyek dalam tahap alokasi anggaran.',
                        )
                    }}
                </p>
            </div>
        </Card>

        <!-- In-Place Physical Progress Editor -->
        <InlineProgressEditor
            :project-id="project.id"
            :initial-progress="Number(metrics.physical_progress_pct)"
            @updated="handleProgressUpdated"
        />

        <!-- 2-Column Split: Labor Timeline Burndown Curve & Team Contribution Roster -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Weekly Timeline Burndown Chart -->
            <CapexLaborTimelineChart
                :timeline="metrics.weekly_timeline || []"
                :allocated-hours="Number(metrics.allocated_hours)"
                :burn-index-pct="Number(metrics.burn_index_pct)"
            />

            <!-- Team Contribution Table -->
            <CapexTeamContributionTable
                :contributors="metrics.top_contributors || []"
                :total-consumed-hours="Number(metrics.consumed_hours)"
            />
        </div>

        <!-- Master Data Details Card -->
        <Card class="border-border bg-card">
            <CardHeader class="border-border border-b pb-3">
                <CardTitle
                    class="text-foreground flex items-center gap-2 text-sm font-bold"
                >
                    <FolderKanban class="size-4 text-[#cc0000]" />
                    {{ __('Parameter Master Data & Kepatuhan Audit') }}
                </CardTitle>
            </CardHeader>
            <CardContent class="p-6">
                <dl
                    class="grid grid-cols-1 gap-6 text-xs sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div>
                        <dt class="text-muted-foreground font-semibold">
                            {{ __('Kode Proyek (Permanen)') }}
                        </dt>
                        <dd class="text-foreground mt-1 font-mono font-bold">
                            {{ project.project_code }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground font-semibold">
                            {{ __('Kode Aset Tetap') }}
                        </dt>
                        <dd class="text-foreground mt-1 font-mono">
                            {{ project.asset_code || '—' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground font-semibold">
                            {{ __('Departemen Pemilik') }}
                        </dt>
                        <dd class="text-foreground mt-1 font-medium">
                            {{ project.department?.code }} —
                            {{ project.department?.name }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground font-semibold">
                            {{ __('Alokasi Jam Lembur') }}
                        </dt>
                        <dd class="text-foreground mt-1 font-mono tabular-nums">
                            {{
                                Number(
                                    project.allocated_labor_hours,
                                ).toLocaleString('id-ID', {
                                    minimumFractionDigits: 1,
                                })
                            }}
                            jam
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground font-semibold">
                            {{ __('Alokasi Anggaran Rupiah') }}
                        </dt>
                        <dd class="text-foreground mt-1 font-mono tabular-nums">
                            {{
                                formatRupiah(project.allocated_labor_budget_idr)
                            }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground font-semibold">
                            {{ __('Jadwal Proyek') }}
                        </dt>
                        <dd class="text-foreground mt-1">
                            {{ formatDateIndo(project.start_date) }} —
                            {{ formatDateIndo(project.target_end_date) }}
                        </dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <!-- Slide-in Drawer: CapexProjectDrawer -->
        <CapexProjectDrawer
            v-model:open="isDrawerOpen"
            :project="project"
            :departments="project.department ? [project.department] : []"
            @success="handleRefresh"
        />

        <!-- Status Transition Modal: ProjectStatusTransitionModal -->
        <ProjectStatusTransitionModal
            v-model:open="isStatusModalOpen"
            :project="project"
            :initial-target-status="initialTargetStatus"
            @success="handleRefresh"
        />
    </div>
</template>
