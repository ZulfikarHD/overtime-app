<script setup lang="ts">
import {
    AlertCircle,
    CheckCircle2,
    Clock,
    FileEdit,
    Loader2,
    Save,
} from '@lucide/vue';
import { ref, watch } from 'vue';
import { toast } from 'vue-sonner';
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
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import { status as actionItemStatusRoute } from '@/routes/analytics/action-items';

export interface ActionItemTarget {
    id: string;
    priority: 'high' | 'medium' | 'low';
    priority_label: string;
    action_item: string;
    department: string;
    department_id: number | null;
    impact: string;
    deadline: string;
    status: 'pending' | 'in_progress' | 'resolved';
    status_label: string;
    resolution_note: string | null;
    updated_at: string | null;
    updated_by_user_id: number | null;
}

const props = defineProps<{
    open: boolean;
    actionItem: ActionItemTarget | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (
        e: 'updated',
        payload: {
            id: string;
            status: 'pending' | 'in_progress' | 'resolved';
            resolution_note: string | null;
            updated_at: string;
        },
    ): void;
}>();

const { __ } = useTrans();

const selectedStatus = ref<'pending' | 'in_progress' | 'resolved'>('pending');
const resolutionNote = ref<string>('');
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);

watch(
    () => props.actionItem,
    (item) => {
        if (item) {
            selectedStatus.value = item.status || 'pending';
            resolutionNote.value = item.resolution_note || '';
            errorMessage.value = null;
        }
    },
    { immediate: true },
);

