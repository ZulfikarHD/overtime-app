<script setup lang="ts">
import {
    Activity,
    AlertTriangle,
    BookmarkPlus,
    CheckCircle2,
    DollarSign,
    FolderKanban,
    Gauge,
    Loader2,
    Play,
    ShieldAlert,
    Sliders,
    TrendingDown,
    TrendingUp,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
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
import {
    calculate as calculateRoute,
    save as saveRoute,
} from '@/routes/analytics/scenario';

export interface ScenarioBuilderResult {
    department_id: number | null;
    department_name: string;
    overtime_change_pct: number;
    baseline_hours: number;
    projected_hours: number;
    baseline_cost: number;
    projected_cost: number;
    formatted_projected_cost: string;
    cost_impact: number;
    formatted_cost_impact: string;
    production_volume_impact_pct: number;
    budget_allocation: number;
    formatted_budget_allocation: string;
    projected_burn_index: number;
    burn_zone: 'safe' | 'on_track' | 'warning' | 'danger';
    safety_risk_score: number;
    safety_risk_zone: 'low' | 'medium' | 'high';
}

export interface DepartmentOption {
    id: number;
    code: string;
    name: string;
    default_hourly_rate: number;
}

interface Props {
    departments?: DepartmentOption[];
    initialResult?: ScenarioBuilderResult | null;
    selectedDepartmentId?: string | number | null;
    savedCount?: number;
    isManager?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    departments: () => [],
    initialResult: null,
    selectedDepartmentId: 'all',
    savedCount: 0,
    isManager: false,
});

const emit = defineEmits<{
    (e: 'updated', result: ScenarioBuilderResult): void;
    (e: 'open-saved-drawer'): void;
    (e: 'scenario-saved', savedScenarios: Array<Record<string, unknown>>): void;
}>();

const { __ } = useTrans();

// Builder Controls State
const overtimeSlider = ref<number>(
    props.initialResult?.overtime_change_pct ?? 0,
);
const budgetAllocation = ref<number>(
    props.initialResult?.budget_allocation ?? 150000000,
);
const targetDeptId = ref<string>(
    props.selectedDepartmentId !== null &&
        props.selectedDepartmentId !== undefined
        ? String(props.selectedDepartmentId)
        : 'all',
);

const isCalculating = ref(false);
const isSaving = ref(false);
const saveDialogOpen = ref(false);
const scenarioName = ref('');
const errorMsg = ref<string | null>(null);
const saveSuccessMsg = ref<string | null>(null);

// Active result state
const currentResult = ref<ScenarioBuilderResult>(
    props.initialResult ?? {
        department_id: null,
        department_name: 'Semua Departemen (Lintas Pabrik)',
        overtime_change_pct: 0,
        baseline_hours: 450,
        projected_hours: 450,
        baseline_cost: 22500000,
        projected_cost: 22500000,
        formatted_projected_cost: 'Rp 22,5 Jt',
        cost_impact: 0,
        formatted_cost_impact: 'Rp 0',
        production_volume_impact_pct: 0,
        budget_allocation: 150000000,
        formatted_budget_allocation: 'Rp 150,0 Jt',
        projected_burn_index: 90.0,
        burn_zone: 'on_track',
        safety_risk_score: 12.0,
        safety_risk_zone: 'low',
    },
);

// Client-side quick reactive update when slider changes
watch(overtimeSlider, (newPct) => {
    recalculateClientPreview(newPct);
});

// Watch initialResult from parent
watch(
    () => props.initialResult,
    (newVal) => {
        if (newVal) {
            currentResult.value = newVal;
            overtimeSlider.value = newVal.overtime_change_pct;
            budgetAllocation.value = newVal.budget_allocation;
        }
    },
);

