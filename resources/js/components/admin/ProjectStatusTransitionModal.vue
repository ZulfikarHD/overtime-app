<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    PauseCircle,
    PlayCircle,
    ShieldAlert,
    XCircle,
} from '@lucide/vue';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import capexProjectsRoute from '@/routes/admin/capex-projects';

export interface CapexProjectTransitionTarget {
    id: number;
    project_code: string;
    name: string;
    status: 'PLANNING' | 'ACTIVE' | 'ON_HOLD' | 'COMPLETED' | 'CLOSED';
}

const props = defineProps<{
    open: boolean;
    project: CapexProjectTransitionTarget | null;
    initialTargetStatus?:
        | 'PLANNING'
        | 'ACTIVE'
        | 'ON_HOLD'
        | 'COMPLETED'
        | 'CLOSED'
        | null;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const { __ } = useTrans();

const form = useForm({
    status: '' as
        | 'PLANNING'
        | 'ACTIVE'
        | 'ON_HOLD'
        | 'COMPLETED'
        | 'CLOSED'
        | '',
    notes: '',
});

// Allowed status transitions mapping
const allowedTransitionsMap: Record<
    string,
    Array<{
        status: 'PLANNING' | 'ACTIVE' | 'ON_HOLD' | 'COMPLETED' | 'CLOSED';
        label: string;
        desc: string;
    }>
> = {
    PLANNING: [
        {
            status: 'ACTIVE',
            label: 'Aktifkan Proyek (ACTIVE)',
            desc: 'Proyek akan dapat dipilih pada formulir lembur shift teknisi & operator.',
        },
    ],
    ACTIVE: [
        {
            status: 'ON_HOLD',
            label: 'Tunda Sementara (ON_HOLD)',
            desc: 'Pengajuan jam lembur proyek dihentikan sementara waktu.',
        },
        {
            status: 'COMPLETED',
            label: 'Tandai Selesai (COMPLETED)',
            desc: 'Pekerjaan fisik proyek selesai. Pengajuan lembur baru dinonaktifkan.',
        },
    ],
    ON_HOLD: [
        {
            status: 'ACTIVE',
            label: 'Lanjutkan Proyek (ACTIVE)',
            desc: 'Mengaktifkan kembali pencatatan lembur proyek.',
        },
        {
            status: 'COMPLETED',
            label: 'Tandai Selesai (COMPLETED)',
            desc: 'Pekerjaan fisik proyek selesai.',
        },
    ],
    COMPLETED: [
        {
            status: 'CLOSED',
            label: 'Tutup Proyek Permanen (CLOSED)',
            desc: 'Mengunci proyek secara permanen untuk audit keuangan dan jadwal depresiasi aset.',
        },
    ],
    CLOSED: [],
};

const availableTransitions = computed(() => {
    if (!props.project) return [];
    return allowedTransitionsMap[props.project.status] ?? [];
});

watch(
    [() => props.project, () => props.open],
    ([proj, isOpen]) => {
        if (proj && isOpen) {
            const transitions = allowedTransitionsMap[proj.status] ?? [];
            if (
                props.initialTargetStatus &&
                transitions.some((t) => t.status === props.initialTargetStatus)
            ) {
                form.status = props.initialTargetStatus;
            } else {
                form.status =
                    transitions.length > 0 ? transitions[0].status : '';
            }
            form.notes = '';
            form.clearErrors();
        }
    },
    { immediate: true },
);

function handleClose() {
    emit('update:open', false);
}

function submit() {
    if (!props.project || !form.status) return;

    form.patch(
        capexProjectsRoute.status.update.url({
            capex_project: props.project.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                emit('success');
                handleClose();
            },
        },
    );
}

function getStatusBadgeClass(status: string) {
    switch (status) {
        case 'PLANNING':
            return 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-800 dark:text-slate-300';
        case 'ACTIVE':
            return 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300';
        case 'ON_HOLD':
            return 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300';
        case 'COMPLETED':
            return 'bg-sky-50 text-sky-700 border-sky-300 dark:bg-sky-950/60 dark:text-sky-300';
        case 'CLOSED':
            return 'bg-zinc-100 text-zinc-600 border-zinc-300 dark:bg-zinc-800 dark:text-zinc-400';
        default:
            return 'bg-slate-100 text-slate-700';
    }
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent
            class="bg-background overflow-hidden p-0 sm:max-w-lg"
            data-test="project-status-modal"
        >
            <div class="border-border bg-card border-b p-6">
                <DialogHeader>
                    <DialogTitle class="text-foreground text-lg font-bold">
                        {{ __('Ubah Status Proyek CapEx') }}
                    </DialogTitle>
                    <DialogDescription class="text-muted-foreground text-xs">
                        <span class="font-mono font-semibold">{{
                            props.project?.project_code
                        }}</span>
                        — {{ props.project?.name }}
                    </DialogDescription>
                </DialogHeader>

                <div class="mt-3 flex items-center gap-2">
                    <span class="text-muted-foreground text-xs">{{
                        __('Status Saat Ini:')
                    }}</span>
                    <Badge
                        variant="outline"
                        :class="
                            getStatusBadgeClass(props.project?.status ?? '')
                        "
                        class="text-xs font-semibold uppercase"
                    >
                        {{ props.project?.status }}
                    </Badge>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-5 p-6">
                <!-- If CLOSED: Warning that project cannot be transitioned -->
                <div
                    v-if="props.project?.status === 'CLOSED'"
                    class="border-border bg-muted/40 space-y-2 rounded-lg border p-4 text-center"
                >
                    <ShieldAlert class="mx-auto size-8 text-zinc-500" />
                    <p class="text-foreground text-sm font-semibold">
                        {{ __('Proyek Telah Ditutup (CLOSED)') }}
                    </p>
                    <p class="text-muted-foreground text-xs">
                        {{
                            __(
                                'Proyek ini telah dikunci permanen untuk audit keuangan. Tidak ada transisi status lanjutan yang diizinkan.',
                            )
                        }}
                    </p>
                </div>

                <!-- Radio Group of Allowed Transitions -->
                <div v-else class="space-y-3">
                    <Label class="text-foreground text-xs font-semibold">
                        {{ __('Pilih Status Tujuan') }}
                        <span class="text-[#cc0000]">*</span>
                    </Label>

                    <div class="space-y-2">
                        <label
                            v-for="item in availableTransitions"
                            :key="item.status"
                            class="flex cursor-pointer items-start gap-3 rounded-lg border p-3 transition-all"
                            :class="[
                                form.status === item.status
                                    ? 'border-[#cc0000] bg-red-50/20 ring-1 ring-[#cc0000] dark:bg-red-950/20'
                                    : 'border-border hover:bg-muted/40',
                            ]"
                            :data-test="`radio-status-${item.status.toLowerCase()}`"
                        >
                            <input
                                type="radio"
                                name="target_status"
                                :value="item.status"
                                v-model="form.status"
                                class="mt-0.5 text-[#cc0000] focus:ring-[#cc0000]"
                            />
                            <div class="space-y-1">
                                <div
                                    class="text-foreground flex items-center gap-2 text-sm font-semibold"
                                >
                                    {{ __(item.label) }}
                                    <Badge
                                        variant="outline"
                                        :class="
                                            getStatusBadgeClass(item.status)
                                        "
                                        class="text-[10px]"
                                    >
                                        {{ item.status }}
                                    </Badge>
                                </div>
                                <p class="text-muted-foreground text-xs">
                                    {{ __(item.desc) }}
                                </p>
                            </div>
                        </label>
                    </div>

                    <InputError :message="form.errors.status" />
                </div>

                <!-- Critical Warning Callout if transitioning to CLOSED -->
                <div
                    v-if="form.status === 'CLOSED'"
                    class="space-y-1 rounded-lg border border-red-300 bg-red-50 p-3.5 text-xs text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                    data-test="callout-closed-warning"
                >
                    <div class="flex items-center gap-1.5 font-bold">
                        <AlertTriangle class="size-4 shrink-0 text-[#cc0000]" />
                        <span>{{
                            __('Peringatan Audit Akuntansi & Pajak')
                        }}</span>
                    </div>
                    <p class="leading-relaxed">
                        {{
                            __(
                                'Menutup proyek (CLOSED) akan mengunci proyek secara permanen dari pengajuan lembur baru. Tindakan ini tidak dapat dibatalkan demi menjaga keaslian jejak audit PSAK 16.',
                            )
                        }}
                    </p>
                </div>

                <!-- Notes / Reason -->
                <div
                    v-if="props.project?.status !== 'CLOSED'"
                    class="space-y-1.5"
                >
                    <Label for="transition_notes" class="text-xs font-semibold">
                        {{ __('Catatan / Justifikasi Transisi') }}
                        <span
                            class="text-muted-foreground text-[11px] font-normal"
                            >({{ __('Opsional') }})</span
                        >
                    </Label>
                    <textarea
                        id="transition_notes"
                        v-model="form.notes"
                        rows="2"
                        placeholder="e.g. Selesai instalasi fisik robot welding cell, dilanjutkan commisioning..."
                        class="border-input bg-background focus-visible:ring-ring w-full rounded-md border px-3 py-2 text-xs shadow-xs focus-visible:ring-1 focus-visible:outline-hidden"
                        data-test="textarea-transition-notes"
                    ></textarea>
                    <InputError :message="form.errors.notes" />
                </div>

                <DialogFooter
                    class="border-border flex flex-row items-center justify-end gap-2 border-t pt-4"
                >
                    <Button
                        type="button"
                        variant="outline"
                        @click="handleClose"
                        :disabled="form.processing"
                        data-test="btn-cancel-modal"
                    >
                        {{ __('Batal') }}
                    </Button>
                    <Button
                        v-if="props.project?.status !== 'CLOSED'"
                        type="submit"
                        class="bg-[#cc0000] text-white hover:bg-[#b30000]"
                        :disabled="form.processing || !form.status"
                        data-test="btn-confirm-status"
                    >
                        {{
                            form.processing
                                ? __('Memperbarui...')
                                : __('Konfirmasi Perubahan Status')
                        }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
