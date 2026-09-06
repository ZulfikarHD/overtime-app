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
import { Spinner } from '@/components/ui/spinner';
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';
import { importMethod, template } from '@/routes/budgets';
import { preview as previewRoute } from '@/routes/budgets/import';

export type BudgetAuditRow = {
    line_number: number;
    section_id: number | null;
    section_code: string;
    section_name: string;
    department_id: number | null;
    department_code: string;
    department_name: string;
    fiscal_year: number;
    fiscal_month: number;
    planned_hours: number;
    planned_cost_idr: number;
    is_valid: boolean;
    errors: string[];
};

export type BudgetAuditResult = {
    total: number;
    valid_count: number;
    error_count: number;
    rows: BudgetAuditRow[];
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
const auditResult = ref<BudgetAuditResult | null>(null);

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
    uploadError.value = null;
    auditResult.value = null;
    isAnalyzing.value = false;
    isImporting.value = false;
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

function handleFileDrop(e: DragEvent) {
    isDragging.value = false;
    if (e.dataTransfer && e.dataTransfer.files.length > 0) {
        processFile(e.dataTransfer.files[0]);
    }
}

async function processFile(file: File) {
    if (
        !file.name.endsWith('.csv') &&
        !file.type.includes('csv') &&
        !file.type.includes('text')
    ) {
        uploadError.value = __('File format must be a CSV file (.csv)');
        return;
    }

    selectedFile.value = file;
    uploadError.value = null;
    isAnalyzing.value = true;
    auditResult.value = null;

    try {
        const formData = new FormData();
        formData.append('file', file);

        const response = await fetch(previewRoute.url(), {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN':
                    (
                        document.querySelector(
                            'meta[name="csrf-token"]',
                        ) as HTMLMetaElement
                    )?.content || '',
            },
        });

        if (!response.ok) {
            const errData = await response.json().catch(() => ({}));
            uploadError.value =
                errData.message ||
                errData.errors?.csv_file?.[0] ||
                __(
                    'Failed to analyze CSV file. Please ensure columns match the template.',
                );
            isAnalyzing.value = false;
            return;
        }

        const data: BudgetAuditResult = await response.json();
        auditResult.value = data;
    } catch (err: any) {
        uploadError.value =
            err.message ||
            __('A network error occurred while reading the file.');
    } finally {
        isAnalyzing.value = false;
    }
}

function confirmImport() {
    if (!auditResult.value || auditResult.value.valid_count === 0) {
        return;
    }

    const validRows = auditResult.value.rows.filter((r) => r.is_valid);
    isImporting.value = true;

    router.post(
        importMethod.url(),
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
                    errors.import ||
                    __('Failed to process budget data import.');
            },
        },
    );
}

function close() {
    emit('update:open', false);
}
</script>

