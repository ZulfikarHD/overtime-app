<script setup lang="ts">
import {
    AlertCircle,
    ChevronDown,
    ChevronUp,
    FolderKanban,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';

export interface CapexProjectOption {
    id: number;
    project_code: string;
    asset_code?: string | null;
    name: string;
}

export interface OvertimeItemModel {
    employee_id: number;
    npk: string;
    full_name: string;
    job_position: string;
    hourly_rate: number | string | null;
    hours_production: number;
    hours_tpm: number;
    hours_project: number;
    hours_others: number;
    capex_project_id: number | null;
    rca_category: string | null;
    rca_notes: string | null;
    task_description: string | null;
}

const props = defineProps<{
    modelValue: OvertimeItemModel;
    defaultHourlyRate?: number | string | null;
    capexProjects: CapexProjectOption[];
    error?: string;
    index: number;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: OvertimeItemModel): void;
    (e: 'remove'): void;
}>();

const { __ } = useTrans();
const showRca = ref(
    Boolean(
        props.modelValue.rca_category ||
        props.modelValue.rca_notes ||
        props.modelValue.task_description,
    ),
);

const totalHours = computed(() => {
    const prod = Number(props.modelValue.hours_production) || 0;
    const tpm = Number(props.modelValue.hours_tpm) || 0;
    const proj = Number(props.modelValue.hours_project) || 0;
    const oth = Number(props.modelValue.hours_others) || 0;
    return Number((prod + tpm + proj + oth).toFixed(2));
});

const effectiveRate = computed(() => {
    const empRate = Number(props.modelValue.hourly_rate);
    if (!isNaN(empRate) && empRate > 0) {
        return empRate;
    }
    const defRate = Number(props.defaultHourlyRate);
    return !isNaN(defRate) && defRate > 0 ? defRate : 0;
});

const estimatedCost = computed(() => {
    return totalHours.value * effectiveRate.value;
});

function updateField<K extends keyof OvertimeItemModel>(
    key: K,
    value: OvertimeItemModel[K],
) {
    emit('update:modelValue', {
        ...props.modelValue,
        [key]: value,
    });
}

function handleHourInput(
    key: 'hours_production' | 'hours_tpm' | 'hours_project' | 'hours_others',
    event: Event,
) {
    const target = event.target as HTMLInputElement;
    const rawVal = target.value;
    if (rawVal === '' || rawVal === null) {
        props.modelValue[key] = 0;
        updateField(key, 0);
        return;
    }
    const val = parseFloat(rawVal);
    const safeVal = isNaN(val) || val < 0 ? 0 : val;
    props.modelValue[key] = safeVal;
    updateField(key, safeVal);
}

const rcaCategories = [
    {
        value: 'MACHINE_BREAKDOWN',
        label: 'Kerusakan Mesin (Machine Breakdown)',
    },
    { value: 'SUPPLIER_DELAY', label: 'Keterlambatan Komponen / Supplier' },
    { value: 'QUALITY_REWORK', label: 'Rework Kualitas (Quality Defect)' },
    { value: 'CUSTOMER_RUSH', label: 'Permintaan Khusus Pelanggan' },
    { value: 'TRIAL_MODEL', label: 'Trial Model Baru' },
    { value: 'FACILITY_MAINTENANCE', label: 'Perawatan Fasilitas Pabrik' },
    { value: 'OTHER', label: 'Lainnya (Other Reason)' },
];
</script>

