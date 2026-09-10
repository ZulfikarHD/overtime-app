<script setup lang="ts">
import {
    Activity,
    AlertCircle,
    BookmarkPlus,
    Calendar,
    Check,
    CheckCircle2,
    Clock,
    DollarSign,
    Gauge,
    Loader2,
    ShieldAlert,
    Trash2,
    TrendingUp,
} from '@lucide/vue';
import { ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTrans } from '@/composables/useTrans';
import { destroy as destroyRoute } from '@/routes/analytics/scenario';

export interface SavedScenarioItem {
    id: string;
    name: string;
    created_at: string;
    created_at_label?: string;
    department_id: number | null;
    department_name: string;
    overtime_change_pct: number;
    budget_allocation: number;
    formatted_budget_allocation?: string;
    projected_hours: number;
    projected_cost: number;
    formatted_projected_cost?: string;
    projected_burn_index: number;
    burn_zone: 'safe' | 'on_track' | 'warning' | 'danger';
    safety_risk_score: number;
    production_volume_impact_pct: number;
}

interface Props {
    open: boolean;
    scenarios?: SavedScenarioItem[];
}

const props = withDefaults(defineProps<Props>(), {
    open: false,
    scenarios: () => [],
});

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'apply', scenario: SavedScenarioItem): void;
    (e: 'deleted', scenarioId: string): void;
}>();

const { __ } = useTrans();

const deletingId = ref<string | null>(null);
const successMsg = ref<string | null>(null);
const errorMsg = ref<string | null>(null);

function applyScenario(scenario: SavedScenarioItem) {
    emit('apply', scenario);
    emit('update:open', false);
}