<template>
    <Sheet :open="open" @update:open="(val) => emit('update:open', val)">
        <SheetContent
            side="right"
            class="flex w-full flex-col overflow-y-auto p-0 sm:max-w-2xl"
        >
            <SheetHeader class="border-border border-b px-6 pt-6 pb-4">
                <div class="flex items-center justify-between">
                    <SheetTitle
                        class="text-foreground flex items-center gap-2 text-lg font-semibold"
                    >
                        <FileSpreadsheet class="text-primary size-5" />
                        {{ __('Import Overtime Budgets (CSV)') }}
                    </SheetTitle>
                    <a
                        :href="template.url()"
                        download="overtime_budget_template.csv"
                        class="text-primary inline-flex cursor-pointer items-center gap-1.5 text-xs font-medium hover:underline"
                        data-test="btn-download-template"
                    >
                        <Download class="size-3.5" />
                        {{ __('Download CSV Template') }}
                    </a>
                </div>
                <SheetDescription
                    class="text-muted-foreground text-xs leading-relaxed"
                >
                    {{
                        __(
                            'Upload a CSV file containing section monthly budgets. The system runs an in-memory pre-commit validation before saving.',
                        )
                    }}
                </SheetDescription>
            </SheetHeader>

            <div class="flex-1 space-y-6 p-6">
                <!-- Dropzone Section -->
                <div
                    v-if="!selectedFile"
                    :class="[
                        'flex cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border-2 border-dashed p-8 text-center transition-colors',
                        isDragging
                            ? 'border-primary bg-primary/5'
                            : 'border-border hover:border-primary/60 bg-muted/20',
                    ]"
                    @dragover.prevent="isDragging = true"
                    @dragleave.prevent="isDragging = false"
                    @drop.prevent="handleFileDrop"
                    @click="fileInputRef?.click()"
                    data-test="csv-dropzone"
                >
                    <input
                        ref="fileInputRef"
                        type="file"
                        accept=".csv"
                        class="hidden"
                        @change="handleFileSelect"
                    />
                    <div
                        class="bg-primary/10 text-primary flex size-12 items-center justify-center rounded-full"
                    >
                        <UploadCloud class="size-6" />
                    </div>
                    <div>
                        <p class="text-foreground text-sm font-medium">
                            {{
                                __(
                                    'Click to select file or drag & drop CSV file here',
                                )
                            }}
                        </p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            {{
                                __(
                                    'Required columns: section_code, fiscal_year, fiscal_month, planned_hours',
                                )
                            }}
                        </p>
                    </div>
                </div>

                <!-- Selected File Card with Re-upload Action -->
                <div
                    v-else
                    class="border-border bg-card flex items-center justify-between rounded-lg border p-4"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-md"
                        >
                            <FileSpreadsheet class="size-5" />
                        </div>
                        <div>
                            <div
                                class="text-foreground max-w-xs truncate text-sm font-medium sm:max-w-md"
                            >
                                {{ selectedFile.name }}
                            </div>
                            <div class="text-muted-foreground text-xs">
                                {{ (selectedFile.size / 1024).toFixed(1) }} KB
                            </div>
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        @click="resetState"
                        class="text-destructive hover:bg-destructive/10 h-8 cursor-pointer text-xs"
                    >
                        <X class="mr-1 size-4" />
                        {{ __('Change File') }}
                    </Button>
                </div>

                <!-- Error Alert -->
                <div
                    v-if="uploadError"
                    class="border-destructive/30 bg-destructive/10 text-destructive flex items-start gap-2.5 rounded-lg border p-3.5 text-xs"
                    data-test="upload-error-alert"
                >
                    <AlertCircle class="mt-0.5 size-4 shrink-0" />
                    <span class="leading-relaxed">{{ uploadError }}</span>
                </div>

                <!-- Loading State during Analysis -->
                <div
                    v-if="isAnalyzing"
                    class="flex flex-col items-center justify-center gap-3 py-12"
                >
                    <Spinner class="text-primary size-8" />
                    <p class="text-muted-foreground text-xs">
                        {{
                            __(
                                'Validating budget rows and section relationships...',
                            )
                        }}
                    </p>
                </div>

                <!-- Pre-Commit Audit Results Section -->
                <div
                    v-if="auditResult"
                    class="space-y-4"
                    data-test="audit-result-section"
                >
                    <!-- Summary Badges -->
                    <div class="grid grid-cols-3 gap-3">
                        <div
                            class="border-border bg-muted/40 rounded-lg border p-3 text-center"
                        >
                            <div class="text-muted-foreground text-xs">
                                {{ __('Total Rows') }}
                            </div>
                            <div
                                class="text-foreground font-mono text-lg font-bold"
                            >
                                {{ auditResult.total }}
                            </div>
                        </div>
                        <div
                            class="rounded-lg border border-emerald-500/20 bg-emerald-500/5 p-3 text-center"
                        >
                            <div
                                class="text-xs text-emerald-700 dark:text-emerald-400"
                            >
                                {{ __('Ready to Import') }}
                            </div>
                            <div
                                class="font-mono text-lg font-bold text-emerald-600 dark:text-emerald-400"
                                data-test="valid-count"
                            >
                                {{ auditResult.valid_count }}
                            </div>
                        </div>
                        <div
                            class="rounded-lg border border-rose-500/20 bg-rose-500/5 p-3 text-center"
                        >
                            <div
                                class="text-xs text-rose-700 dark:text-rose-400"
                            >
                                {{ __('Has Errors') }}
                            </div>
                            <div
                                class="font-mono text-lg font-bold text-rose-600 dark:text-rose-400"
                                data-test="error-count"
                            >
                                {{ auditResult.error_count }}
                            </div>
                        </div>
                    </div>

                    <!-- Audit Table -->
                    <div
                        class="border-border overflow-hidden rounded-lg border"
                    >
                        <div class="max-h-80 overflow-y-auto">
                            <table class="w-full text-left text-xs">
                                <thead
                                    class="bg-muted/60 text-muted-foreground border-border sticky top-0 border-b"
                                >
                                    <tr>
                                        <th class="px-3 py-2 font-medium">
                                            {{ __('Line') }}
                                        </th>
                                        <th class="px-3 py-2 font-medium">
                                            {{ __('Section Code') }}
                                        </th>
                                        <th class="px-3 py-2 font-medium">
                                            {{ __('Period') }}
                                        </th>
                                        <th class="px-3 py-2 font-medium">
                                            {{ __('Target Hours') }}
                                        </th>
                                        <th class="px-3 py-2 font-medium">
                                            {{ __('Status & Notes') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-border/60 divide-y">
                                    <tr
                                        v-for="row in auditResult.rows"
                                        :key="row.line_number"
                                        :class="
                                            row.is_valid
                                                ? 'bg-background hover:bg-muted/30'
                                                : 'bg-rose-500/5 hover:bg-rose-500/10'
                                        "
                                    >
                                        <td
                                            class="text-muted-foreground px-3 py-2 font-mono font-medium"
                                        >
                                            #{{ row.line_number }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <div
                                                class="text-foreground font-mono font-semibold"
                                            >
                                                {{ row.section_code }}
                                            </div>
                                            <div
                                                class="text-muted-foreground max-w-[120px] truncate text-[11px]"
                                            >
                                                {{ row.section_name }}
                                            </div>
                                        </td>
                                        <td class="px-3 py-2 font-mono">
                                            {{ row.fiscal_month }}/{{
                                                row.fiscal_year
                                            }}
                                        </td>
                                        <td
                                            class="px-3 py-2 font-mono font-medium"
                                        >
                                            {{ row.planned_hours }}
                                            {{ __('Hours') }}
                                        </td>
                                        <td class="px-3 py-2">
                                            <div
                                                v-if="row.is_valid"
                                                class="inline-flex items-center gap-1 font-medium text-emerald-600 dark:text-emerald-400"
                                            >
                                                <CheckCircle2
                                                    class="size-3.5"
                                                />
                                                <span>{{ __('Valid') }}</span>
                                            </div>
                                            <div v-else class="space-y-1">
                                                <div
                                                    class="inline-flex items-center gap-1 font-medium text-rose-600 dark:text-rose-400"
                                                >
                                                    <XCircle class="size-3.5" />
                                                    <span>{{
                                                        __('Error')
                                                    }}</span>
                                                </div>
                                                <div
                                                    v-for="(
                                                        err, i
                                                    ) in row.errors"
                                                    :key="i"
                                                    class="text-[11px] leading-tight text-rose-600 dark:text-rose-400"
                                                >
                                                    {{ err }}
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <SheetFooter
                class="border-border bg-background mt-auto flex items-center justify-end gap-2 border-t p-6"
            >
                <Button
                    type="button"
                    variant="outline"
                    @click="close"
                    class="cursor-pointer"
                >
                    {{ __('Cancel') }}
                </Button>
                <Button
                    type="button"
                    :disabled="
                        isImporting ||
                        !auditResult ||
                        auditResult.valid_count === 0
                    "
                    @click="confirmImport"
                    class="cursor-pointer"
                    data-test="btn-confirm-import"
                >
                    <Spinner v-if="isImporting" class="mr-2 size-4" />
                    <span>
                        {{
                            auditResult
                                ? __('Import :count Valid Records', {
                                      count: auditResult.valid_count,
                                  })
                                : __('Confirm & Run Import')
                        }}
                    </span>
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
