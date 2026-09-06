<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    Calendar,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    RefreshCw,
    UploadCloud,
    X,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTrans } from '@/composables/useTrans';
import {
    importMethod as importHolidaysRoute,
    template as templateRoute,
} from '@/routes/admin/calendar';
import { preview as previewHolidaysRoute } from '@/routes/admin/calendar/import';

export type HolidayAuditRow = {
    row_number: number;
    date: string;
    holiday_name: string;
    description: string;
    day_type: 'HLR';
    is_holiday: boolean;
    is_valid: boolean;
    errors: string[];
};

export type HolidayAuditResult = {
    total: number;
    valid_count: number;
    error_count: number;
    rows: HolidayAuditRow[];
};

const props = defineProps<{
    open: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const { __ } = useTrans();

const fileInputRef = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const isDragging = ref(false);
const isAnalyzing = ref(false);
const isImporting = ref(false);
const uploadError = ref<string | null>(null);
const auditResult = ref<HolidayAuditResult | null>(null);
const rowFilter = ref<'all' | 'valid' | 'error'>('all');

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            resetState();
        }
    },
);

function resetState() {
    selectedFile.value = null;
    auditResult.value = null;
    uploadError.value = null;
    isAnalyzing.value = false;
    isImporting.value = false;
    rowFilter.value = 'all';
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

function handleFileSelect(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        processFile(target.files[0]);
    }
}

function handleDrop(e: DragEvent) {
    isDragging.value = false;
    if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
        processFile(e.dataTransfer.files[0]);
    }
}

async function processFile(file: File) {
    selectedFile.value = file;
    uploadError.value = null;
    isAnalyzing.value = true;
    auditResult.value = null;

    try {
        const text = await file.text();
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const response = await fetch(previewHolidaysRoute.url(), {
            method: 'POST',
            body: JSON.stringify({
                csv_content: text,
            }),
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
        });

        const data = await response.json();

        if (!response.ok) {
            uploadError.value =
                data.message ||
                (data.errors && Object.values(data.errors).flat().join(', ')) ||
                __('Gagal memproses file CSV.');
        } else {
            auditResult.value = data;
        }
    } catch (err: unknown) {
        uploadError.value =
            err instanceof Error
                ? err.message
                : __('Terjadi kesalahan saat mengunggah file.');
    } finally {
        isAnalyzing.value = false;
    }
}

function handleConfirmImport() {
    if (!auditResult.value || auditResult.value.valid_count === 0) {
        return;
    }

    const validRows = auditResult.value.rows.filter((r) => r.is_valid);
    isImporting.value = true;

    router.post(
        importHolidaysRoute.url(),
        {
            rows: validRows,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isImporting.value = false;
                emit('update:open', false);
                emit('success');
            },
            onError: (errors) => {
                isImporting.value = false;
                uploadError.value =
                    Object.values(errors)[0] ||
                    __('Gagal mengimport hari libur nasional.');
            },
        },
    );
}

const filteredRows = computed(() => {
    if (!auditResult.value) return [];
    if (rowFilter.value === 'valid') {
        return auditResult.value.rows.filter((r) => r.is_valid);
    }
    if (rowFilter.value === 'error') {
        return auditResult.value.rows.filter((r) => !r.is_valid);
    }
    return auditResult.value.rows;
});

function handleClose() {
    emit('update:open', false);
}
</script>

