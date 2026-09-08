<script setup lang="ts">
import {
    ChevronDown,
    Download,
    FileSpreadsheet,
    FileText,
    Loader2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
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
import { exportMethod } from '@/routes/overtime/approvals';

interface ExportFilters {
    status?: string;
    department_id?: string | number;
    section_id?: string | number;
    spkl_status?: string;
    date_from?: string;
    date_to?: string;
    sort?: string;
    direction?: string;
}

const props = defineProps<{
    filters: ExportFilters;
    departmentName?: string | null;
}>();

const { __ } = useTrans();
const isDownloading = ref(false);

const activeFilterSummary = computed(() => {
    const parts: string[] = [];

    if (props.departmentName) {
        parts.push(props.departmentName);
    }

    if (props.filters.date_from && props.filters.date_to) {
        parts.push(`${props.filters.date_from} s/d ${props.filters.date_to}`);
    } else if (props.filters.date_from) {
        parts.push(`≥ ${props.filters.date_from}`);
    }

    if (parts.length > 0) {
        return parts.join(' · ');
    }

    return __('Sesuai filter aktif saat ini');
});

function triggerExport(format: 'csv' | 'xlsx') {
    if (isDownloading.value) {
        return;
    }

    isDownloading.value = true;

    const queryParams: Record<string, string> = {
        format,
    };

    if (props.filters.status) {
        queryParams.status = props.filters.status;
    }
    if (props.filters.department_id) {
        queryParams.department_id = String(props.filters.department_id);
    }
    if (props.filters.section_id) {
        queryParams.section_id = String(props.filters.section_id);
    }
    if (props.filters.spkl_status) {
        queryParams.spkl_status = props.filters.spkl_status;
    }
    if (props.filters.date_from) {
        queryParams.date_from = props.filters.date_from;
    }
    if (props.filters.date_to) {
        queryParams.date_to = props.filters.date_to;
    }
    if (props.filters.sort) {
        queryParams.sort = props.filters.sort;
    }
    if (props.filters.direction) {
        queryParams.direction = props.filters.direction;
    }

    const downloadUrl = exportMethod.url({ query: queryParams });

    const link = document.createElement('a');
    link.href = downloadUrl;
    link.setAttribute('download', '');
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    setTimeout(() => {
        isDownloading.value = false;
    }, 2500);
}
</script>

<template>
    <div class="inline-block">
        <DropdownMenu>
            <DropdownMenuTrigger as-child>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-9 cursor-pointer gap-2 border-slate-300 bg-white px-3 font-semibold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                    :disabled="isDownloading"
                    data-test="export-dropdown-trigger"
                >
                    <Loader2
                        v-if="isDownloading"
                        class="size-3.5 animate-spin text-[#cc0000]"
                    />
                    <Download
                        v-else
                        class="size-3.5 text-slate-600 dark:text-slate-300"
                    />

                    <span>
                        {{
                            isDownloading
                                ? __('Menyiapkan berkas...')
                                : __('Export Data')
                        }}
                    </span>

                    <ChevronDown class="size-3 text-slate-400" />
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent
                align="end"
                class="w-72 p-1.5"
                data-test="export-dropdown-menu"
            >
                <DropdownMenuLabel
                    class="px-2 py-1 text-xs font-bold text-slate-800 dark:text-slate-100"
                >
                    {{ __('Pilih Format Unduhan') }}
                </DropdownMenuLabel>
                <div
                    class="px-2 pb-1.5 text-[11px] text-slate-500 dark:text-slate-400"
                >
                    {{ activeFilterSummary }}
                </div>
                <DropdownMenuSeparator />

                <DropdownMenuItem
                    class="flex cursor-pointer items-start gap-2.5 rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800"
                    data-test="export-csv-option"
                    @click="triggerExport('csv')"
                >
                    <div
                        class="mt-0.5 rounded-md bg-emerald-100 p-1.5 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
                    >
                        <FileText class="size-4" />
                    </div>
                    <div>
                        <div
                            class="text-xs font-semibold text-slate-900 dark:text-white"
                        >
                            {{ __('Unduh Format CSV (.csv)') }}
                        </div>
                        <div
                            class="text-[10px] text-slate-500 dark:text-slate-400"
                        >
                            {{
                                __('Format teks untuk integrasi ERP & Payroll')
                            }}
                        </div>
                    </div>
                </DropdownMenuItem>

                <DropdownMenuItem
                    class="flex cursor-pointer items-start gap-2.5 rounded-md p-2 hover:bg-slate-100 dark:hover:bg-slate-800"
                    data-test="export-xlsx-option"
                    @click="triggerExport('xlsx')"
                >
                    <div
                        class="mt-0.5 rounded-md bg-sky-100 p-1.5 text-sky-700 dark:bg-sky-950/60 dark:text-sky-300"
                    >
                        <FileSpreadsheet class="size-4" />
                    </div>
                    <div>
                        <div
                            class="text-xs font-semibold text-slate-900 dark:text-white"
                        >
                            {{ __('Unduh Format Excel (.xlsx)') }}
                        </div>
                        <div
                            class="text-[10px] text-slate-500 dark:text-slate-400"
                        >
                            {{
                                __(
                                    'Format spreadsheet laporan keuangan & audit',
                                )
                            }}
                        </div>
                    </div>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    </div>
</template>