function recalculateClientPreview(changePct: number) {
    const baseHours = currentResult.value.baseline_hours || 450;
    const baseCost = currentResult.value.baseline_cost || 22500000;
    const avgRate = baseHours > 0 ? baseCost / baseHours : 50000;

    const projectedHours = Math.max(
        0,
        Math.round(baseHours * (1 + changePct / 100) * 10) / 10,
    );
    const projectedCost = Math.round(projectedHours * avgRate);
    const costImpact = Math.round(projectedCost - baseCost);

    const budget =
        budgetAllocation.value > 0 ? budgetAllocation.value : baseCost;
    const burnIndex =
        budget > 0
            ? Math.round((projectedCost / budget) * 1000) / 10
            : 100 + changePct;

    let burnZone: 'safe' | 'on_track' | 'warning' | 'danger' = 'safe';
    if (burnIndex > 115) {
        burnZone = 'danger';
    } else if (burnIndex > 100) {
        burnZone = 'warning';
    } else if (burnIndex >= 85) {
        burnZone = 'on_track';
    }

    const volumeImpact = Math.round(changePct * 0.78 * 10) / 10;

    // Approximated safety risk score
    let risk = currentResult.value.safety_risk_score;
    if (changePct <= 0) {
        risk = Math.max(2, Math.round((12 + changePct * 0.15) * 10) / 10);
    } else {
        risk = Math.min(95, Math.round((12 + changePct * 0.65) * 10) / 10);
    }

    let safetyZone: 'low' | 'medium' | 'high' = 'low';
    if (risk > 30) {
        safetyZone = 'high';
    } else if (risk >= 15) {
        safetyZone = 'medium';
    }

    currentResult.value = {
        ...currentResult.value,
        overtime_change_pct: changePct,
        projected_hours: projectedHours,
        projected_cost: projectedCost,
        formatted_projected_cost: formatRupiahCompact(projectedCost),
        cost_impact: costImpact,
        formatted_cost_impact:
            (costImpact > 0 ? '+ ' : costImpact < 0 ? '- ' : '') +
            formatRupiahCompact(Math.abs(costImpact)),
        production_volume_impact_pct: volumeImpact,
        projected_burn_index: burnIndex,
        burn_zone: burnZone,
        safety_risk_score: risk,
        safety_risk_zone: safetyZone,
    };

    emit('updated', currentResult.value);
}

function formatRupiahCompact(amount: number): string {
    if (amount <= 0) return 'Rp 0';
    if (amount >= 1_000_000_000) {
        return (
            'Rp ' + (amount / 1_000_000_000).toFixed(1).replace('.', ',') + ' M'
        );
    }
    if (amount >= 1_000_000) {
        return (
            'Rp ' + (amount / 1_000_000).toFixed(1).replace('.', ',') + ' Jt'
        );
    }
    if (amount >= 1_000) {
        return 'Rp ' + (amount / 1_000).toFixed(1).replace('.', ',') + ' Rb';
    }
    return 'Rp ' + Math.round(amount).toLocaleString('id-ID');
}

async function runServerCalculation() {
    isCalculating.value = true;
    errorMsg.value = null;

    try {
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const res = await fetch(calculateRoute.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                type: 'builder',
                overtime_change_pct: Number(overtimeSlider.value),
                budget_allocation: Number(budgetAllocation.value),
                department_id: targetDeptId.value,
            }),
        });

        if (!res.ok) {
            const errJson = await res.json().catch(() => null);
            throw new Error(
                errJson?.message ??
                    `HTTP error ${res.status}: Gagal memproses simulasi skenario.`,
            );
        }

        const json = await res.json();
        if (json.status === 'success' && json.data) {
            currentResult.value = json.data as ScenarioBuilderResult;
            emit('updated', currentResult.value);
        }
    } catch (err: unknown) {
        const error = err as Error;
        errorMsg.value =
            error?.message ??
            __('Gagal menghubungkan ke engine kalkulasi skenario.');
    } finally {
        isCalculating.value = false;
    }
}