async function handleSaveStatus() {
    if (!props.actionItem) {
        return;
    }

    isSubmitting.value = true;
    errorMessage.value = null;

    try {
        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const url = actionItemStatusRoute.url({ id: props.actionItem.id });
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({
                status: selectedStatus.value,
                resolution_note: resolutionNote.value.trim() || null,
            }),
        });

        if (!res.ok) {
            const errJson = await res.json().catch(() => null);
            throw new Error(
                errJson?.message ??
                    __(
                        'Gagal memperbarui status tindakan manajemen. Coba beberapa saat lagi.',
                    ),
            );
        }

        const data = await res.json();
        const updatedItem = data.action_item || {
            id: props.actionItem.id,
            status: selectedStatus.value,
            resolution_note: resolutionNote.value.trim() || null,
            updated_at: new Date().toISOString(),
        };

        toast.success(__('Status tindakan manajemen berhasil diperbarui.'));

        emit('updated', {
            id: props.actionItem.id,
            status: selectedStatus.value,
            resolution_note: resolutionNote.value.trim() || null,
            updated_at: updatedItem.updated_at,
        });

        emit('update:open', false);
    } catch (err: unknown) {
        const error = err as Error;
        errorMessage.value =
            error?.message ??
            __('Terjadi kesalahan saat menyimpan perubahan status.');
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <Dialog
        :open="open"
        @update:open="(val: boolean) => emit('update:open', val)"
    >
        <DialogContent class="sm:max-w-md" data-test="action-item-status-modal">
            <DialogHeader>
                <DialogTitle
                    class="flex items-center gap-2 text-base font-bold"
                >
                    <FileEdit class="size-4 text-[#cc0000]" />
                    <span>{{ __('Perbarui Status Tindakan Manajemen') }}</span>
                </DialogTitle>
                <DialogDescription class="text-xs text-slate-500">
                    {{
                        __(
                            'Perubahan status dan catatan resolusi akan dicatat dalam audit trail akun Anda.',
                        )
                    }}
                </DialogDescription>
            </DialogHeader>

            <!-- Action Item Context Card -->
            <div
                v-if="actionItem"
                class="rounded-lg border border-slate-200 bg-slate-50/70 p-3 text-xs dark:border-slate-800 dark:bg-slate-900/60"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="font-mono font-semibold text-slate-500">{{
                        actionItem.id
                    }}</span>
                    <Badge
                        :variant="
                            actionItem.priority === 'high'
                                ? 'destructive'
                                : actionItem.priority === 'medium'
                                  ? 'secondary'
                                  : 'outline'
                        "
                        class="text-[10px]"
                    >
                        {{ actionItem.priority_label }}
                    </Badge>
                </div>
                <h4
                    class="mt-1 font-bold text-slate-900 dark:text-white"
                    data-test="modal-action-title"
                >
                    {{ actionItem.action_item }}
                </h4>
                <div
                    class="mt-1 flex items-center gap-3 text-slate-600 dark:text-slate-300"
                >
                    <span>{{ actionItem.department }}</span>
                    <span>•</span>
                    <span
                        >{{ __('Batas Waktu:') }}
                        <strong class="font-mono tabular-nums">{{
                            actionItem.deadline
                        }}</strong></span
                    >
                </div>
            </div>

            <!-- Error Banner -->
            <div
                v-if="errorMessage"
                class="flex items-center gap-2 rounded-md border border-red-200 bg-red-50 p-2.5 text-xs text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                data-test="status-error-banner"
            >
                <AlertCircle class="size-4 shrink-0 text-[#cc0000]" />
                <span>{{ errorMessage }}</span>
            </div>

            <!-- Status Selector Radio Cards -->
            <div class="space-y-2">
                <Label
                    class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                >
                    {{ __('Pilih Status Target') }}
                </Label>

                <div class="grid gap-2">
                    <!-- Option 1: Tertunda (Pending) -->
                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-all"
                        :class="
                            selectedStatus === 'pending'
                                ? 'border-slate-400 bg-slate-100/60 dark:border-slate-600 dark:bg-slate-800/60'
                                : 'border-slate-200 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900/40'
                        "
                        data-test="status-radio-pending"
                    >
                        <input
                            v-model="selectedStatus"
                            type="radio"
                            value="pending"
                            name="action_status"
                            class="mt-0.5 text-[#cc0000] focus:ring-[#cc0000]"
                            data-test="radio-pending"
                        />
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span
                                    class="text-xs font-bold text-slate-800 dark:text-slate-200"
                                >
                                    {{ __('Tertunda (Pending)') }}
                                </span>
                                <Badge
                                    variant="outline"
                                    class="border-slate-300 bg-slate-100 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                >
                                    {{ __('Menunggu') }}
                                </Badge>
                            </div>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                {{
                                    __(
                                        'Tindakan belum ditindaklanjuti atau masih menunggu penugasan supervisor.',
                                    )
                                }}
                            </p>
                        </div>
                    </label>

                    <!-- Option 2: Dalam Pengerjaan (In Progress) -->
                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-all"
                        :class="
                            selectedStatus === 'in_progress'
                                ? 'border-amber-400 bg-amber-50/60 dark:border-amber-600 dark:bg-amber-950/30'
                                : 'border-slate-200 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900/40'
                        "
                        data-test="status-radio-in_progress"
                    >
                        <input
                            v-model="selectedStatus"
                            type="radio"
                            value="in_progress"
                            name="action_status"
                            class="mt-0.5 text-amber-600 focus:ring-amber-500"
                            data-test="radio-in-progress"
                        />
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span
                                    class="text-xs font-bold text-amber-900 dark:text-amber-200"
                                >
                                    {{ __('Dalam Pengerjaan (In Progress)') }}
                                </span>
                                <Badge
                                    class="border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
                                >
                                    {{ __('Aktif') }}
                                </Badge>
                            </div>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                {{
                                    __(
                                        'Penyesuaian kuota, evaluasi rotasi shift, atau peninjauan sedang berjalan.',
                                    )
                                }}
                            </p>
                        </div>
                    </label>

                    <!-- Option 3: Selesai (Resolved) -->
                    <label
                        class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-all"
                        :class="
                            selectedStatus === 'resolved'
                                ? 'border-emerald-400 bg-emerald-50/60 dark:border-emerald-600 dark:bg-emerald-950/30'
                                : 'border-slate-200 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-900/40'
                        "
                        data-test="status-radio-resolved"
                    >
                        <input
                            v-model="selectedStatus"
                            type="radio"
                            value="resolved"
                            name="action_status"
                            class="mt-0.5 text-emerald-600 focus:ring-emerald-500"
                            data-test="radio-resolved"
                        />
                        <div class="flex-1">
                            <div class="flex items-center gap-2">
                                <span
                                    class="text-xs font-bold text-emerald-900 dark:text-emerald-200"
                                >
                                    {{ __('Selesai (Resolved)') }}
                                </span>
                                <Badge
                                    class="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
                                >
                                    {{ __('Selesai') }}
                                </Badge>
                            </div>
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                {{
                                    __(
                                        'Mitigasi selesai diterapkan dan deviasi telah terkendali.',
                                    )
                                }}
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Optional Resolution Note -->
            <div class="space-y-1.5">
                <Label
                    for="resolution-note"
                    class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                >
                    {{ __('Catatan Penyelesaian / Resolusi (Opsional)') }}
                </Label>
                <textarea
                    id="resolution-note"
                    v-model="resolutionNote"
                    rows="3"
                    class="w-full rounded-md border border-slate-300 bg-white p-2.5 text-xs text-slate-900 shadow-2xs focus:border-[#cc0000] focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                    :placeholder="
                        __(
                            'Tuliskan langkah mitigasi spesifik, nomor SPKL revisi, atau kesepakatan manajerial...',
                        )
                    "
                    maxlength="1000"
                    data-test="textarea-resolution-note"
                ></textarea>
            </div>

            <DialogFooter
                class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="text-xs"
                    :disabled="isSubmitting"
                    data-test="btn-cancel-status-modal"
                    @click="emit('update:open', false)"
                >
                    {{ __('Batal') }}
                </Button>
                <Button
                    type="button"
                    class="bg-[#cc0000] text-xs text-white hover:bg-[#b30000]"
                    :disabled="isSubmitting"
                    data-test="btn-submit-status-update"
                    @click="handleSaveStatus"
                >
                    <Loader2
                        v-if="isSubmitting"
                        class="mr-1.5 size-3.5 animate-spin"
                    />
                    <Save v-else class="mr-1.5 size-3.5" />
                    <span>{{
                        isSubmitting
                            ? __('Menyimpan...')
                            : __('Simpan Perubahan')
                    }}</span>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
