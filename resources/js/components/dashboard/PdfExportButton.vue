<script setup lang="ts">
import {
    Calendar,
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
import { exportPdf } from '@/routes/dashboard/burn-index';

interface DepartmentItem {
    id: number;
    code: string;
    name: string;
}

const props = defineProps<{
    fiscalYear: number;
    fiscalMonth: number;
    selectedDepartment: DepartmentItem | null;
}>();

const { __ } = useTrans();
const isExporting = ref(false);

const monthNames = [
    '',
    'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember',
];

const periodLabel = computed(() => {
    const m = monthNames[props.fiscalMonth] || `Bulan ${props.fiscalMonth}`;
    return `${m} ${props.fiscalYear}`;
});

const departmentLabel = computed(() => {
    if (props.selectedDepartment) {
        return `${props.selectedDepartment.code} - ${props.selectedDepartment.name}`;
    }
    return __('Semua Departemen (Lintas Pabrik)');
});

function handleExport(reportType: 'standup' | 'monthly') {
    if (isExporting.value) return;

    isExporting.value = true;
    toast.info(__('Menyiapkan dokumen PDF...'), {
        description: `${departmentLabel.value} • ${periodLabel.value}`,
    });

    try {
        const targetUrl = exportPdf.url({
            query: {
                year: props.fiscalYear,
                month: props.fiscalMonth,
                department_id: props.selectedDepartment
                    ? props.selectedDepartment.id
                    : undefined,
                type: reportType,
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
        }, 2000);
    } catch {
        isExporting.value = false;
        toast.error(__('Gagal mengunduh laporan PDF. Silakan coba kembali.'));
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
                    data-test="btn-pdf-export"
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
                        isExporting
                            ? __('Menyiapkan PDF...')
                            : __('Unduh Laporan PDF')
                    }}</span>
                    <ChevronDown class="size-3 text-slate-400" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                class="w-72 p-1.5 text-xs shadow-md"
                data-test="dropdown-pdf-options"
            >
                <DropdownMenuLabel
                    class="px-2 py-1.5 text-[11px] font-semibold text-slate-500 dark:text-slate-400"
                >
                    <div class="flex items-center gap-1.5">
                        <Calendar class="size-3 text-slate-400" />
                        <span class="truncate">{{ departmentLabel }}</span>
                    </div>
                    <div class="mt-0.5 text-[10px] font-normal text-slate-400">
                        {{ periodLabel }}
                    </div>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />

                <DropdownMenuItem
                    class="cursor-pointer gap-2.5 p-2 focus:bg-slate-100 dark:focus:bg-slate-800"
                    @click="handleExport('standup')"
                    data-test="option-pdf-standup"
                >
                    <div
                        class="rounded-md bg-red-50 p-1.5 text-[#cc0000] dark:bg-red-950/50"
                    >
                        <FileText class="size-4" />
                    </div>
                    <div>
                        <div class="font-bold text-slate-900 dark:text-white">
                            {{
                                __('Ringkasan Standup Mingguan (1 Halaman PDF)')
                            }}
                        </div>
                        <div
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            {{
                                __(
                                    'Format ringkas untuk briefing pagi manajemen',
                                )
                            }}
                        </div>
                    </div>
                </DropdownMenuItem>

                <DropdownMenuItem
                    class="cursor-pointer gap-2.5 p-2 focus:bg-slate-100 dark:focus:bg-slate-800"
                    @click="handleExport('monthly')"
                    data-test="option-pdf-monthly"
                >
                    <div
                        class="rounded-md bg-sky-50 p-1.5 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300"
                    >
                        <FileSpreadsheet class="size-4" />
                    </div>
                    <div>
                        <div class="font-bold text-slate-900 dark:text-white">
                            {{ __('Laporan Analisis Bulanan Lengkap (PDF)') }}
                        </div>
                        <div
                            class="text-[11px] text-slate-500 dark:text-slate-400"
                        >
                            {{
                                __(
                                    'Lengkap dengan analisis CapEx dan rincian proyek',
                                )
                            }}
                        </div>
                    </div>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