async function saveCurrentScenario() {
    if (!scenarioName.value.trim()) {
        scenarioName.value = `Skenario ${overtimeSlider.value > 0 ? '+' : ''}${overtimeSlider.value}% Lembur`;
    }

    isSaving.value = true;
    errorMsg.value = null;

    try {
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const res = await fetch(saveRoute.url(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                name: scenarioName.value,
                department_id: targetDeptId.value,
                overtime_change_pct: Number(
                    currentResult.value.overtime_change_pct,
                ),
                budget_allocation: Number(
                    currentResult.value.budget_allocation,
                ),
                projected_hours: Number(currentResult.value.projected_hours),
                projected_cost: Number(currentResult.value.projected_cost),
                projected_burn_index: Number(
                    currentResult.value.projected_burn_index,
                ),
                burn_zone: currentResult.value.burn_zone,
                safety_risk_score: Number(
                    currentResult.value.safety_risk_score,
                ),
                production_volume_impact_pct: Number(
                    currentResult.value.production_volume_impact_pct,
                ),
            }),
        });

        if (!res.ok) {
            const errJson = await res.json().catch(() => null);
            throw new Error(
                errJson?.message ?? __('Gagal menyimpan skenario.'),
            );
        }

        const json = await res.json();
        if (json.status === 'success') {
            saveSuccessMsg.value = __('Skenario berhasil disimpan.');
            saveDialogOpen.value = false;
            scenarioName.value = '';
            if (json.saved_scenarios) {
                emit('scenario-saved', json.saved_scenarios);
            }
            setTimeout(() => {
                saveSuccessMsg.value = null;
            }, 3000);
        }
    } catch (err: unknown) {
        const error = err as Error;
        errorMsg.value =
            error?.message ?? __('Terjadi kesalahan saat menyimpan skenario.');
    } finally {
        isSaving.value = false;
    }
}

function setQuickSlider(val: number) {
    overtimeSlider.value = val;
}
</script>

