<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import {
    AlertCircle,
    AlertTriangle,
    Camera,
    CheckCircle2,
    Clock,
    FileText,
    Image as ImageIcon,
    Info,
    Trash2,
    UploadCloud,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
import { formatDateIndo } from '@/lib/formatters';
import { attach as attachSpklRoute } from '@/routes/overtime/submissions/spkl';

export interface SpklTargetSubmission {
    id: number;
    submission_code: string;
    operational_date?: string | null;
    section_name?: string | null;
    total_hours?: number | string | null;
    spkl_document?: {
        id: number;
        status: string;
        due_date?: string | null;
        spkl_number?: string | null;
        file_name?: string | null;
    } | null;
}

const props = defineProps<{
    open: boolean;
    submission: SpklTargetSubmission | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', val: boolean): void;
    (e: 'success'): void;
}>();

const { __ } = useTrans();

const fileInputRef = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const spklNumber = ref('');
const isSubmitting = ref(false);
const formError = ref<string | null>(null);
const isDragOver = ref(false);

watch(
    () => props.submission,
    (val) => {
        selectedFile.value = null;
        formError.value = null;
        if (fileInputRef.value) {
            fileInputRef.value.value = '';
        }
        if (val) {
            spklNumber.value = val.spkl_document?.spkl_number || '';
        } else {
            spklNumber.value = '';
        }
    },
    { immediate: true },
);

const isOverdue = computed(() => {
    if (!props.submission?.spkl_document?.due_date) {
        return false;
    }
    const dueStr = props.submission.spkl_document.due_date;
    const dueDate = new Date(dueStr + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    return (
        dueDate < today && props.submission.spkl_document.status === 'PENDING'
    );
});

const dueCountdownText = computed(() => {
    if (!props.submission?.spkl_document?.due_date) {
        return null;
    }
    const dueStr = props.submission.spkl_document.due_date;
    const dueDate = new Date(dueStr + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const diffMs = dueDate.getTime() - today.getTime();
    const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

    if (diffDays < 0) {
        const overdueDays = Math.abs(diffDays);
        return {
            isOverdue: true,
            label: __('⚠️ SPKL Terlambat: Melewati batas :days hari', {
                days: overdueDays,
            }),
            subtext: __('Jatuh tempo: :date', {
                date: formatDateIndo(dueStr),
            }),
        };
    }

    if (diffDays === 0) {
        return {
            isOverdue: false,
            label: __('⏰ Jatuh tempo hari ini!'),
            subtext: __('Batas waktu: :date', {
                date: formatDateIndo(dueStr),
            }),
        };
    }

    return {
        isOverdue: false,
        label: __('Batas Pengunggahan: :date (:days hari tersisa)', {
            date: formatDateIndo(dueStr),
            days: diffDays,
        }),
        subtext: null,
    };
});

function handleFileDrop(e: DragEvent) {
    isDragOver.value = false;
    if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
        validateAndSetFile(e.dataTransfer.files[0]);
    }
}

function handleFileInputChange(e: Event) {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        validateAndSetFile(target.files[0]);
    }
}

function validateAndSetFile(file: File) {
    formError.value = null;
    const maxBytes = 3 * 1024 * 1024; // 3 MB
    const allowedTypes = [
        'application/pdf',
        'image/jpeg',
        'image/jpg',
        'image/png',
    ];

    if (!allowedTypes.includes(file.type)) {
        formError.value = __('Format berkas harus berupa PDF, JPEG, atau PNG.');
        return;
    }

    if (file.size > maxBytes) {
        formError.value = __(
            'Ukuran file maksimal 3 MB. Silakan gunakan format PDF atau kompresi foto.',
        );
        return;
    }

    selectedFile.value = file;
}

function removeSelectedFile() {
    selectedFile.value = null;
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }
    if (bytes < 1024 * 1024) {
        return `${(bytes / 1024).toFixed(1)} KB`;
    }
    return `${(bytes / (1024 * 1024)).toFixed(2)} MB`;
}

function submitSpkl() {
    if (!props.submission) {
        return;
    }

    if (!selectedFile.value && !spklNumber.value.trim()) {
        formError.value = __(
            'Lampirkan berkas dokumen SPKL atau masukkan nomor fisik SPKL.',
        );
        return;
    }

    isSubmitting.value = true;
    formError.value = null;

    const payload: Record<string, any> = {};
    if (selectedFile.value) {
        payload.file = selectedFile.value;
    }
    if (spklNumber.value.trim()) {
        payload.spkl_number = spklNumber.value.trim();
    }

    router.post(
        attachSpklRoute.url({ submission: props.submission.id }),
        payload,
        {
            forceFormData: Boolean(selectedFile.value),
            preserveScroll: true,
            onSuccess: () => {
                isSubmitting.value = false;
                emit('update:open', false);
                emit('success');
            },
            onError: (errors) => {
                isSubmitting.value = false;
                formError.value =
                    errors.file ||
                    errors.spkl_number ||
                    Object.values(errors)[0] ||
                    __('Gagal mengunggah dokumen SPKL.');
            },
        },
    );
}
</script>

