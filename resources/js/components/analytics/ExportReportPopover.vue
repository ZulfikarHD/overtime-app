<script setup lang="ts">
import {
    ChevronDown,
    Download,
    FileSpreadsheet,
    FileText,
    Loader2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useTrans } from '@/composables/useTrans';
import { exportMethod as analyticsExport } from '@/routes/analytics';

interface DepartmentItem {
    id: number;
    code: string;
    name: string;
}

const props = defineProps<{
    activeTab: string;
    selectedDepartment: DepartmentItem | null;
    startDate: string;
    endDate: string;
}>();

const { __ } = useTrans();
const isExporting = ref(false);

const tabLabels: Record<string, string> = {
    predictive: 'Prediksi Lembur',
    cost: 'Analisis Biaya',
    correlation: 'Korelasi & Pola',
    scenario: 'Simulasi Skenario',
    insights: 'Wawasan Kunci',
    comparison: 'Perbandingan Periode',
};

const activeTabLabel = computed(() => {
    return tabLabels[props.activeTab] || props.activeTab;
});

const departmentLabel = computed(() => {
    if (props.selectedDepartment) {
        return `${props.selectedDepartment.code} - ${props.selectedDepartment.name}`;
    }
    return __('Semua Departemen (Lintas Pabrik)');
});

function handleExport(format: 'pdf' | 'csv') {
    if (isExporting.value) {
        return;
    }

    isExporting.value = true;
    toast.info(__('Menyiapkan dokumen ekspor...'), {
        description: `${departmentLabel.value} • ${activeTabLabel.value}`,
    });

    try {
        const targetUrl = analyticsExport.url({
            query: {
                format,
                tab: props.activeTab,
                department_id: props.selectedDepartment
                    ? props.selectedDepartment.id
                    : undefined,
                start_date: props.startDate || undefined,
                end_date: props.endDate || undefined,
            },
        });

        const link = document.createElement('a');
        link.href = targetUrl;
        link.setAttribute('download', '');
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);

        setTimeout(() => {
            isExporting.value = false;
            toast.success(__('Unduhan berhasil dimulai.'));
        }, 1500);
    } catch {
        isExporting.value = false;
        toast.error(__('Gagal mengunduh laporan. Silakan coba kembali.'));
    }
}
</script>

<template>
    <div class="relative inline-block">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-9 shrink-0 cursor-pointer gap-1.5 border-slate-300 bg-white px-3 font-semibold text-slate-800 shadow-2xs hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800"
                    :disabled="isExporting"
                    data-test="btn-analytics-export"
                >
                    <Loader2
                        v-if="isExporting"
                        class="size-3.5 animate-spin text-[#cc0000]"
                    />
                    <Download
                        v-else
                        class="size-3.5 text-slate-600 dark:text-slate-400"
                    />
                    <span class="text-xs">{{
                        isExporting ? __('Memproses...') : __('Ekspor Laporan')
                    }}</span>
                    <ChevronDown
                        class="size-3 text-slate-400 dark:text-slate-500"
                    />
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align="end"
                class="w-72 border-slate-200 bg-white p-1 text-slate-900 shadow-md dark:border-slate-800 dark:bg-slate-900 dark:text-slate-100"
            >
                <DropdownMenuLabel class="px-2 py-1.5 text-xs">
                    <div class="font-bold text-slate-900 dark:text-slate-100">
                        {{ __('Unduh Ringkasan Eksekutif') }}
                    </div>
                    <div class="text-[11px] font-normal text-slate-500">
                        {{ departmentLabel }}
                    </div>
                    <div
                        class="mt-1 flex items-center gap-1 font-mono text-[10px] text-slate-400 tabular-nums"
                    >
                        <span>{{ activeTabLabel }}</span>
                        <span>•</span>
                        <span>{{ startDate }} ~ {{ endDate }}</span>
                    </div>
                </DropdownMenuLabel>

                <DropdownMenuSeparator
                    class="my-1 bg-slate-100 dark:bg-slate-800"
                />

                <DropdownMenuItem
                    class="flex cursor-pointer items-start gap-2.5 rounded-md px-2.5 py-2 hover:bg-slate-50 focus:bg-slate-50 dark:hover:bg-slate-800/60 dark:focus:bg-slate-800/60"
                    @click="handleExport('pdf')"
                    data-test="export-pdf-option"
                >
                    <div
                        class="mt-0.5 rounded-xs bg-red-100 p-1 text-[#cc0000] dark:bg-red-950/60 dark:text-red-400"
                    >
                        <FileText class="size-4 shrink-0" />
                    </div>
                    <div class="flex-1 text-xs">
                        <div
                            class="font-semibold text-slate-900 dark:text-slate-100"
                        >
                            {{ __('PDF (Executive 1-Page Summary)') }}
                        </div>
                        <div class="text-[10px] text-slate-500">
                            {{
                                __(
                                    'Laporan ringkas A4 dengan format standar ISUZU.',
                                )
                            }}
                        </div>
                    </div>
                </DropdownMenuItem>

                <DropdownMenuItem
                    class="flex cursor-pointer items-start gap-2.5 rounded-md px-2.5 py-2 hover:bg-slate-50 focus:bg-slate-50 dark:hover:bg-slate-800/60 dark:focus:bg-slate-800/60"
                    @click="handleExport('csv')"
                    data-test="export-csv-option"
                >
                    <div
                        class="mt-0.5 rounded-xs bg-emerald-100 p-1 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400"
                    >
                        <FileSpreadsheet class="size-4 shrink-0" />
                    </div>
                    <div class="flex-1 text-xs">
                        <div
                            class="font-semibold text-slate-900 dark:text-slate-100"
                        >
                            {{ __('CSV (Data Mentah Tab Ini)') }}
                        </div>
                        <div class="text-[10px] text-slate-500">
                            {{
                                __(
                                    'Unduh data parameter tabular terstruktur UTF-8.',
                                )
                            }}
                        </div>
                    </div>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