<template>
    <div class="space-y-5" data-test="scenario-builder-panel">
        <Card
            class="border-slate-200 bg-white shadow-2xs dark:border-slate-800 dark:bg-slate-900"
        >
            <CardHeader class="pb-4">
                <div class="flex items-center justify-between gap-2">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-white"
                    >
                        <Sliders class="size-4 text-sky-600" />
                        <span>{{ __('Simulator Skenario Beban Kerja') }}</span>
                    </CardTitle>
                    <div class="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="sm"
                            class="h-7 cursor-pointer border-slate-200 px-2.5 text-xs font-semibold text-slate-700 hover:border-slate-300 dark:border-slate-700 dark:text-slate-300"
                            data-test="btn-open-saved-drawer"
                            @click="emit('open-saved-drawer')"
                        >
                            <BookmarkPlus
                                class="mr-1.5 size-3.5 text-sky-600"
                            />
                            <span>{{ __('Kelola Skenario') }}</span>
                            <Badge
                                v-if="savedCount > 0"
                                variant="secondary"
                                class="ml-1.5 h-4 px-1 font-mono text-[10px] tabular-nums"
                            >
                                {{ savedCount }}
                            </Badge>
                        </Button>
                    </div>
                </div>
                <CardDescription class="text-xs text-slate-500">
                    {{
                        __(
                            'Uji sensitivitas penyesuaian kuota lembur (-50% s/d +50%) terhadap biaya, batas plafon anggaran, dan skor risiko keselamatan.',
                        )
                    }}
                </CardDescription>
            </CardHeader>

            <CardContent class="space-y-5">
                <!-- Slider & Controls -->
                <div
                    class="dark:bg-slate-850/50 space-y-4 rounded-xl border border-slate-100 bg-slate-50/50 p-4 dark:border-slate-800"
                >
                    <!-- Overtime Adjustment Slider -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label
                                class="text-xs font-bold text-slate-800 dark:text-slate-200"
                            >
                                {{ __('Penyesuaian Alokasi Lembur (%):') }}
                            </label>
                            <!-- Dynamic Percentage Badge -->
                            <div
                                class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 font-mono text-sm font-bold tabular-nums"
                                :class="
                                    overtimeSlider > 25
                                        ? 'bg-red-100 text-[#cc0000] dark:bg-red-950/60 dark:text-red-400'
                                        : overtimeSlider > 0
                                          ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300'
                                          : overtimeSlider < 0
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                            : 'bg-sky-100 text-sky-800 dark:bg-sky-950/60 dark:text-sky-300'
                                "
                                data-test="label-overtime-slider-value"
                            >
                                <span
                                    >{{ overtimeSlider > 0 ? '+' : ''
                                    }}{{ overtimeSlider }}%</span
                                >
                            </div>
                        </div>

                        <!-- Native HTML5 Range Slider with Smooth Touch -->
                        <div class="relative py-1">
                            <input
                                v-model.number="overtimeSlider"
                                type="range"
                                min="-50"
                                max="50"
                                step="1"
                                class="h-2 w-full cursor-pointer appearance-none rounded-lg bg-slate-200 accent-[#cc0000] dark:bg-slate-700"
                                data-test="slider-overtime-change"
                            />
                            <div
                                class="flex justify-between pt-1 font-mono text-[10px] text-slate-400 tabular-nums"
                            >
                                <span>-50%</span>
                                <span>-25%</span>
                                <span
                                    class="font-bold text-slate-600 dark:text-slate-300"
                                    >0% (Baseline)</span
                                >
                                <span>+25%</span>
                                <span>+50%</span>
                            </div>
                        </div>

                        <!-- Quick Preset Chips -->
                        <div class="flex flex-wrap items-center gap-1.5 pt-1">
                            <span class="mr-1 text-[11px] text-slate-400">{{
                                __('Preset cepat:')
                            }}</span>
                            <button
                                v-for="preset in [-50, -25, 0, 25, 50]"
                                :key="preset"
                                type="button"
                                :data-test="
                                    'btn-preset-' +
                                    (preset < 0
                                        ? 'minus-' + Math.abs(preset)
                                        : preset === 0
                                          ? 'zero'
                                          : 'plus-' + preset)
                                "
                                class="cursor-pointer rounded-md border border-slate-200 bg-white px-2 py-0.5 font-mono text-[11px] font-semibold text-slate-700 tabular-nums shadow-2xs hover:border-[#cc0000] hover:text-[#cc0000] active:scale-95 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                :class="{
                                    'border-[#cc0000] bg-red-50 text-[#cc0000] dark:bg-red-950/40 dark:text-red-400':
                                        overtimeSlider === preset,
                                }"
                                @click="setQuickSlider(preset)"
                            >
                                {{ preset > 0 ? '+' : '' }}{{ preset }}%
                            </button>
                        </div>
                    </div>

                    <!-- Additional Parameters: Budget Allocation & Target Dept -->
                    <div class="grid gap-3 pt-2 sm:grid-cols-2">
                        <!-- Budget Allocation Input -->
                        <div class="space-y-1">
                            <label
                                class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                {{ __('Plafon Anggaran Target (Rp)') }}
                            </label>
                            <input
                                v-model.number="budgetAllocation"
                                type="number"
                                min="0"
                                step="1000000"
                                class="h-9 w-full rounded-md border border-slate-300 bg-white px-3 font-mono text-xs font-bold text-slate-900 tabular-nums shadow-2xs focus:border-[#cc0000] focus:ring-1 focus:ring-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                                data-test="input-budget-allocation"
                            />
                            <p class="font-mono text-[10px] text-slate-400">
                                {{ formatRupiahCompact(budgetAllocation) }}
                            </p>
                        </div>

                        <!-- Target Department -->
                        <div class="space-y-1">
                            <label
                                class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                            >
                                {{ __('Lingkup Departemen') }}
                            </label>
                            <select
                                v-if="!isManager"
                                v-model="targetDeptId"
                                class="h-9 w-full rounded-md border border-slate-300 bg-white px-3 text-xs font-medium text-slate-700 shadow-2xs focus:border-[#cc0000] focus:ring-1 focus:ring-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200"
                                data-test="select-target-department"
                            >
                                <option value="all">
                                    {{ __('Semua Departemen (Lintas Pabrik)') }}
                                </option>
                                <option
                                    v-for="dept in departments"
                                    :key="dept.id"
                                    :value="String(dept.id)"
                                >
                                    {{ dept.name }}
                                </option>
                            </select>
                            <div
                                v-else
                                class="flex h-9 items-center rounded-md border border-slate-200 bg-slate-100 px-3 text-xs font-semibold text-slate-700 dark:border-slate-800 dark:bg-slate-800 dark:text-slate-300"
                            >
                                {{ currentResult.department_name }}
                            </div>
                            <p class="text-[10px] text-slate-400">
                                {{
                                    __(
                                        'Basis komparasi terhadap data aktual berjalan',
                                    )
                                }}
                            </p>
                        </div>
                    </div>

                    <!-- Calculation and Save Action Toolbar -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-t border-slate-200/60 pt-3 dark:border-slate-800"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            class="h-9 cursor-pointer border-slate-300 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-100 active:scale-98 dark:border-slate-700 dark:text-slate-300"
                            data-test="btn-trigger-save-dialog"
                            @click="saveDialogOpen = true"
                        >
                            <BookmarkPlus
                                class="mr-1.5 size-3.5 text-sky-600"
                            />
                            <span>{{ __('Simpan Skenario') }}</span>
                        </Button>

                        <Button
                            type="button"
                            class="h-9 cursor-pointer bg-slate-900 px-4 text-xs font-semibold text-white shadow-xs hover:bg-slate-800 active:scale-98 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100"
                            :disabled="isCalculating"
                            data-test="btn-run-scenario"
                            @click="runServerCalculation"
                        >
                            <Loader2
                                v-if="isCalculating"
                                class="mr-2 size-3.5 animate-spin"
                            />
                            <Play v-else class="mr-2 size-3.5 fill-current" />
                            <span>{{ __('Jalankan Skenario') }}</span>
                        </Button>
                    </div>
                </div>

                <!-- Save Inline Dialog (Smooth toggle) -->
                <div
                    v-if="saveDialogOpen"
                    class="space-y-3 rounded-lg border border-sky-200 bg-sky-50/60 p-3.5 dark:border-sky-900/60 dark:bg-sky-950/30"
                    data-test="save-scenario-dialog"
                >
                    <div class="flex items-center justify-between">
                        <h5
                            class="text-xs font-bold text-sky-900 dark:text-sky-300"
                        >
                            {{ __('Beri Nama Skenario Simulasi') }}
                        </h5>
                        <button
                            type="button"
                            class="text-xs text-slate-400 hover:text-slate-600"
                            @click="saveDialogOpen = false"
                        >
                            ✕
                        </button>
                    </div>
                    <div class="flex items-center gap-2">
                        <input
                            v-model="scenarioName"
                            type="text"
                            :placeholder="__('Contoh: Surge 1800 Unit Q4')"
                            class="h-8 flex-1 rounded border border-sky-300 bg-white px-2.5 text-xs text-slate-900 focus:border-sky-600 focus:outline-hidden dark:border-sky-800 dark:bg-slate-900 dark:text-white"
                            data-test="input-scenario-name"
                        />
                        <Button
                            size="sm"
                            class="h-8 cursor-pointer bg-sky-600 text-xs text-white hover:bg-sky-700"
                            :disabled="isSaving"
                            data-test="btn-confirm-save-scenario"
                            @click="saveCurrentScenario"
                        >
                            <Loader2
                                v-if="isSaving"
                                class="mr-1 size-3 animate-spin"
                            />
                            <span>{{ __('Simpan') }}</span>
                        </Button>
                    </div>
                </div>

                <!-- Notifications -->
                <div
                    v-if="saveSuccessMsg"
                    class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 p-2.5 text-xs text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
                >
                    <CheckCircle2 class="size-4 shrink-0 text-emerald-600" />
                    <span>{{ saveSuccessMsg }}</span>
                </div>

                <div
                    v-if="errorMsg"
                    class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-2.5 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                >
                    <AlertTriangle class="size-4 shrink-0 text-red-600" />
                    <span>{{ errorMsg }}</span>
                </div>

                <!-- 4 Scenario Result KPI Cards -->
                <div class="space-y-3">
                    <div
                        class="text-xs font-bold tracking-wider text-slate-600 uppercase dark:text-slate-400"
                    >
                        {{ __('Proyeksi Dampak Operasional & Finansial') }}
                    </div>

                    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <!-- Projected Cost Impact -->
                        <div
                            class="dark:bg-slate-850/60 rounded-lg border border-slate-200 bg-slate-50/70 p-3 shadow-2xs dark:border-slate-800"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-[11px] font-medium text-slate-500"
                                    >{{ __('Dampak Biaya (Rp)') }}</span
                                >
                                <TrendingUp
                                    v-if="currentResult.cost_impact > 0"
                                    class="size-3.5 text-[#cc0000]"
                                />
                                <TrendingDown
                                    v-else
                                    class="size-3.5 text-emerald-600"
                                />
                            </div>
                            <div
                                class="mt-1 font-mono text-lg font-bold tabular-nums"
                                :class="
                                    currentResult.cost_impact > 0
                                        ? 'text-[#cc0000] dark:text-red-400'
                                        : 'text-emerald-600 dark:text-emerald-400'
                                "
                                data-test="result-cost-impact"
                            >
                                {{ currentResult.formatted_cost_impact }}
                            </div>
                            <div class="mt-1 text-[10px] text-slate-400">
                                {{ __('Total:') }}
                                {{ currentResult.formatted_projected_cost }}
                            </div>
                        </div>

                        <!-- Production Volume Impact -->
                        <div
                            class="dark:bg-slate-850/60 rounded-lg border border-slate-200 bg-slate-50/70 p-3 shadow-2xs dark:border-slate-800"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-[11px] font-medium text-slate-500"
                                    >{{ __('Dampak Output Unit') }}</span
                                >
                                <Activity class="size-3.5 text-sky-600" />
                            </div>
                            <div
                                class="mt-1 font-mono text-lg font-bold text-slate-900 tabular-nums dark:text-white"
                                data-test="result-volume-impact"
                            >
                                {{
                                    currentResult.production_volume_impact_pct >
                                    0
                                        ? '+'
                                        : ''
                                }}{{
                                    currentResult.production_volume_impact_pct.toFixed(
                                        1,
                                    )
                                }}%
                            </div>
                            <div class="mt-1 text-[10px] text-slate-400">
                                {{ __('Korelasi r = 0.78') }}
                            </div>
                        </div>

                        <!-- Burn Index Projection -->
                        <div
                            class="dark:bg-slate-850/60 rounded-lg border border-slate-200 bg-slate-50/70 p-3 shadow-2xs dark:border-slate-800"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-[11px] font-medium text-slate-500"
                                    >{{ __('Proyeksi Burn Index') }}</span
                                >
                                <Gauge class="size-3.5 text-amber-500" />
                            </div>
                            <div
                                class="mt-1 font-mono text-lg font-bold tabular-nums"
                                :class="
                                    currentResult.burn_zone === 'danger'
                                        ? 'text-[#cc0000]'
                                        : currentResult.burn_zone === 'warning'
                                          ? 'text-amber-600'
                                          : 'text-emerald-600'
                                "
                                data-test="result-burn-index"
                            >
                                {{
                                    currentResult.projected_burn_index.toFixed(
                                        1,
                                    )
                                }}%
                            </div>
                            <div
                                class="mt-1 flex items-center gap-1 text-[10px]"
                            >
                                <span
                                    v-if="currentResult.burn_zone === 'danger'"
                                    class="font-semibold text-[#cc0000]"
                                >
                                    {{ __('Defisit Kritis (>115%)') }}
                                </span>
                                <span
                                    v-else-if="
                                        currentResult.burn_zone === 'warning'
                                    "
                                    class="font-semibold text-amber-600"
                                >
                                    {{ __('Peringatan (101-115%)') }}
                                </span>
                                <span
                                    v-else
                                    class="font-semibold text-emerald-600"
                                >
                                    {{ __('Sesuai Rencana (<100%)') }}
                                </span>
                            </div>
                        </div>

                        <!-- Safety Risk Score -->
                        <div
                            class="dark:bg-slate-850/60 rounded-lg border border-slate-200 bg-slate-50/70 p-3 shadow-2xs dark:border-slate-800"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-[11px] font-medium text-slate-500"
                                    >{{ __('Skor Risiko K3') }}</span
                                >
                                <ShieldAlert class="size-3.5 text-red-500" />
                            </div>
                            <div
                                class="mt-1 font-mono text-lg font-bold tabular-nums"
                                :class="
                                    currentResult.safety_risk_zone === 'high'
                                        ? 'text-[#cc0000]'
                                        : currentResult.safety_risk_zone ===
                                            'medium'
                                          ? 'text-amber-600'
                                          : 'text-emerald-600'
                                "
                                data-test="result-safety-risk"
                            >
                                {{
                                    currentResult.safety_risk_score.toFixed(1)
                                }}%
                            </div>
                            <div class="mt-1 text-[10px] text-slate-400">
                                {{
                                    currentResult.safety_risk_zone === 'high'
                                        ? __('Risiko Kelelahan Tinggi')
                                        : currentResult.safety_risk_zone ===
                                            'medium'
                                          ? __('Peringatan Jam Ekstrem')
                                          : __('Batas Aman Kebijakan')
                                }}
                            </div>
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
