<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ClipboardList, Mail, Plus, Save } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/AlertFollowUpController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTrans } from '@/composables/useTrans';
import { BURN_ZONE_STYLES, normalizeBurnZone } from '@/lib/burnZone';
import type { BurnZone } from '@/lib/burnZone';
import { formatRupiah } from '@/lib/formatters';

export interface AlertFollowUpItem {
    id: number;
    department_id: number | null;
    section_id: number | null;
    section_name: string | null;
    fiscal_year: number;
    fiscal_month: number;
    zone: BurnZone;
    index_pct: number;
    what_text: string | null;
    why_text: string | null;
    when_date: string | null;
    where_location: string | null;
    who_department_id: number | null;
    who_department_name: string | null;
    how_text: string | null;
    how_much_cost_idr: number;
    how_much_hours: number;
    status: 'draft' | 'sent';
    status_label: string;
    is_editable: boolean;
    sent_at: string | null;
    created_by_name: string;
}

export interface FollowUpAlertContext {
    average_index: number;
    average_zone: BurnZone;
}

interface DepartmentOption {
    id: number;
    code: string;
    name: string;
}

interface Props {
    followUps?: AlertFollowUpItem[];
    alert?: FollowUpAlertContext;
    departments?: DepartmentOption[];
    scopeDepartmentId?: number | null;
    scopeSectionId?: number | null;
    scopeLabel?: string;
    selectedDate?: string;
}

const props = withDefaults(defineProps<Props>(), {
    followUps: () => [],
    alert: undefined,
    departments: () => [],
    scopeDepartmentId: null,
    scopeSectionId: null,
    scopeLabel: '',
    selectedDate: '',
});

const { __ } = useTrans();

const todayIso = (): string => new Date().toISOString().slice(0, 10);

/** Follow-up currently shown in the form; null means a brand-new one. */
const editingId = ref<number | null>(null);
const startNew = ref(false);

const latestDraft = computed(
    () => props.followUps.find((item) => item.status === 'draft') ?? null,
);

const activeFollowUp = computed<AlertFollowUpItem | null>(() => {
    if (editingId.value !== null) {
        return (
            props.followUps.find((item) => item.id === editingId.value) ?? null
        );
    }

    return startNew.value ? null : latestDraft.value;
});

const isReadOnly = computed(
    () => activeFollowUp.value !== null && !activeFollowUp.value.is_editable,
);

const alertZone = computed(() =>
    normalizeBurnZone(props.alert?.average_zone, 'green'),
);

const form = useForm({
    department_id: null as number | null,
    section_id: null as number | null,
    date: '',
    what_text: '',
    why_text: '',
    when_date: '',
    where_location: '',
    who_department_id: '' as number | string,
    how_text: '',
    how_much_cost_idr: 0,
    how_much_hours: 0,
    send: false,
});

function defaultWhat(): string {
    const zoneLabel = __(BURN_ZONE_STYLES[alertZone.value].label);
    const index = props.alert?.average_index ?? 0;

    return __('Early Warning :zone: indeks rata-rata :index% di :scope', {
        zone: zoneLabel,
        index,
        scope: props.scopeLabel || __('Seluruh Pabrik'),
    });
}

function loadForm(item: AlertFollowUpItem | null): void {
    form.clearErrors();
    form.department_id = props.scopeDepartmentId;
    form.section_id = props.scopeSectionId;
    form.date = props.selectedDate || todayIso();
    form.send = false;

    if (item) {
        form.what_text = item.what_text ?? '';
        form.why_text = item.why_text ?? '';
        form.when_date = item.when_date ?? '';
        form.where_location = item.where_location ?? '';
        form.who_department_id = item.who_department_id ?? '';
        form.how_text = item.how_text ?? '';
        form.how_much_cost_idr = item.how_much_cost_idr;
        form.how_much_hours = item.how_much_hours;

        return;
    }

    form.what_text = defaultWhat();
    form.why_text = '';
    form.when_date = props.selectedDate || todayIso();
    form.where_location = props.scopeLabel;
    form.who_department_id = props.scopeDepartmentId ?? '';
    form.how_text = '';
    form.how_much_cost_idr = 0;
    form.how_much_hours = 0;
}

watch(
    () =>
        [
            activeFollowUp.value?.id ?? null,
            activeFollowUp.value?.status ?? null,
            props.scopeDepartmentId,
            props.scopeSectionId,
            props.selectedDate,
            props.alert?.average_zone,
        ] as const,
    () => loadForm(activeFollowUp.value),
    { immediate: true },
);

// A new scope / period resets any history navigation.
watch(
    () => [props.scopeDepartmentId, props.scopeSectionId, props.selectedDate],
    () => {
        editingId.value = null;
        startNew.value = false;
    },
);

