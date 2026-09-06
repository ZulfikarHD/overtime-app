<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    CheckCircle2,
    Download,
    FileSpreadsheet,
    RefreshCw,
    UploadCloud,
    X,
    XCircle,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';
import employeesRoute from '@/routes/admin/employees';
import { preview as previewRoute } from '@/routes/admin/employees/import';

export type CsvAuditRow = {
    row_number: number;
    npk: string;
    full_name: string;
    department_code: string;
    department_name: string;
    department_id: number | null;
    section_code: string;
    section_name: string;
    section_id: number | null;
    job_position: string;
    hourly_rate: number | null;
    is_valid: boolean;
    errors: string[];
};

export type CsvAuditResult = {
    total: number;
    valid_count: number;
    error_count: number;
    rows: CsvAuditRow[];
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
const auditResult = ref<CsvAuditResult | null>(null);
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

function handleClose() {
    emit('update:open', false);
}

function triggerFileSelect() {
    fileInputRef.value?.click();
}

function onFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        processFile(target.files[0]);
    }
}

function onDrop(event: DragEvent) {
    isDragging.value = false;
    if (event.dataTransfer?.files && event.dataTransfer.files.length > 0) {
        const file = event.dataTransfer.files[0];
        if (
            !file.name.endsWith('.csv') &&
            !file.type.includes('csv') &&
            !file.type.includes('text')
        ) {
            uploadError.value = __('Please upload a valid CSV file.');
            return;
        }
        processFile(file);
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

        const response = await fetch(previewRoute.url(), {
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
                __('Failed to parse CSV file.');
        } else {
            auditResult.value = data;
        }
    } catch (err: unknown) {
        uploadError.value =
            err instanceof Error
                ? err.message
                : __('An error occurred while uploading.');
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
        employeesRoute.import.url(),
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
                uploadError.value = Object.values(errors).flat().join(', ');
            },
            onFinish: () => {
                isImporting.value = false;
            },
        },
    );
}