<template>
    <Dialog
        :open="open"
        @update:open="(val: boolean) => emit('update:open', val)"
    >
        <DialogContent
            class="sm:max-w-3xl"
            data-test="calendar-holiday-import-dialog"
        >
            <DialogHeader class="border-border border-b pb-4 text-left">
                <div class="flex items-center gap-2">
                    <Calendar class="text-primary size-5" />
                    <DialogTitle class="text-foreground text-lg font-bold">
                        {{ __('Import Hari Libur Nasional (CSV)') }}
                    </DialogTitle>
                </div>
                <DialogDescription class="text-muted-foreground text-xs">
                    {{
                        __(
                            'Unggah file CSV berisi daftar hari libur nasional untuk memperbarui kalender operasional secara massal.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <div class="max-h-[68vh] space-y-4 overflow-y-auto py-2 text-xs">
                <!-- Template Info & Download Banner -->
                <div
                    class="border-border/80 bg-muted/30 flex flex-col items-start justify-between gap-3 rounded-lg border p-3 sm:flex-row sm:items-center"
                >
                    <div class="flex items-center gap-2.5">
                        <FileSpreadsheet class="text-primary size-5 shrink-0" />
                        <div>
                            <div class="text-foreground font-semibold">
                                {{ __('Standard CSV Template Format') }}
                            </div>
                            <div
                                class="text-muted-foreground font-mono text-[11px]"
                            >
                                {{
                                    __(
                                        'Columns: date (YYYY-MM-DD), holiday_name, description',
                                    )
                                }}
                            </div>
                        </div>
                    </div>

                    <a
                        :href="templateRoute.url()"
                        download="national_holidays_template.csv"
                        class="border-input hover:bg-muted text-foreground inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium transition-colors"
                        data-test="btn-download-calendar-template"
                    >
                        <Download class="size-3.5" />
                        <span>{{ __('Download Template') }}</span>
                    </a>
                </div>

                <!-- Upload Drag & Drop Zone -->
                <div v-if="!auditResult && !isAnalyzing" class="space-y-3">
                    <div
                        :class="[
                            isDragging
                                ? 'border-primary bg-primary/5'
                                : 'border-border/80 hover:border-primary/50 hover:bg-muted/10',
                            'flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-8 text-center transition-all',
                        ]"
                        data-test="dropzone-calendar-csv"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="handleDrop"
                        @click="fileInputRef?.click()"
                    >
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept=".csv,text/csv,text/plain"
                            class="hidden"
                            @change="handleFileSelect"
                        />
                        <div class="bg-primary/10 mb-3 rounded-full p-3">
                            <UploadCloud class="text-primary size-6" />
                        </div>
                        <p class="text-foreground text-sm font-semibold">
                            {{
                                __(
                                    'Drag and drop your CSV file here, or browse',
                                )
                            }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{
                                __(
                                    'Supports UTF-8 encoded .csv files up to 5MB',
                                )
                            }}
                        </p>
                    </div>

                    <!-- Upload Error Banner -->
                    <div
                        v-if="uploadError"
                        class="border-destructive/30 bg-destructive/10 text-destructive flex items-center justify-between rounded-lg border p-3"
                    >
                        <div class="flex items-center gap-2">
                            <AlertCircle class="size-4 shrink-0" />
                            <span>{{ uploadError }}</span>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="size-6 p-0"
                            @click="uploadError = null"
                        >
                            <X class="size-3.5" />
                        </Button>
                    </div>
                </div>

                <!-- In-Memory Analyzing Indicator -->
                <div
                    v-if="isAnalyzing"
                    class="border-border/60 bg-muted/20 flex flex-col items-center justify-center rounded-xl border py-12 text-center"
                >
                    <RefreshCw class="text-primary mb-3 size-8 animate-spin" />
                    <p class="text-foreground text-sm font-semibold">
                        {{
                            __('Memeriksa data CSV secara instan di memori...')
                        }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-xs">
                        {{
                            __(
                                'Memvalidasi format tanggal, keunikan, dan nama hari libur.',
                            )
                        }}
                    </p>
                </div>

                <!-- Pre-Commit Audit Preview Table (Stage 2) -->
                <div v-if="auditResult && !isAnalyzing" class="space-y-4">
                    <!-- Metrics Summary Strip -->
                    <div class="grid grid-cols-3 gap-3">
                        <div
                            class="border-border/60 bg-card rounded-lg border p-3 shadow-xs"
                        >
                            <div
                                class="text-muted-foreground text-[11px] font-medium"
                            >
                                {{ __('Total Rows') }}
                            </div>
                            <div class="text-foreground text-xl font-bold">
                                {{ auditResult.total }}
                            </div>
                        </div>

                        <div
                            class="rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-3 shadow-xs"
                        >
                            <div
                                class="text-[11px] font-medium text-emerald-700 dark:text-emerald-400"
                            >
                                {{ __('Valid Rows') }}
                            </div>
                            <div
                                class="text-xl font-bold text-emerald-600 dark:text-emerald-300"
                                data-test="metric-valid-rows"
                            >
                                {{ auditResult.valid_count }}
                            </div>
                        </div>

                        <div
                            :class="[
                                auditResult.error_count > 0
                                    ? 'border-rose-500/30 bg-rose-500/5 text-rose-700 dark:text-rose-400'
                                    : 'border-border/60 bg-card text-muted-foreground',
                                'rounded-lg border p-3 shadow-xs',
                            ]"
                        >
                            <div class="text-[11px] font-medium">
                                {{ __('Error Rows') }}
                            </div>
                            <div
                                class="text-xl font-bold"
                                data-test="metric-error-rows"
                            >
                                {{ auditResult.error_count }}
                            </div>
                        </div>
                    </div>

                    <!-- Filter & Re-upload Controls -->
                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-1.5">
                            <Button
                                type="button"
                                size="sm"
                                :variant="
                                    rowFilter === 'all' ? 'secondary' : 'ghost'
                                "
                                class="h-7 text-xs"
                                @click="rowFilter = 'all'"
                            >
                                {{ __('Semua') }} ({{ auditResult.total }})
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                :variant="
                                    rowFilter === 'valid'
                                        ? 'secondary'
                                        : 'ghost'
                                "
                                class="h-7 text-xs"
                                @click="rowFilter = 'valid'"
                            >
                                {{ __('Valid') }} ({{
                                    auditResult.valid_count
                                }})
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                :variant="
                                    rowFilter === 'error'
                                        ? 'secondary'
                                        : 'ghost'
                                "
                                class="h-7 text-xs text-rose-600"
                                @click="rowFilter = 'error'"
                            >
                                {{ __('Bermasalah') }} ({{
                                    auditResult.error_count
                                }})
                            </Button>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="h-7 gap-1 text-xs"
                            @click="resetState"
                        >
                            <RefreshCw class="size-3" />
                            <span>{{ __('Pilih File Lain') }}</span>
                        </Button>
                    </div>

                    <!-- Warning if errors exist -->
                    <div
                        v-if="auditResult.error_count > 0"
                        class="flex items-center gap-2 rounded-lg border border-amber-500/20 bg-amber-500/10 p-2.5 text-xs text-amber-900 dark:text-amber-200"
                    >
                        <AlertTriangle
                            class="size-4 shrink-0 text-amber-600 dark:text-amber-400"
                        />
                        <span>
                            {{
                                __(
                                    'Only :count valid row(s) will be imported. Rows with errors will be skipped.',
                                    { count: auditResult.valid_count },
                                )
                            }}
                        </span>
                    </div>

                    <!-- Table Preview -->
                    <div
                        class="border-border/80 max-h-56 overflow-y-auto rounded-md border"
                    >
                        <table class="w-full text-left text-xs">
                            <thead
                                class="bg-muted/50 text-muted-foreground sticky top-0 border-b font-medium"
                            >
                                <tr>
                                    <th class="px-3 py-2">
                                        {{ __('Row') }}
                                    </th>
                                    <th class="px-3 py-2">
                                        {{ __('Date') }}
                                    </th>
                                    <th class="px-3 py-2">
                                        {{ __('Holiday Name') }}
                                    </th>
                                    <th class="px-3 py-2">
                                        {{ __('Description') }}
                                    </th>
                                    <th class="px-3 py-2 text-right">
                                        {{ __('Status') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-border/60 divide-y">
                                <tr
                                    v-for="row in filteredRows"
                                    :key="row.row_number"
                                    :class="[
                                        row.is_valid
                                            ? 'hover:bg-muted/30'
                                            : 'bg-rose-500/5 hover:bg-rose-500/10',
                                        'transition-colors',
                                    ]"
                                    :data-test="`audit-row-${row.row_number}`"
                                >
                                    <td
                                        class="text-muted-foreground px-3 py-2 font-mono"
                                    >
                                        #{{ row.row_number }}
                                    </td>
                                    <td class="px-3 py-2 font-mono font-medium">
                                        {{ row.date }}
                                    </td>
                                    <td class="px-3 py-2 font-medium">
                                        {{ row.holiday_name }}
                                    </td>
                                    <td class="text-muted-foreground px-3 py-2">
                                        {{ row.description }}
                                    </td>
                                    <td class="px-3 py-2 text-right">
                                        <div
                                            v-if="row.is_valid"
                                            class="inline-flex items-center gap-1 font-semibold text-emerald-600 dark:text-emerald-400"
                                        >
                                            <CheckCircle2 class="size-3.5" />
                                            <span>{{ __('Valid') }}</span>
                                        </div>
                                        <div
                                            v-else
                                            class="flex flex-col items-end gap-1 text-rose-600"
                                        >
                                            <div
                                                class="inline-flex items-center gap-1 font-semibold"
                                            >
                                                <XCircle class="size-3.5" />
                                                <span>{{ __('Invalid') }}</span>
                                            </div>
                                            <span
                                                v-for="(
                                                    err, eIdx
                                                ) in row.errors"
                                                :key="eIdx"
                                                class="rounded bg-rose-500/10 px-1.5 py-0.5 text-[10px] text-rose-700 dark:text-rose-300"
                                            >
                                                {{ err }}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <DialogFooter class="border-border border-t pt-4">
                <div class="flex w-full items-center justify-end gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="text-xs"
                        :disabled="isImporting"
                        @click="handleClose"
                    >
                        {{ __('Cancel') }}
                    </Button>

                    <Button
                        v-if="auditResult"
                        type="button"
                        size="sm"
                        class="text-xs"
                        :disabled="isImporting || auditResult.valid_count === 0"
                        data-test="btn-confirm-import-holidays"
                        @click="handleConfirmImport"
                    >
                        <RefreshCw
                            v-if="isImporting"
                            class="mr-1.5 size-3.5 animate-spin"
                        />
                        <span v-if="isImporting">
                            {{ __('Mengimport...') }}
                        </span>
                        <span v-else>
                            {{
                                __('Confirm & Import :count Holidays', {
                                    count: auditResult.valid_count,
                                })
                            }}
                        </span>
                    </Button>
                </div>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
