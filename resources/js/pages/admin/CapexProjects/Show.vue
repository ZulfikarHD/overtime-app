<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowLeft,
    Building2,
    Calendar,
    Clock,
    DollarSign,
    Edit2,
    Flame,
    FolderKanban,
    Info,
    Layers,
    Lock,
    RefreshCw,
    TrendingUp,
    Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import CapexProjectDrawer, {
    type CapexProjectRecord,
} from '@/components/admin/CapexProjectDrawer.vue';
import ProjectStatusTransitionModal from '@/components/admin/ProjectStatusTransitionModal.vue';
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

export interface ProjectDetailMetrics {
    allocated_hours: number;
    consumed_hours: number;
    remaining_hours: number;
    allocated_budget_idr: number;
    consumed_cost_idr: number;
    remaining_budget_idr: number;
    burn_index_pct: number;
    physical_progress_pct: number;
    milestone_burn_ratio: number;
    days_remaining: number;
    is_at_risk: boolean;
    is_overdue: boolean;
}

const props = defineProps<{
    project: CapexProjectRecord & {
        department?: {
            id: number;
            code: string;
            name: string;
        };
    };
    metrics: ProjectDetailMetrics;
}>();

const { __ } = useTrans();

const isDrawerOpen = ref(false);
const isStatusModalOpen = ref(false);