function submit(send: boolean): void {
    if (isReadOnly.value || form.processing) {
        return;
    }

    form.send = send;

    const updatingId = activeFollowUp.value?.id ?? null;

    const options = {
        preserveScroll: true,
        preserveState: true,
        only: ['alertFollowUps', 'flash'],
        onSuccess: () => {
            startNew.value = false;
            editingId.value = null;

            if (send) {
                // Keep the freshly sent follow-up on screen (read-only) as confirmation.
                void nextTick(() => {
                    editingId.value =
                        updatingId ?? props.followUps[0]?.id ?? null;
                });
            }
        },
    };

    if (updatingId !== null) {
        form.put(update.url(updatingId), options);

        return;
    }

    form.post(store.url(), options);
}

function newFollowUp(): void {
    editingId.value = null;
    startNew.value = true;
    loadForm(null);
}

function viewFollowUp(item: AlertFollowUpItem): void {
    editingId.value = item.id;
    startNew.value = false;
}

const zoneStyle = computed(() =>
    activeFollowUp.value
        ? BURN_ZONE_STYLES[normalizeBurnZone(activeFollowUp.value.zone)]
        : BURN_ZONE_STYLES[alertZone.value],
);

const shownIndex = computed(
    () => activeFollowUp.value?.index_pct ?? props.alert?.average_index ?? 0,
);

const statusLabel = computed(() =>
    activeFollowUp.value?.status === 'sent' ? __('Terkirim') : __('Draft'),
);

const costPreview = computed(() =>
    formatRupiah(Number(form.how_much_cost_idr) || 0, {
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }),
);

const fieldClass =
    'border-input dark:bg-input/30 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-60';
</script>