async function deleteScenario(scenarioId: string) {
    deletingId.value = scenarioId;
    errorMsg.value = null;

    try {
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const url = destroyRoute.url({ id: scenarioId });
        const res = await fetch(url, {
            method: 'DELETE',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        if (!res.ok) {
            throw new Error(
                `HTTP error ${res.status}: Gagal menghapus skenario.`,
            );
        }

        const json = await res.json();
        if (json.status === 'success') {
            emit('deleted', scenarioId);
            successMsg.value = __('Skenario berhasil dihapus.');
            setTimeout(() => {
                successMsg.value = null;
            }, 3000);
        }
    } catch (err: unknown) {
        const error = err as Error;
        errorMsg.value =
            error?.message ?? __('Gagal menghapus skenario tersimpan.');
    } finally {
        deletingId.value = null;
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="(val) => emit('update:open', val)">
        <SheetContent
            side="right"
            class="flex w-full flex-col gap-0 p-0 sm:max-w-md"
            data-test="saved-scenarios-drawer"
        >
            <!-- Drawer Header -->
            <SheetHeader
                class="border-b border-slate-200 p-5 dark:border-slate-800"
            >
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <BookmarkPlus class="size-5 text-sky-600" />
                        <SheetTitle
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            {{ __('Daftar Skenario Tersimpan') }}
                        </SheetTitle>
                    </div>
                    <Badge
                        variant="outline"
                        class="font-mono text-xs tabular-nums"
                    >
                        {{ scenarios.length }} {{ __('Tersimpan') }}
                    </Badge>
                </div>
                <SheetDescription class="text-xs text-slate-500">
                    {{
                        __(
                            'Pilih skenario yang pernah disimpan untuk diterapkan kembali ke simulator, atau hapus preset yang tidak relevan.',
                        )
                    }}
                </SheetDescription>
            </SheetHeader>

            <!-- Alerts -->
            <div
                v-if="successMsg"
                class="mx-5 mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-2.5 text-xs text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
            >
                <div class="flex items-center gap-2">
                    <CheckCircle2 class="size-4 shrink-0 text-emerald-600" />
                    <span>{{ successMsg }}</span>
                </div>
            </div>

            <div
                v-if="errorMsg"
                class="mx-5 mt-3 rounded-lg border border-red-200 bg-red-50 p-2.5 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
            >
                <div class="flex items-center gap-2">
                    <AlertCircle class="size-4 shrink-0 text-red-600" />
                    <span>{{ errorMsg }}</span>
                </div>
            </div>

            <!-- Drawer Body: Scenarios Card List -->
            <div class="flex-1 space-y-3.5 overflow-y-auto p-5">
                <!-- Empty State -->
                <div
                    v-if="scenarios.length === 0"
                    class="flex h-64 flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center dark:border-slate-800 dark:bg-slate-900/50"
                    data-test="saved-scenarios-empty-state"
                >
                    <BookmarkPlus
                        class="mb-2 size-10 text-slate-300 dark:text-slate-600"
                    />
                    <h4
                        class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                    >
                        {{ __('Belum Ada Skenario Tersimpan') }}
                    </h4>
                    <p class="mt-1 text-[11px] text-slate-500">
                        {{
                            __(
                                'Gunakan tombol "Simpan Skenario" di simulator untuk menyimpan hasil konfigurasi simulasi beban kerja.',
                            )
                        }}
                    </p>
                </div>

                <!-- Scenario Cards -->
                <div
                    v-for="item in scenarios"
                    :key="item.id"
                    class="rounded-xl border border-slate-200 bg-white p-4 shadow-2xs transition-all hover:border-slate-300 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700"
                    data-test="saved-scenario-item"
                >
                    <!-- Card Top Row: Name & Date -->
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h4
                                class="text-xs font-bold text-slate-900 dark:text-white"
                            >
                                {{ item.name }}
                            </h4>
                            <p
                                class="mt-0.5 flex items-center gap-1 text-[10px] text-slate-400"
                            >
                                <Calendar class="size-3 text-slate-400" />
                                <span>{{
                                    item.created_at_label || item.created_at
                                }}</span>
                            </p>
                        </div>
                        <Badge
                            class="font-mono text-[10px] font-bold tabular-nums"
                            :class="
                                item.overtime_change_pct > 0
                                    ? 'bg-red-100 text-[#cc0000] dark:bg-red-950/60 dark:text-red-300'
                                    : item.overtime_change_pct < 0
                                      ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                      : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'
                            "
                        >
                            {{ item.overtime_change_pct > 0 ? '+' : ''
                            }}{{ item.overtime_change_pct }}%
                        </Badge>
                    </div>

                    <!-- Department & Metrics Grid -->
                    <div
                        class="dark:bg-slate-850 mt-3 grid grid-cols-2 gap-2 rounded-lg bg-slate-50/70 p-2.5 text-[11px]"
                    >
                        <div>
                            <span class="text-slate-400">{{
                                __('Jam Lembur:')
                            }}</span>
                            <div
                                class="font-mono font-bold text-slate-800 tabular-nums dark:text-slate-200"
                            >
                                {{ item.projected_hours.toFixed(1) }}
                                {{ __('Jam') }}
                            </div>
                        </div>
                        <div>
                            <span class="text-slate-400">{{
                                __('Estimasi Biaya:')
                            }}</span>
                            <div
                                class="font-mono font-bold text-slate-800 tabular-nums dark:text-slate-200"
                            >
                                {{
                                    item.formatted_projected_cost ||
                                    'Rp ' +
                                        Math.round(
                                            item.projected_cost,
                                        ).toLocaleString('id-ID')
                                }}
                            </div>
                        </div>
                        <div>
                            <span class="text-slate-400">{{
                                __('Burn Index:')
                            }}</span>
                            <div
                                class="font-mono font-bold tabular-nums"
                                :class="
                                    item.projected_burn_index > 115
                                        ? 'text-[#cc0000]'
                                        : item.projected_burn_index > 100
                                          ? 'text-amber-600'
                                          : 'text-emerald-600'
                                "
                            >
                                {{ item.projected_burn_index.toFixed(1) }}%
                            </div>
                        </div>
                        <div>
                            <span class="text-slate-400">{{
                                __('Risiko K3:')
                            }}</span>
                            <div
                                class="font-mono font-bold tabular-nums"
                                :class="
                                    item.safety_risk_score > 30
                                        ? 'text-[#cc0000]'
                                        : item.safety_risk_score >= 15
                                          ? 'text-amber-600'
                                          : 'text-emerald-600'
                                "
                            >
                                {{ item.safety_risk_score.toFixed(1) }}%
                            </div>
                        </div>
                    </div>

                    <!-- Card Actions -->
                    <div
                        class="mt-3 flex items-center justify-between border-t border-slate-100 pt-2 dark:border-slate-800"
                    >
                        <button
                            type="button"
                            class="flex cursor-pointer items-center gap-1 text-xs font-semibold text-red-600 hover:text-red-700 disabled:opacity-50 dark:text-red-400"
                            :disabled="deletingId === item.id"
                            data-test="btn-delete-saved-scenario"
                            @click="deleteScenario(item.id)"
                        >
                            <Loader2
                                v-if="deletingId === item.id"
                                class="size-3 animate-spin"
                            />
                            <Trash2 v-else class="size-3.5" />
                            <span>{{ __('Hapus') }}</span>
                        </button>

                        <Button
                            size="sm"
                            class="h-7 cursor-pointer bg-[#cc0000] px-3 text-xs font-semibold text-white shadow-xs hover:bg-[#b30000]"
                            data-test="btn-apply-saved-scenario"
                            @click="applyScenario(item)"
                        >
                            <Check class="mr-1 size-3" />
                            <span>{{ __('Terapkan Skenario') }}</span>
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Drawer Footer -->
            <SheetFooter
                class="border-t border-slate-200 p-4 dark:border-slate-800"
            >
                <SheetClose as-child>
                    <Button
                        variant="outline"
                        class="w-full text-xs font-semibold"
                    >
                        {{ __('Tutup') }}
                    </Button>
                </SheetClose>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