function handleRefresh() {
    router.reload();
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

function getBurnIndexColor(index: number) {
    if (index > 115) return 'text-[#cc0000]';
    if (index > 100) return 'text-amber-600';
    if (index >= 85) return 'text-sky-600';
    return 'text-emerald-600';
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
                        @click="isStatusModalOpen = true"
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

        <!-- 4 Macro KPI Cockpit Cards -->
        <div
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4"
            data-test="detail-kpi-cockpit"
        >
            <!-- Card 1: Jam Tenaga Kerja -->
            <Card class="border-border bg-card">
                <CardHeader class="pb-2">
                    <CardDescription
                        class="text-muted-foreground flex items-center justify-between text-xs font-semibold tracking-wider uppercase"
                    >
                        <span>{{ __('Jam Tenaga Kerja') }}</span>
                        <Clock class="size-4 text-sky-600" />
                    </CardDescription>
                    <CardTitle
                        class="text-foreground font-mono text-xl font-bold tabular-nums"
                    >
                        {{
                            metrics.consumed_hours.toLocaleString('id-ID', {
                                minimumFractionDigits: 1,
                                maximumFractionDigits: 1,
                            })
                        }}
                        <span class="text-muted-foreground text-xs font-normal"
                            >/
                            {{
                                metrics.allocated_hours.toLocaleString(
                                    'id-ID',
                                    {
                                        minimumFractionDigits: 1,
                                        maximumFractionDigits: 1,
                                    },
                                )
                            }}
                            jam</span
                        >
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-2 text-xs">
                    <div
                        class="bg-muted h-2 w-full overflow-hidden rounded-full"
                    >
                        <div
                            class="h-2 rounded-full transition-all"
                            :class="
                                metrics.burn_index_pct > 100
                                    ? 'bg-[#cc0000]'
                                    : 'bg-sky-600'
                            "
                            :style="{
                                width: `${Math.min(100, metrics.burn_index_pct)}%`,
                            }"
                        ></div>
                    </div>
                    <div
                        class="text-muted-foreground flex items-center justify-between font-mono text-[11px]"
                    >
                        <span
                            >{{ __('Sisa alokasi:') }}
                            {{ metrics.remaining_hours.toFixed(1) }} jam</span
                        >
                        <span class="font-semibold"
                            >{{ metrics.burn_index_pct.toFixed(1) }}%</span
                        >
                    </div>
                </CardContent>
            </Card>

            <!-- Card 2: Biaya Tenaga Kerja Terkapitalisasi -->
            <Card class="border-border bg-card">
                <CardHeader class="pb-2">
                    <CardDescription
                        class="text-muted-foreground flex items-center justify-between text-xs font-semibold tracking-wider uppercase"
                    >
                        <span>{{ __('Biaya Terkapitalisasi') }}</span>
                        <DollarSign class="size-4 text-emerald-600" />
                    </CardDescription>
                    <CardTitle
                        class="text-foreground font-mono text-lg font-bold tabular-nums"
                    >
                        {{
                            formatRupiah(metrics.consumed_cost_idr, {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0,
                            })
                        }}
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-1 text-xs">
                    <p class="text-muted-foreground text-[11px]">
                        {{ __('Anggaran:') }}
                        {{
                            formatRupiah(metrics.allocated_budget_idr, {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0,
                            })
                        }}
                    </p>
                    <p
                        class="font-mono text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                    >
                        {{ __('Sisa Anggaran:') }}
                        {{
                            formatRupiah(metrics.remaining_budget_idr, {
                                minimumFractionDigits: 0,
                                maximumFractionDigits: 0,
                            })
                        }}
                    </p>
                </CardContent>
            </Card>

            <!-- Card 3: Indeks Burn CapEx -->
            <Card class="border-border bg-card">
                <CardHeader class="pb-2">
                    <CardDescription
                        class="text-muted-foreground flex items-center justify-between text-xs font-semibold tracking-wider uppercase"
                    >
                        <span>{{ __('Indeks Burn CapEx') }}</span>
                        <Flame
                            class="size-4"
                            :class="getBurnIndexColor(metrics.burn_index_pct)"
                        />
                    </CardDescription>
                    <CardTitle
                        class="font-mono text-2xl font-bold tabular-nums"
                        :class="getBurnIndexColor(metrics.burn_index_pct)"
                    >
                        {{ metrics.burn_index_pct.toFixed(1) }}%
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-muted-foreground text-xs">
                    <span
                        v-if="metrics.burn_index_pct > 115"
                        class="font-semibold text-[#cc0000]"
                    >
                        🚨 {{ __('Overrun Defisit: Melebihi 115% alokasi') }}
                    </span>
                    <span
                        v-else-if="metrics.burn_index_pct > 100"
                        class="font-semibold text-amber-600"
                    >
                        ⚠️ {{ __('Peringatan: Melebihi alokasi jam kerja') }}
                    </span>
                    <span
                        v-else-if="metrics.burn_index_pct >= 85"
                        class="font-medium text-sky-600"
                    >
                        {{ __('Mendekati batas alokasi (85 - 100%)') }}
                    </span>
                    <span v-else class="font-medium text-emerald-600">
                        {{ __('Konsumsi dalam batas normal (< 85%)') }}
                    </span>
                </CardContent>
            </Card>

            <!-- Card 4: Kemajuan Fisik & Rasio Milestone -->
            <Card class="border-border bg-card">
                <CardHeader class="pb-2">
                    <CardDescription
                        class="text-muted-foreground flex items-center justify-between text-xs font-semibold tracking-wider uppercase"
                    >
                        <span>{{ __('Kemajuan Fisik & Milestone') }}</span>
                        <TrendingUp class="size-4 text-sky-600" />
                    </CardDescription>
                    <CardTitle
                        class="text-foreground font-mono text-xl font-bold tabular-nums"
                    >
                        {{ metrics.physical_progress_pct.toFixed(1) }}%
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-1 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-muted-foreground text-[11px]">{{
                            __('Rasio Burn Milestone:')
                        }}</span>
                        <span
                            class="font-mono text-xs font-bold"
                            :class="
                                metrics.milestone_burn_ratio > 1.2
                                    ? 'text-amber-600'
                                    : 'text-foreground'
                            "
                        >
                            {{
                                metrics.milestone_burn_ratio > 0
                                    ? metrics.milestone_burn_ratio.toFixed(2)
                                    : 'N/A'
                            }}
                        </span>
                    </div>
                    <p
                        v-if="metrics.milestone_burn_ratio > 1.2"
                        class="text-[10px] leading-tight font-semibold text-amber-600"
                    >
                        ⚠️
                        {{
                            __(
                                'Pembakaran jam lebih cepat dibanding kemajuan fisik!',
                            )
                        }}
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Zero Hours Empty State Notice or Informational Block -->
        <Card
            v-if="metrics.consumed_hours === 0"
            class="border-border bg-card p-6 text-center"
            data-test="zero-hours-state"
        >
            <div class="mx-auto max-w-md space-y-2">
                <Clock class="text-muted-foreground/60 mx-auto size-8" />
                <h3 class="text-foreground text-sm font-bold">
                    {{ __('Belum Ada Jam Lembur Tercatat') }}
                </h3>
                <p class="text-muted-foreground text-xs leading-relaxed">
                    {{
                        __(
                            'Proyek dalam tahap alokasi anggaran. Jam lembur yang disetujui untuk proyek ini akan otomatis terakumulasi di cockpit ini dan laporan audit PSAK 16.',
                        )
                    }}
                </p>
            </div>
        </Card>

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
            @success="handleRefresh"
        />
    </div>
</template>