<template>
    <Card class="border-border/70 shadow-2xs" data-test="followup-panel">
        <CardHeader class="pb-3">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="space-y-1">
                    <CardTitle
                        class="flex items-center gap-2 text-base font-bold text-slate-900 dark:text-slate-100"
                    >
                        <ClipboardList class="size-4 text-[#cc0000]" />
                        <span>{{ __('Tindak Lanjut Alert (5W2H)') }}</span>
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{
                            __(
                                'Catat tindak lanjut atas alert Early Warning, lalu kirim via email ke PIC.',
                            )
                        }}
                    </CardDescription>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <Badge
                        variant="outline"
                        class="font-mono text-[11px] font-semibold tabular-nums"
                        :class="zoneStyle.badge"
                        data-test="followup-zone-badge"
                    >
                        {{ __(zoneStyle.label) }} · {{ shownIndex }}%
                    </Badge>
                    <Badge
                        variant="outline"
                        class="text-[11px] font-semibold"
                        :class="
                            activeFollowUp?.status === 'sent'
                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300'
                                : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'
                        "
                        data-test="followup-status-pill"
                    >
                        {{ statusLabel }}
                    </Badge>
                </div>
            </div>
        </CardHeader>

        <CardContent class="space-y-4 pt-0">
            <!-- History of follow-ups for this scope & period -->
            <div
                v-if="followUps.length > 0"
                class="flex flex-wrap items-center gap-2 text-xs"
                data-test="followup-history"
            >
                <span class="text-muted-foreground">{{
                    __('Riwayat bulan ini')
                }}</span>
                <button
                    v-for="item in followUps"
                    :key="item.id"
                    type="button"
                    class="cursor-pointer rounded-full border px-2.5 py-1 font-mono text-[11px] font-medium transition-colors"
                    :class="[
                        BURN_ZONE_STYLES[normalizeBurnZone(item.zone)].badge,
                        activeFollowUp?.id === item.id
                            ? 'ring-2 ring-slate-400/60'
                            : '',
                    ]"
                    :data-test="`followup-history-item-${item.id}`"
                    @click="viewFollowUp(item)"
                >
                    #{{ item.id }} ·
                    {{ item.status === 'sent' ? __('Terkirim') : __('Draft') }}
                </button>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="h-7 cursor-pointer gap-1 text-xs"
                    data-test="followup-new"
                    @click="newFollowUp"
                >
                    <Plus class="size-3.5" />
                    {{ __('Tindak Lanjut Baru') }}
                </Button>
            </div>

            <div
                v-if="isReadOnly && activeFollowUp?.sent_at"
                class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300"
                data-test="followup-sent-note"
            >
                {{
                    __(
                        'Terkirim via email pada :time oleh :name (read-only).',
                        {
                            time: activeFollowUp.sent_at,
                            name: activeFollowUp.created_by_name,
                        },
                    )
                }}
            </div>

            <div class="grid gap-3 md:grid-cols-2">
                <!-- What -->
                <div class="space-y-1.5 md:col-span-2">
                    <Label for="followup-what" class="text-xs font-semibold">
                        {{ __('What — apa masalahnya') }}
                        <span class="text-red-500">*</span>
                    </Label>
                    <textarea
                        id="followup-what"
                        v-model="form.what_text"
                        rows="2"
                        :class="fieldClass"
                        :disabled="isReadOnly"
                        data-test="followup-what"
                    />
                    <InputError :message="form.errors.what_text" />
                </div>

                <!-- Why -->
                <div class="space-y-1.5 md:col-span-2">
                    <Label for="followup-why" class="text-xs font-semibold">
                        {{ __('Why — kenapa terjadi') }}
                    </Label>
                    <textarea
                        id="followup-why"
                        v-model="form.why_text"
                        rows="2"
                        :class="fieldClass"
                        :disabled="isReadOnly"
                        data-test="followup-why"
                    />
                    <InputError :message="form.errors.why_text" />
                </div>

                <!-- When -->
                <div class="space-y-1.5">
                    <Label for="followup-when" class="text-xs font-semibold">
                        {{ __('When') }}
                    </Label>
                    <Input
                        id="followup-when"
                        v-model="form.when_date"
                        type="date"
                        class="h-9"
                        :disabled="isReadOnly"
                        data-test="followup-when"
                    />
                    <InputError :message="form.errors.when_date" />
                </div>

                <!-- Where -->
                <div class="space-y-1.5">
                    <Label for="followup-where" class="text-xs font-semibold">
                        {{ __('Where') }}
                    </Label>
                    <Input
                        id="followup-where"
                        v-model="form.where_location"
                        type="text"
                        class="h-9"
                        :disabled="isReadOnly"
                        data-test="followup-where"
                    />
                    <InputError :message="form.errors.where_location" />
                </div>

                <!-- Who -->
                <div class="space-y-1.5 md:col-span-2">
                    <Label for="followup-who" class="text-xs font-semibold">
                        {{ __('Who — PIC terkait (departemen)') }}
                    </Label>
                    <select
                        id="followup-who"
                        v-model="form.who_department_id"
                        :class="fieldClass"
                        class="h-9"
                        :disabled="isReadOnly"
                        data-test="followup-who"
                    >
                        <option value="">
                            {{ __('Pilih departemen PIC') }}
                        </option>
                        <option
                            v-for="dept in departments"
                            :key="dept.id"
                            :value="dept.id"
                        >
                            {{ dept.name }}
                        </option>
                    </select>
                    <p class="text-muted-foreground text-[11px]">
                        {{
                            __(
                                'Email dikirim ke seluruh Admin, Manager, dan Team Leader aktif departemen ini.',
                            )
                        }}
                    </p>
                    <InputError :message="form.errors.who_department_id" />
                </div>

                <!-- How -->
                <div class="space-y-1.5 md:col-span-2">
                    <Label for="followup-how" class="text-xs font-semibold">
                        {{ __('How — rencana tindak lanjut') }}
                    </Label>
                    <textarea
                        id="followup-how"
                        v-model="form.how_text"
                        rows="2"
                        :class="fieldClass"
                        :disabled="isReadOnly"
                        data-test="followup-how"
                    />
                    <InputError :message="form.errors.how_text" />
                </div>

                <!-- How much -->
                <div class="space-y-1.5">
                    <Label for="followup-cost" class="text-xs font-semibold">
                        {{ __('How much — biaya (Rp)') }}
                    </Label>
                    <Input
                        id="followup-cost"
                        v-model.number="form.how_much_cost_idr"
                        type="number"
                        min="0"
                        step="1000"
                        class="h-9 font-mono tabular-nums"
                        :disabled="isReadOnly"
                        data-test="followup-cost"
                    />
                    <p
                        class="text-muted-foreground font-mono text-[11px] tabular-nums"
                    >
                        {{ costPreview }}
                    </p>
                    <InputError :message="form.errors.how_much_cost_idr" />
                </div>

                <div class="space-y-1.5">
                    <Label for="followup-hours" class="text-xs font-semibold">
                        {{ __('How much — jam lembur') }}
                    </Label>
                    <Input
                        id="followup-hours"
                        v-model.number="form.how_much_hours"
                        type="number"
                        min="0"
                        step="0.5"
                        class="h-9 font-mono tabular-nums"
                        :disabled="isReadOnly"
                        data-test="followup-hours"
                    />
                    <InputError :message="form.errors.how_much_hours" />
                </div>
            </div>

            <div
                v-if="!isReadOnly"
                class="flex flex-wrap items-center justify-end gap-2 border-t pt-3"
            >
                <Button
                    type="button"
                    variant="outline"
                    class="cursor-pointer gap-1.5"
                    :disabled="form.processing"
                    data-test="followup-save-draft"
                    @click="submit(false)"
                >
                    <Save class="size-4" />
                    {{ __('Simpan Draft') }}
                </Button>
                <Button
                    type="button"
                    class="cursor-pointer gap-1.5 bg-[#cc0000] text-white hover:bg-[#b30000]"
                    :disabled="form.processing"
                    data-test="followup-send"
                    @click="submit(true)"
                >
                    <Mail class="size-4" />
                    {{ __('Kirim via Email') }}
                </Button>
            </div>
        </CardContent>
    </Card>
</template>