<template>
    <div
        class="border-b border-slate-200 transition-colors last:border-b-0 dark:border-slate-800"
        :class="[
            error
                ? 'bg-red-50/70 dark:bg-red-950/20'
                : 'dark:hover:bg-slate-850/40 hover:bg-slate-50/70',
        ]"
        :data-test="`item-row-${modelValue.employee_id}`"
    >
        <!-- Main Line Item Row -->
        <div class="grid grid-cols-12 items-center gap-2 px-3 py-2.5 text-xs">
            <!-- Col 1: Worker Info (3 cols) -->
            <div class="col-span-12 sm:col-span-3">
                <div class="flex items-center gap-2">
                    <span
                        class="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                    >
                        {{ modelValue.npk }}
                    </span>
                    <span
                        class="truncate font-semibold text-slate-900 dark:text-white"
                        :title="modelValue.full_name"
                    >
                        {{ modelValue.full_name }}
                    </span>
                </div>
                <div
                    class="mt-0.5 flex items-center gap-2 text-[11px] text-slate-500"
                >
                    <span class="truncate">{{ modelValue.job_position }}</span>
                    <span>·</span>
                    <span class="font-mono text-[10px]"
                        >{{ formatRupiah(effectiveRate) }}/jam</span
                    >
                </div>
            </div>

            <!-- Col 2: Production Hours (2 cols) -->
            <div class="col-span-3 sm:col-span-2">
                <label
                    class="block text-[10px] font-medium text-slate-500 sm:hidden"
                    >{{ __('Produksi') }}</label
                >
                <div class="relative">
                    <input
                        type="text"
                        inputmode="decimal"
                        :value="modelValue.hours_production || ''"
                        @input="handleHourInput('hours_production', $event)"
                        placeholder="0.0"
                        :name="`items[${index}][hours_production]`"
                        :data-test="`input-prod-${modelValue.employee_id}`"
                        class="h-8 w-full rounded border border-slate-300 bg-white px-2 text-right font-mono text-xs text-slate-900 tabular-nums focus:border-[#cc0000] focus:ring-1 focus:ring-[#cc0000] dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    />
                </div>
            </div>

            <!-- Col 3: TPM Hours (2 cols) -->
            <div class="col-span-3 sm:col-span-2">
                <label
                    class="block text-[10px] font-medium text-slate-500 sm:hidden"
                    >{{ __('TPM') }}</label
                >
                <div class="relative">
                    <input
                        type="text"
                        inputmode="decimal"
                        :value="modelValue.hours_tpm || ''"
                        @input="handleHourInput('hours_tpm', $event)"
                        placeholder="0.0"
                        :name="`items[${index}][hours_tpm]`"
                        :data-test="`input-tpm-${modelValue.employee_id}`"
                        class="h-8 w-full rounded border border-slate-300 bg-white px-2 text-right font-mono text-xs text-slate-900 tabular-nums focus:border-[#cc0000] focus:ring-1 focus:ring-[#cc0000] dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    />
                </div>
            </div>

            <!-- Col 4: Project CapEx Hours (2 cols) -->
            <div class="col-span-3 sm:col-span-2">
                <label
                    class="block text-[10px] font-medium text-slate-500 sm:hidden"
                    >{{ __('CapEx') }}</label
                >
                <div class="relative">
                    <input
                        type="text"
                        inputmode="decimal"
                        :value="modelValue.hours_project || ''"
                        @input="handleHourInput('hours_project', $event)"
                        placeholder="0.0"
                        :name="`items[${index}][hours_project]`"
                        :data-test="`input-project-${modelValue.employee_id}`"
                        class="h-8 w-full rounded border border-sky-300 bg-sky-50/40 px-2 text-right font-mono text-xs text-sky-950 tabular-nums focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-sky-800 dark:bg-sky-950/30 dark:text-sky-200"
                    />
                </div>
            </div>

            <!-- Col 5: Others Hours (1 col) -->
            <div class="col-span-3 sm:col-span-1">
                <label
                    class="block text-[10px] font-medium text-slate-500 sm:hidden"
                    >{{ __('Lainnya') }}</label
                >
                <div class="relative">
                    <input
                        type="text"
                        inputmode="decimal"
                        :value="modelValue.hours_others || ''"
                        @input="handleHourInput('hours_others', $event)"
                        placeholder="0.0"
                        :name="`items[${index}][hours_others]`"
                        :data-test="`input-others-${modelValue.employee_id}`"
                        class="h-8 w-full rounded border border-slate-300 bg-white px-2 text-right font-mono text-xs text-slate-900 tabular-nums focus:border-[#cc0000] focus:ring-1 focus:ring-[#cc0000] dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                    />
                </div>
            </div>

            <!-- Col 6: Total Hours & Estimated Cost (1 col) -->
            <div class="col-span-6 text-right sm:col-span-1">
                <div
                    class="font-mono text-xs font-bold tabular-nums"
                    :class="
                        totalHours >= 0.5
                            ? 'text-slate-900 dark:text-white'
                            : 'text-amber-600 dark:text-amber-400'
                    "
                    :data-test="`row-total-${modelValue.employee_id}`"
                >
                    {{ totalHours.toFixed(1) }}
                    <span class="text-[10px] font-normal text-slate-500"
                        >jam</span
                    >
                </div>
                <div
                    class="cursor-help font-mono text-[10px] text-slate-500 tabular-nums"
                    :title="
                        __(
                            'Estimasi biaya dihitung otomatis menggunakan tarif standar karyawan saat pengajuan (Snapshot Biaya Terkunci).',
                        )
                    "
                    :data-test="`row-cost-${modelValue.employee_id}`"
                >
                    {{ formatRupiah(estimatedCost) }}
                </div>
            </div>

            <!-- Col 7: Controls & Remove Button (1 col) -->
            <div
                class="col-span-6 flex items-center justify-end gap-1 sm:col-span-1"
            >
                <button
                    type="button"
                    @click="showRca = !showRca"
                    class="flex size-7 items-center justify-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                    :title="__('Catatan / RCA')"
                    :data-test="`btn-toggle-rca-${modelValue.employee_id}`"
                >
                    <component
                        :is="showRca ? ChevronUp : ChevronDown"
                        class="size-3.5"
                    />
                </button>
                <button
                    type="button"
                    @click="emit('remove')"
                    class="flex size-7 items-center justify-center rounded text-slate-400 hover:bg-red-100 hover:text-[#cc0000] dark:hover:bg-red-950/40 dark:hover:text-red-400"
                    :title="__('Hapus Baris')"
                    :data-test="`btn-remove-row-${modelValue.employee_id}`"
                >
                    <Trash2 class="size-3.5" />
                </button>
            </div>
        </div>

        <!-- Inline Error Banner if BR-01 or other row validation failed -->
        <div
            v-if="error"
            class="mx-3 mb-2 flex items-center gap-1.5 rounded border border-red-200 bg-red-100/70 px-2 py-1 text-[11px] font-medium text-red-700 dark:border-red-900 dark:bg-red-950/60 dark:text-red-300"
            :data-test="`error-banner-${modelValue.employee_id}`"
        >
            <AlertCircle class="size-3.5 shrink-0" />
            <span>{{ error }}</span>
        </div>

        <!-- Progressive Disclosure 1: CapEx Project Selection (Visible when hours_project > 0) -->
        <div
            v-if="modelValue.hours_project > 0"
            class="border-t border-sky-100 bg-sky-50/60 px-3 py-2 dark:border-sky-950 dark:bg-sky-950/20"
            :data-test="`capex-container-${modelValue.employee_id}`"
        >
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center">
                <div
                    class="flex items-center gap-1.5 text-[11px] font-semibold text-sky-900 sm:w-48 dark:text-sky-300"
                >
                    <FolderKanban
                        class="size-3.5 text-sky-600 dark:text-sky-400"
                    />
                    <span>{{ __('Alokasi Proyek CapEx') }}</span>
                    <span class="text-red-500">*</span>
                </div>
                <div class="flex-1">
                    <select
                        :value="modelValue.capex_project_id || ''"
                        @change="
                            updateField(
                                'capex_project_id',
                                Number(
                                    ($event.target as HTMLSelectElement).value,
                                ) || null,
                            )
                        "
                        class="h-8 w-full rounded border border-sky-300 bg-white px-2 text-xs text-sky-950 focus:border-sky-500 focus:ring-1 focus:ring-sky-500 dark:border-sky-800 dark:bg-slate-900 dark:text-sky-200"
                        :data-test="`select-capex-${modelValue.employee_id}`"
                    >
                        <option value="">
                            {{ __('-- Pilih Proyek Investasi (CapEx) --') }}
                        </option>
                        <option
                            v-for="project in capexProjects"
                            :key="project.id"
                            :value="project.id"
                        >
                            {{ project.project_code }} · {{ project.name }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Progressive Disclosure 2: RCA Category & Task Description -->
        <div
            v-if="showRca"
            class="border-t border-slate-100 bg-slate-50/50 px-3 py-2.5 dark:border-slate-800/60 dark:bg-slate-900/40"
            :data-test="`rca-container-${modelValue.employee_id}`"
        >
            <div class="grid grid-cols-1 gap-2 sm:grid-cols-12 sm:gap-3">
                <div class="sm:col-span-4">
                    <label
                        class="block text-[10px] font-medium text-slate-500"
                        >{{ __('Kategori RCA (Opsional)') }}</label
                    >
                    <select
                        :value="modelValue.rca_category || ''"
                        @change="
                            updateField(
                                'rca_category',
                                ($event.target as HTMLSelectElement).value ||
                                    null,
                            )
                        "
                        class="mt-1 h-7.5 w-full rounded border border-slate-300 bg-white px-2 text-xs text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        :data-test="`select-rca-${modelValue.employee_id}`"
                    >
                        <option value="">
                            {{ __('-- Tanpa Kategori Khusus --') }}
                        </option>
                        <option
                            v-for="cat in rcaCategories"
                            :key="cat.value"
                            :value="cat.value"
                        >
                            {{ cat.label }}
                        </option>
                    </select>
                </div>
                <div class="sm:col-span-4">
                    <label
                        class="block text-[10px] font-medium text-slate-500"
                        >{{ __('Deskripsi Pekerjaan') }}</label
                    >
                    <input
                        type="text"
                        :value="modelValue.task_description || ''"
                        @input="
                            updateField(
                                'task_description',
                                ($event.target as HTMLInputElement).value ||
                                    null,
                            )
                        "
                        placeholder="Contoh: Overtime shift perakitan chassis"
                        class="mt-1 h-7.5 w-full rounded border border-slate-300 bg-white px-2 text-xs text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        :data-test="`input-task-${modelValue.employee_id}`"
                    />
                </div>
                <div class="sm:col-span-4">
                    <label
                        class="block text-[10px] font-medium text-slate-500"
                        >{{ __('Catatan Akar Masalah (RCA)') }}</label
                    >
                    <input
                        type="text"
                        :value="modelValue.rca_notes || ''"
                        @input="
                            updateField(
                                'rca_notes',
                                ($event.target as HTMLInputElement).value ||
                                    null,
                            )
                        "
                        placeholder="Contoh: Penggantian bearing die stamping"
                        class="mt-1 h-7.5 w-full rounded border border-slate-300 bg-white px-2 text-xs text-slate-800 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                        :data-test="`input-rca-notes-${modelValue.employee_id}`"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
