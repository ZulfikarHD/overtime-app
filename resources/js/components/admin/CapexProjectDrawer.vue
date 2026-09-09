<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Calendar, DollarSign, FolderKanban, Info, Lock } from '@lucide/vue';
import { computed, watch } from 'vue';
import InputError from '@/components/InputError.vue';
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
import { useTrans } from '@/composables/useTrans';
import { formatRupiah } from '@/lib/formatters';
import capexProjectsRoute from '@/routes/admin/capex-projects';

export interface DepartmentOption {
    id: number;
    code: string;
    name: string;
}

export interface CapexProjectRecord {
    id: number;
    project_code: string;
    asset_code?: string | null;
    name: string;
    department_id: number;
    allocated_labor_hours: number | string;
    allocated_labor_budget_idr: number | string;
    physical_progress_pct?: number | string;
    status: 'PLANNING' | 'ACTIVE' | 'ON_HOLD' | 'COMPLETED' | 'CLOSED';
    start_date: string;
    target_end_date: string;
    department?: DepartmentOption;
}

const props = defineProps<{
    open: boolean;
    project?: CapexProjectRecord | null;
    departments: DepartmentOption[];
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
    (e: 'success'): void;
}>();

const { __ } = useTrans();

const isEdit = computed(() => !!props.project);

const form = useForm({
    project_code: '',
    asset_code: '',
    name: '',
    department_id: '' as string | number,
    allocated_labor_hours: '' as string | number,
    allocated_labor_budget_idr: '' as string | number,
    start_date: '',
    target_end_date: '',
    status: 'PLANNING' as
        | 'PLANNING'
        | 'ACTIVE'
        | 'ON_HOLD'
        | 'COMPLETED'
        | 'CLOSED',
});

// Format date string to YYYY-MM-DD for standard date input
function toDateInputString(dateStr: string | null | undefined): string {
    if (!dateStr) return '';
    try {
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return String(dateStr).slice(0, 10);
        return d.toISOString().slice(0, 10);
    } catch {
        return String(dateStr).slice(0, 10);
    }
}

watch(
    () => props.project,
    (proj) => {
        if (proj) {
            form.project_code = proj.project_code;
            form.asset_code = proj.asset_code ?? '';
            form.name = proj.name;
            form.department_id = proj.department_id;
            form.allocated_labor_hours = proj.allocated_labor_hours;
            form.allocated_labor_budget_idr = proj.allocated_labor_budget_idr;
            form.start_date = toDateInputString(proj.start_date);
            form.target_end_date = toDateInputString(proj.target_end_date);
            form.status = proj.status;
            form.clearErrors();
        } else {
            form.reset();
            if (props.departments.length === 1) {
                form.department_id = props.departments[0].id;
            }
            form.status = 'PLANNING';
            form.clearErrors();
        }
    },
    { immediate: true },
);

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            form.clearErrors();
        }
    },
);

const isCodeRegexValid = computed(() => {
    if (!form.project_code || isEdit.value) return true;
    return /^CPX-\d{4}-[A-Z0-9]+-\d{3,}$/.test(form.project_code.trim());
});

const formattedBudgetPreview = computed(() => {
    const val = Number(form.allocated_labor_budget_idr);
    if (isNaN(val) || val <= 0) return 'Rp 0';
    return formatRupiah(val, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    });
});

function handleClose() {
    emit('update:open', false);
}