<template>
    <Sheet :open="open" @update:open="(val) => emit('update:open', val)">
        <SheetContent
            side="right"
            class="flex w-full flex-col justify-between overflow-y-auto sm:max-w-lg"
            data-test="spkl-upload-sheet"
        >
            <div>
                <SheetHeader
                    class="space-y-1 border-b border-slate-200 pb-4 dark:border-slate-800"
                >
                    <div class="flex items-center gap-2">
                        <FileText class="size-5 text-[#cc0000]" />
                        <SheetTitle
                            class="text-lg font-bold text-slate-900 dark:text-white"
                        >
                            {{ __('Lampirkan Dokumen SPKL') }}
                        </SheetTitle>
                    </div>
                    <SheetDescription class="text-xs text-slate-500">
                        {{
                            __(
                                'Surat Perintah Kerja Lembur (SPKL) fisik dapat difoto atau di-scan untuk melengkapi arsip kepatuhan audit.',
                            )
                        }}
                    </SheetDescription>

                    <!-- Linked Submission Pill -->
                    <div
                        v-if="submission"
                        class="mt-3 flex flex-wrap items-center gap-2 rounded-lg border border-slate-200/80 bg-slate-50 p-2.5 text-xs text-slate-700 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300"
                        data-test="linked-submission-info"
                    >
                        <span class="font-semibold text-slate-500">{{
                            __('Pengajuan:')
                        }}</span>
                        <span
                            class="rounded bg-white px-2 py-0.5 font-mono text-xs font-bold text-slate-900 shadow-2xs dark:bg-slate-800 dark:text-white"
                        >
                            {{ submission.submission_code }}
                        </span>
                        <span v-if="submission.section_name"
                            >· {{ submission.section_name }}</span
                        >
                        <span v-if="submission.operational_date"
                            >· {{ submission.operational_date }}</span
                        >
                        <span v-if="submission.total_hours">
                            ·
                            <span
                                class="font-mono font-semibold tabular-nums"
                                >{{
                                    Number(submission.total_hours).toFixed(1)
                                }}</span
                            >
                            {{ __('Jam') }}
                        </span>
                    </div>

                    <!-- SPKL Due Countdown Banner -->
                    <div
                        v-if="dueCountdownText"
                        class="mt-2 rounded-lg border p-2.5 text-xs"
                        :class="
                            dueCountdownText.isOverdue
                                ? 'border-red-200 bg-red-50 text-red-900 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300'
                                : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/40 dark:text-amber-300'
                        "
                        data-test="spkl-countdown-banner"
                    >
                        <div class="flex items-center gap-2 font-bold">
                            <Clock class="size-4 shrink-0" />
                            <span>{{ dueCountdownText.label }}</span>
                        </div>
                        <div
                            v-if="dueCountdownText.subtext"
                            class="mt-0.5 text-[11px] opacity-80"
                        >
                            {{ dueCountdownText.subtext }}
                        </div>
                    </div>
                </SheetHeader>

                <div class="mt-6 space-y-5">
                    <!-- Error Notice -->
                    <div
                        v-if="formError"
                        class="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-800 dark:border-red-900/60 dark:bg-red-950/40 dark:text-red-300"
                        data-test="upload-error-alert"
                    >
                        <AlertCircle class="size-4 shrink-0" />
                        <span>{{ formError }}</span>
                    </div>

                    <!-- File Dropzone -->
                    <div class="space-y-1.5">
                        <Label
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            {{ __('Berkas Scan / Foto SPKL') }}
                            <span class="text-[11px] font-normal text-slate-500"
                                >({{ __('PDF, PNG, JPEG, Maks. 3 MB') }})</span
                            >
                        </Label>

                        <div
                            v-if="!selectedFile"
                            class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed p-6 text-center transition-colors"
                            :class="
                                isDragOver
                                    ? 'border-[#cc0000] bg-red-50/50 dark:bg-red-950/20'
                                    : 'border-slate-300 bg-slate-50/50 hover:border-slate-400 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900/40 dark:hover:bg-slate-900/80'
                            "
                            @dragover.prevent="isDragOver = true"
                            @dragleave.prevent="isDragOver = false"
                            @drop.prevent="handleFileDrop"
                            @click="fileInputRef?.click()"
                            data-test="spkl-dropzone"
                        >
                            <div
                                class="mb-2 flex size-11 items-center justify-center rounded-full bg-red-100 text-[#cc0000] dark:bg-red-950/60 dark:text-red-300"
                            >
                                <UploadCloud class="size-6" />
                            </div>
                            <div
                                class="text-xs font-bold text-slate-800 dark:text-slate-200"
                            >
                                {{
                                    __(
                                        'Tarik file ke sini, atau klik untuk memilih file',
                                    )
                                }}
                            </div>
                            <div class="mt-1 text-[11px] text-slate-500">
                                {{
                                    __(
                                        'Mendukung foto dari kamera smartphone atau dokumen PDF',
                                    )
                                }}
                            </div>

                            <input
                                ref="fileInputRef"
                                type="file"
                                accept=".pdf,image/png,image/jpeg,image/jpg"
                                class="hidden"
                                @change="handleFileInputChange"
                                data-test="spkl-file-input"
                            />
                        </div>

                        <!-- Selected File Preview -->
                        <div
                            v-else
                            class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/70 p-3 dark:border-emerald-900/60 dark:bg-emerald-950/30"
                            data-test="selected-file-preview"
                        >
                            <div
                                class="flex items-center gap-3 overflow-hidden"
                            >
                                <div
                                    class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-200"
                                >
                                    <FileText
                                        v-if="
                                            selectedFile.type ===
                                            'application/pdf'
                                        "
                                        class="size-5"
                                    />
                                    <ImageIcon v-else class="size-5" />
                                </div>
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-xs font-bold text-emerald-950 dark:text-emerald-100"
                                        :title="selectedFile.name"
                                    >
                                        {{ selectedFile.name }}
                                    </p>
                                    <p
                                        class="font-mono text-[11px] text-emerald-700 tabular-nums dark:text-emerald-300"
                                    >
                                        {{ formatFileSize(selectedFile.size) }}
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                @click="removeSelectedFile"
                                class="rounded p-1 text-slate-400 hover:bg-emerald-100 hover:text-red-600 dark:hover:bg-emerald-900/60"
                                :title="__('Hapus file terpilih')"
                                data-test="btn-remove-selected-file"
                            >
                                <X class="size-4" />
                            </button>
                        </div>

                        <!-- Existing File Notice if replacing -->
                        <div
                            v-if="
                                submission?.spkl_document?.file_name &&
                                !selectedFile
                            "
                            class="flex items-center gap-1.5 text-[11px] text-slate-500"
                            data-test="existing-file-notice"
                        >
                            <CheckCircle2
                                class="size-3.5 shrink-0 text-emerald-600"
                            />
                            <span>{{
                                __('Berkas tersimpan saat ini: :name', {
                                    name: submission.spkl_document.file_name,
                                })
                            }}</span>
                        </div>
                    </div>

                    <!-- SPKL Number Input -->
                    <div class="space-y-1.5">
                        <Label
                            for="spkl-number-input"
                            class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            {{ __('Nomor Dokumen Fisik SPKL') }}
                            <span class="text-[11px] font-normal text-slate-500"
                                >({{
                                    __('Opsional jika mengunggah file')
                                }})</span
                            >
                        </Label>
                        <Input
                            id="spkl-number-input"
                            v-model="spklNumber"
                            type="text"
                            :placeholder="__('Contoh: SPKL/PROD/2026/IX/089')"
                            class="font-mono text-xs"
                            data-test="input-spkl-number"
                        />
                        <p class="text-[11px] text-slate-500">
                            {{
                                __(
                                    'Nomor registrasi dokumen fisik yang tercetak pada formulir kertas SPKL.',
                                )
                            }}
                        </p>
                    </div>

                    <!-- Non-Blocking SPKL Guidance Banner -->
                    <div
                        class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-400"
                    >
                        <div class="flex items-start gap-2">
                            <Info
                                class="mt-0.5 size-4 shrink-0 text-slate-500"
                            />
                            <p class="leading-relaxed">
                                <span
                                    class="font-bold text-slate-800 dark:text-slate-200"
                                    >{{
                                        __('Kebijakan Fleksibel (BR-05):')
                                    }}</span
                                >
                                {{
                                    __(
                                        'Pengunggahan dokumen SPKL bersifat fleksibel dan tidak menghambat persetujuan jam lembur awal. Pastikan tanda tangan fisik supervisor terlihat jelas pada foto/scan.',
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Actions -->
            <SheetFooter
                class="mt-8 flex items-center justify-end gap-2 border-t border-slate-200 pt-4 dark:border-slate-800"
            >
                <Button
                    type="button"
                    variant="outline"
                    @click="emit('update:open', false)"
                    :disabled="isSubmitting"
                    data-test="btn-cancel-spkl"
                >
                    {{ __('Batal') }}
                </Button>

                <Button
                    type="button"
                    @click="submitSpkl"
                    :disabled="
                        isSubmitting || (!selectedFile && !spklNumber.trim())
                    "
                    class="bg-[#cc0000] text-xs font-semibold text-white hover:bg-[#b30000] active:scale-95"
                    data-test="btn-submit-spkl"
                >
                    <Spinner v-if="isSubmitting" class="mr-1.5 size-3.5" />
                    <span>{{ __('Unggah & Simpan SPKL') }}</span>
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>