function getFilteredRows() {
    if (!auditResult.value) {
        return [];
    }
    if (rowFilter.value === 'valid') {
        return auditResult.value.rows.filter((r) => r.is_valid);
    }
    if (rowFilter.value === 'error') {
        return auditResult.value.rows.filter((r) => !r.is_valid);
    }
    return auditResult.value.rows;
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent class="w-full overflow-y-auto sm:max-w-3xl" side="right">
            <SheetHeader>
                <div class="flex items-center justify-between">
                    <div>
                        <SheetTitle class="flex items-center gap-2">
                            <FileSpreadsheet class="text-primary size-5" />
                            <span>{{
                                __('Import Employee Roster (CSV)')
                            }}</span>
                        </SheetTitle>
                        <SheetDescription class="mt-1">
                            {{
                                __(
                                    'Upload a CSV file to bulk-register employees into section rosters with automatic pre-commit audit.',
                                )
                            }}
                        </SheetDescription>
                    </div>
                </div>
            </SheetHeader>

            <div class="space-y-5 py-4">
                <!-- Template Download Card -->
                <div
                    class="border-border/60 bg-muted/30 flex items-center justify-between rounded-lg border p-3 text-xs"
                >
                    <div>
                        <p class="text-foreground font-medium">
                            {{ __('Standard CSV Template Format') }}
                        </p>
                        <p class="text-muted-foreground mt-0.5">
                            {{
                                __(
                                    'Columns: npk, full_name, department_code, section_code, job_position, hourly_rate',
                                )
                            }}
                        </p>
                    </div>
                    <a
                        :href="employeesRoute.template.url()"
                        download="employee_roster_template.csv"
                        class="border-input bg-background hover:bg-accent hover:text-accent-foreground inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-xs font-medium shadow-xs transition-colors"
                        data-test="btn-download-template"
                    >
                        <Download class="size-3.5" />
                        <span>{{ __('Download Template') }}</span>
                    </a>
                </div>

                <!-- Dropzone when no file or analyzing -->
                <div v-if="!auditResult">
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept=".csv,text/csv,text/plain"
                        class="hidden"
                        data-test="input-csv-file"
                        @change="onFileChange"
                    />

                    <div
                        :class="[
                            isDragging
                                ? 'border-primary bg-primary/5 ring-primary/20 ring-2'
                                : 'border-border/80 hover:border-primary/50 hover:bg-muted/30',
                            'relative flex cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed p-8 text-center transition-colors',
                        ]"
                        data-test="csv-dropzone"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="onDrop"
                        @click="triggerFileSelect"
                    >
                        <div
                            v-if="isAnalyzing"
                            class="flex flex-col items-center gap-3"
                        >
                            <RefreshCw
                                class="text-primary size-8 animate-spin"
                            />
                            <p class="text-foreground text-sm font-medium">
                                {{ __('Auditing CSV data in-memory...') }}
                            </p>
                            <p class="text-muted-foreground text-xs">
                                {{
                                    __(
                                        'Validating NPK uniqueness, department codes, and section hierarchies.',
                                    )
                                }}
                            </p>
                        </div>

                        <div v-else class="flex flex-col items-center gap-2">
                            <div
                                class="bg-primary/10 text-primary rounded-full p-3"
                            >
                                <UploadCloud class="size-6" />
                            </div>
                            <h4 class="text-foreground text-sm font-semibold">
                                {{
                                    __(
                                        'Drag and drop your CSV file here, or browse',
                                    )
                                }}
                            </h4>
                            <p class="text-muted-foreground text-xs">
                                {{
                                    __(
                                        'Supports UTF-8 encoded .csv files up to 5MB',
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Error Alert -->
                <div
                    v-if="uploadError"
                    class="flex items-start gap-2.5 rounded-lg border border-red-500/20 bg-red-50 p-4 text-xs text-red-700 dark:border-red-500/30 dark:bg-red-950/40 dark:text-red-300"
                    data-test="csv-error-alert"
                >
                    <AlertCircle
                        class="mt-0.5 size-4 shrink-0 text-red-600 dark:text-red-400"
                    />
                    <div>
                        <p class="font-semibold">
                            {{ __('Validation Error') }}
                        </p>
                        <p class="mt-0.5">{{ uploadError }}</p>
                        <button
                            type="button"
                            class="text-primary mt-2 cursor-pointer font-medium hover:underline"
                            @click="resetState"
                        >
                            {{ __('Try another file') }}
                        </button>
                    </div>
                </div>

                <!-- Pre-Commit Audit Preview Section -->
                <div v-if="auditResult" class="space-y-4">
                    <!-- File Header & Summary Badges -->
                    <div
                        class="border-border/80 bg-card flex flex-col gap-3 rounded-lg border p-3 shadow-xs sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="flex items-center gap-2.5">
                            <FileSpreadsheet
                                class="size-5 text-emerald-600 dark:text-emerald-400"
                            />
                            <div>
                                <span
                                    class="text-foreground text-sm font-medium"
                                >
                                    {{ selectedFile?.name }}
                                </span>
                                <span
                                    class="text-muted-foreground ml-2 text-xs"
                                >
                                    ({{
                                        selectedFile?.size
                                            ? (
                                                  selectedFile.size / 1024
                                              ).toFixed(1)
                                            : 0
                                    }}
                                    KB)
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                class="gap-1.5 text-xs"
                                @click="resetState"
                            >
                                <X class="size-3.5" />
                                <span>{{ __('Select Different File') }}</span>
                            </Button>
                        </div>
                    </div>

                    <!-- Metrics Badges -->
                    <div class="grid grid-cols-3 gap-3">
                        <div
                            :class="[
                                rowFilter === 'all'
                                    ? 'border-primary ring-primary ring-1'
                                    : 'border-border/60',
                                'bg-card cursor-pointer rounded-lg border p-3 shadow-xs transition-colors',
                            ]"
                            @click="rowFilter = 'all'"
                        >
                            <span
                                class="text-muted-foreground text-xs font-medium"
                                >{{ __('Total Rows') }}</span
                            >
                            <div
                                class="text-foreground mt-0.5 text-lg font-bold"
                            >
                                {{ auditResult.total }}
                            </div>
                        </div>

                        <div
                            :class="[
                                rowFilter === 'valid'
                                    ? 'border-emerald-500 ring-1 ring-emerald-500'
                                    : 'border-border/60',
                                'bg-card cursor-pointer rounded-lg border p-3 shadow-xs transition-colors',
                            ]"
                            @click="rowFilter = 'valid'"
                        >
                            <div
                                class="flex items-center gap-1.5 text-xs font-medium text-emerald-700 dark:text-emerald-400"
                            >
                                <CheckCircle2 class="size-3.5" />
                                <span>{{ __('Valid Rows') }}</span>
                            </div>
                            <div
                                class="mt-0.5 text-lg font-bold text-emerald-600 dark:text-emerald-400"
                            >
                                {{ auditResult.valid_count }}
                            </div>
                        </div>

                        <div
                            :class="[
                                rowFilter === 'error'
                                    ? 'border-red-500 ring-1 ring-red-500'
                                    : 'border-border/60',
                                'bg-card cursor-pointer rounded-lg border p-3 shadow-xs transition-colors',
                            ]"
                            @click="rowFilter = 'error'"
                        >
                            <div
                                class="flex items-center gap-1.5 text-xs font-medium text-red-700 dark:text-red-400"
                            >
                                <XCircle class="size-3.5" />
                                <span>{{ __('Error Rows') }}</span>
                            </div>
                            <div
                                class="mt-0.5 text-lg font-bold text-red-600 dark:text-red-400"
                            >
                                {{ auditResult.error_count }}
                            </div>
                        </div>
                    </div>

                    <!-- Warning if partial errors exist -->
                    <div
                        v-if="
                            auditResult.error_count > 0 &&
                            auditResult.valid_count > 0
                        "
                        class="flex items-center gap-2 rounded-md border border-amber-500/20 bg-amber-500/10 p-2.5 text-xs text-amber-700 dark:text-amber-300"
                    >
                        <AlertTriangle class="size-4 shrink-0 text-amber-600" />
                        <span>
                            {{
                                __(
                                    'Only :count valid row(s) will be imported. Rows with errors will be skipped.',
                                    { count: auditResult.valid_count },
                                )
                            }}
                        </span>
                    </div>

                    <!-- Pre-Commit Live Audit Table -->
                    <div
                        class="border-border/80 overflow-hidden rounded-lg border shadow-xs"
                    >
                        <div class="max-h-72 overflow-y-auto">
                            <table class="w-full text-left text-xs">
                                <thead
                                    class="bg-muted/90 text-muted-foreground border-border sticky top-0 border-b font-semibold backdrop-blur-xs"
                                >
                                    <tr>
                                        <th class="w-12 px-3 py-2 text-center">
                                            #
                                        </th>
                                        <th class="px-3 py-2">
                                            {{ __('NPK') }}
                                        </th>
                                        <th class="px-3 py-2">
                                            {{ __('Full Name') }}
                                        </th>
                                        <th class="px-3 py-2">
                                            {{ __('Dept / Section') }}
                                        </th>
                                        <th class="px-3 py-2">
                                            {{ __('Position') }}
                                        </th>
                                        <th class="px-3 py-2">
                                            {{ __('Rate') }}
                                        </th>
                                        <th class="px-3 py-2">
                                            {{ __('Audit Status') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    class="divide-border/60 bg-card divide-y"
                                >
                                    <tr
                                        v-for="row in getFilteredRows()"
                                        :key="row.row_number"
                                        :class="[
                                            row.is_valid
                                                ? 'hover:bg-muted/30'
                                                : 'bg-red-500/5 hover:bg-red-500/10',
                                        ]"
                                    >
                                        <td
                                            class="text-muted-foreground px-3 py-2 text-center font-mono"
                                        >
                                            {{ row.row_number }}
                                        </td>
                                        <td
                                            class="px-3 py-2 font-mono font-medium"
                                        >
                                            {{ row.npk }}
                                        </td>
                                        <td
                                            class="text-foreground px-3 py-2 font-medium"
                                        >
                                            {{ row.full_name }}
                                        </td>
                                        <td
                                            class="text-muted-foreground px-3 py-2"
                                        >
                                            <span
                                                class="text-foreground font-mono"
                                                >{{ row.department_code }}</span
                                            >
                                            <span class="mx-1">/</span>
                                            <span
                                                class="text-foreground font-mono"
                                                >{{ row.section_code }}</span
                                            >
                                        </td>
                                        <td
                                            class="text-muted-foreground px-3 py-2"
                                        >
                                            {{ row.job_position }}
                                        </td>
                                        <td
                                            class="text-muted-foreground px-3 py-2 font-mono"
                                        >
                                            {{
                                                row.hourly_rate !== null
                                                    ? formatRupiah(
                                                          row.hourly_rate,
                                                      )
                                                    : __('Dept Rate')
                                            }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <Badge
                                                v-if="row.is_valid"
                                                variant="outline"
                                                class="border-emerald-500/30 text-[10px] text-emerald-700 dark:text-emerald-300"
                                            >
                                                {{ __('Valid') }}
                                            </Badge>
                                            <div v-else class="space-y-1">
                                                <Badge
                                                    variant="destructive"
                                                    class="text-[10px]"
                                                >
                                                    {{ __('Invalid') }}
                                                </Badge>
                                                <p
                                                    v-for="(
                                                        err, idx
                                                    ) in row.errors"
                                                    :key="idx"
                                                    class="text-[11px] leading-tight font-medium text-red-600 dark:text-red-400"
                                                >
                                                    {{ err }}
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <SheetFooter class="pt-4">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="isImporting"
                    @click="handleClose"
                >
                    {{ __('Cancel') }}
                </Button>
                <Button
                    type="button"
                    :disabled="
                        !auditResult ||
                        auditResult.valid_count === 0 ||
                        isImporting
                    "
                    data-test="btn-confirm-import"
                    @click="handleConfirmImport"
                >
                    <RefreshCw
                        v-if="isImporting"
                        class="mr-2 size-4 animate-spin"
                    />
                    <span>
                        {{
                            isImporting
                                ? __('Importing...')
                                : auditResult
                                  ? __('Confirm & Import :count Employees', {
                                        count: auditResult.valid_count,
                                    })
                                  : __('Confirm & Import')
                        }}
                    </span>
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