function submit() {
    if (isEdit.value && props.project) {
        // Exclude project_code to strictly respect immutability invariant
        form.transform(() => ({
            asset_code: form.asset_code ? form.asset_code.trim() : null,
            name: form.name.trim(),
            department_id: Number(form.department_id),
            allocated_labor_hours: Number(form.allocated_labor_hours),
            allocated_labor_budget_idr: Number(form.allocated_labor_budget_idr),
            start_date: form.start_date,
            target_end_date: form.target_end_date,
        })).put(
            capexProjectsRoute.update.url({ capex_project: props.project.id }),
            {
                preserveScroll: true,
                onSuccess: () => {
                    emit('success');
                    handleClose();
                },
            },
        );
    } else {
        form.transform((data) => ({
            ...data,
            project_code: data.project_code.trim().toUpperCase(),
            asset_code: data.asset_code
                ? data.asset_code.trim().toUpperCase()
                : null,
            name: data.name.trim(),
            department_id: Number(data.department_id),
            allocated_labor_hours: Number(data.allocated_labor_hours),
            allocated_labor_budget_idr: Number(data.allocated_labor_budget_idr),
        })).post(capexProjectsRoute.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                emit('success');
                handleClose();
            },
        });
    }
}
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent
            side="right"
            class="bg-background flex w-full flex-col overflow-y-auto p-0 sm:max-w-xl"
            data-test="capex-project-drawer"
        >
            <div class="border-border bg-card border-b p-6">
                <SheetHeader>
                    <div
                        class="flex items-center gap-2 text-xs font-semibold tracking-wider text-[#cc0000] uppercase"
                    >
                        <FolderKanban class="size-4" />
                        {{
                            isEdit
                                ? __('Edit Proyek CapEx')
                                : __('Pendaftaran Proyek CapEx Baru')
                        }}
                    </div>
                    <SheetTitle class="text-foreground text-xl font-bold">
                        {{
                            isEdit
                                ? props.project?.project_code +
                                  ' — ' +
                                  props.project?.name
                                : __('Tambah Proyek CapEx')
                        }}
                    </SheetTitle>
                    <SheetDescription class="text-muted-foreground text-xs">
                        {{
                            isEdit
                                ? __(
                                      'Perbarui alokasi jam, anggaran biaya, atau jadwal proyek. Kode proyek bersifat permanen.',
                                  )
                                : __(
                                      'Daftarkan proyek belanja modal baru (CapEx) dengan alokasi jam kerja dan target waktu.',
                                  )
                        }}
                    </SheetDescription>
                </SheetHeader>
            </div>

            <form @submit.prevent="submit" class="flex-1 space-y-6 p-6">
                <!-- Section 1: Identitas Proyek -->
                <div
                    class="border-border bg-card/60 space-y-4 rounded-lg border p-4"
                >
                    <div
                        class="border-border/60 flex items-center justify-between border-b pb-2"
                    >
                        <h4
                            class="text-muted-foreground text-xs font-bold tracking-wider uppercase"
                        >
                            1. {{ __('Identitas Proyek') }}
                        </h4>
                        <span class="text-muted-foreground text-[11px]">{{
                            __('Wajib')
                        }}</span>
                    </div>

                    <!-- Kode Proyek -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <Label
                                for="project_code"
                                class="text-xs font-semibold"
                            >
                                {{ __('Kode Proyek') }}
                                <span class="text-[#cc0000]">*</span>
                            </Label>
                            <span
                                v-if="isEdit"
                                class="inline-flex items-center gap-1 text-[11px] font-medium text-amber-600 dark:text-amber-400"
                            >
                                <Lock class="size-3" />
                                {{ __('Permanen / Terkunci') }}
                            </span>
                        </div>
                        <div class="relative">
                            <Input
                                id="project_code"
                                v-model="form.project_code"
                                :disabled="isEdit"
                                placeholder="CPX-2026-ASSY-001"
                                class="font-mono text-sm uppercase tabular-nums"
                                :class="{
                                    'bg-muted/60 text-muted-foreground cursor-not-allowed':
                                        isEdit,
                                    'border-amber-500 focus-visible:ring-amber-500':
                                        form.project_code &&
                                        !isCodeRegexValid &&
                                        !isEdit,
                                }"
                                data-test="input-project-code"
                            />
                            <Lock
                                v-if="isEdit"
                                class="text-muted-foreground absolute top-1/2 right-3 size-4 -translate-y-1/2"
                            />
                        </div>
                        <p
                            v-if="isEdit"
                            class="text-muted-foreground mt-1 flex items-center gap-1 text-[11px]"
                        >
                            <Info
                                class="text-muted-foreground size-3.5 shrink-0"
                            />
                            {{
                                __(
                                    'Kode proyek bersifat permanen dan tidak dapat diubah setelah dibuat demi kepatuhan audit.',
                                )
                            }}
                        </p>
                        <p
                            v-else-if="form.project_code && !isCodeRegexValid"
                            class="mt-1 text-[11px] text-amber-600 dark:text-amber-400"
                        >
                            {{
                                __(
                                    'Format yang disarankan: CPX-YYYY-DEPT-NNN (misal: CPX-2026-ASSY-001)',
                                )
                            }}
                        </p>
                        <InputError :message="form.errors.project_code" />
                    </div>

                    <!-- Nama Proyek -->
                    <div class="space-y-1.5">
                        <Label for="name" class="text-xs font-semibold">
                            {{ __('Nama Proyek') }}
                            <span class="text-[#cc0000]">*</span>
                        </Label>
                        <Input
                            id="name"
                            v-model="form.name"
                            placeholder="e.g. Pemasangan Lini Robot Welding 2"
                            class="text-sm"
                            data-test="input-project-name"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <!-- Kode Aset Tetap (Fixed Asset Tag) -->
                    <div class="space-y-1.5">
                        <Label for="asset_code" class="text-xs font-semibold">
                            {{ __('Kode Aset Tetap') }}
                            <span
                                class="text-muted-foreground text-[11px] font-normal"
                                >({{ __('Opsional / Akuntansi') }})</span
                            >
                        </Label>
                        <Input
                            id="asset_code"
                            v-model="form.asset_code"
                            placeholder="e.g. AST-8812"
                            class="font-mono text-sm uppercase"
                            data-test="input-asset-code"
                        />
                        <InputError :message="form.errors.asset_code" />
                    </div>
                </div>

                <!-- Section 2: Departemen Pemilik -->
                <div
                    class="border-border bg-card/60 space-y-4 rounded-lg border p-4"
                >
                    <div
                        class="border-border/60 flex items-center justify-between border-b pb-2"
                    >
                        <h4
                            class="text-muted-foreground text-xs font-bold tracking-wider uppercase"
                        >
                            2. {{ __('Departemen Pemilik') }}
                        </h4>
                    </div>

                    <div class="space-y-1.5">
                        <Label
                            for="department_id"
                            class="text-xs font-semibold"
                        >
                            {{ __('Departemen') }}
                            <span class="text-[#cc0000]">*</span>
                        </Label>
                        <select
                            id="department_id"
                            v-model="form.department_id"
                            class="border-input bg-background focus-visible:ring-ring h-9 w-full rounded-md border px-3 py-1 text-sm shadow-xs focus-visible:ring-1 focus-visible:outline-hidden"
                            data-test="select-department"
                        >
                            <option value="" disabled>
                                {{ __('Pilih Departemen') }}
                            </option>
                            <option
                                v-for="dept in departments"
                                :key="dept.id"
                                :value="dept.id"
                            >
                                {{ dept.code }} — {{ dept.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.department_id" />
                    </div>
                </div>

                <!-- Section 3: Alokasi Anggaran Tenaga Kerja -->
                <div
                    class="border-border bg-card/60 space-y-4 rounded-lg border p-4"
                >
                    <div
                        class="border-border/60 flex items-center justify-between border-b pb-2"
                    >
                        <h4
                            class="text-muted-foreground flex items-center gap-1.5 text-xs font-bold tracking-wider uppercase"
                        >
                            <DollarSign
                                class="size-3.5 text-sky-600 dark:text-sky-400"
                            />
                            3. {{ __('Alokasi Anggaran Tenaga Kerja') }}
                        </h4>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Alokasi Jam Lembur -->
                        <div class="space-y-1.5">
                            <Label
                                for="allocated_labor_hours"
                                class="text-xs font-semibold"
                            >
                                {{ __('Alokasi Jam Lembur (Jam)') }}
                                <span class="text-[#cc0000]">*</span>
                            </Label>
                            <Input
                                id="allocated_labor_hours"
                                v-model="form.allocated_labor_hours"
                                type="number"
                                step="0.5"
                                min="0"
                                placeholder="400.0"
                                class="font-mono text-sm tabular-nums"
                                data-test="input-allocated-hours"
                            />
                            <InputError
                                :message="form.errors.allocated_labor_hours"
                            />
                        </div>

                        <!-- Alokasi Anggaran Rupiah -->
                        <div class="space-y-1.5">
                            <Label
                                for="allocated_labor_budget_idr"
                                class="text-xs font-semibold"
                            >
                                {{ __('Alokasi Anggaran (Rp)') }}
                                <span class="text-[#cc0000]">*</span>
                            </Label>
                            <Input
                                id="allocated_labor_budget_idr"
                                v-model="form.allocated_labor_budget_idr"
                                type="number"
                                step="1000"
                                min="0"
                                placeholder="28000000"
                                class="font-mono text-sm tabular-nums"
                                data-test="input-allocated-budget"
                            />
                            <p
                                class="font-mono text-[11px] font-semibold text-sky-700 tabular-nums dark:text-sky-300"
                            >
                                {{ formattedBudgetPreview }}
                            </p>
                            <InputError
                                :message="
                                    form.errors.allocated_labor_budget_idr
                                "
                            />
                        </div>
                    </div>
                </div>

                <!-- Section 4: Jadwal Pengerjaan -->
                <div
                    class="border-border bg-card/60 space-y-4 rounded-lg border p-4"
                >
                    <div
                        class="border-border/60 flex items-center justify-between border-b pb-2"
                    >
                        <h4
                            class="text-muted-foreground flex items-center gap-1.5 text-xs font-bold tracking-wider uppercase"
                        >
                            <Calendar class="text-muted-foreground size-3.5" />
                            4. {{ __('Jadwal Pengerjaan') }}
                        </h4>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <!-- Tanggal Mulai -->
                        <div class="space-y-1.5">
                            <Label
                                for="start_date"
                                class="text-xs font-semibold"
                            >
                                {{ __('Tanggal Mulai') }}
                                <span class="text-[#cc0000]">*</span>
                            </Label>
                            <Input
                                id="start_date"
                                v-model="form.start_date"
                                type="date"
                                class="font-mono text-sm"
                                data-test="input-start-date"
                            />
                            <InputError :message="form.errors.start_date" />
                        </div>

                        <!-- Target Tanggal Selesai -->
                        <div class="space-y-1.5">
                            <Label
                                for="target_end_date"
                                class="text-xs font-semibold"
                            >
                                {{ __('Target Tanggal Selesai') }}
                                <span class="text-[#cc0000]">*</span>
                            </Label>
                            <Input
                                id="target_end_date"
                                v-model="form.target_end_date"
                                type="date"
                                class="font-mono text-sm"
                                data-test="input-target-end-date"
                            />
                            <InputError
                                :message="form.errors.target_end_date"
                            />
                        </div>
                    </div>
                </div>

                <SheetFooter
                    class="border-border flex flex-row items-center justify-end gap-2 border-t pt-4"
                >
                    <Button
                        type="button"
                        variant="outline"
                        @click="handleClose"
                        :disabled="form.processing"
                        data-test="btn-cancel-drawer"
                    >
                        {{ __('Batal') }}
                    </Button>
                    <Button
                        type="submit"
                        class="bg-[#cc0000] text-white hover:bg-[#b30000]"
                        :disabled="form.processing"
                        data-test="btn-submit-drawer"
                    >
                        {{
                            form.processing
                                ? __('Menyimpan...')
                                : isEdit
                                  ? __('Perbarui Proyek')
                                  : __('Simpan Proyek')
                        }}
                    </Button>
                </SheetFooter>
            </form>
        </SheetContent>
    </Sheet>
</template>
