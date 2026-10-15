<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ClipboardList, Mail, Plus, RotateCcw, Save } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/AlertFollowUpController';
import InputError from '@/components/InputError.vue';
import FollowUpFieldTag from '@/components/early-warning/FollowUpFieldTag.vue';
import FollowUpStatusPill from '@/components/early-warning/FollowUpStatusPill.vue';
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
import type {
    AlertFollowUpItem,
    FollowUpFields,
    FollowUpSuggestion,
    WhoDepartmentOption,
} from '@/lib/earlyWarning';
import { formatRupiah } from '@/lib/formatters';

type FieldKey = keyof FollowUpFields;

const props = defineProps<{
    /** Follow-ups of this alert, newest first. */
    followUps: AlertFollowUpItem[];
    /** System suggestion the form opens with; null when the date is not an alert. */
    suggestion: FollowUpSuggestion | null;
    departments: WhoDepartmentOption[];
    sectionId: number;
    alertDate: string;
    /** False when the date is not (or no longer) an alert: nothing can be created or edited. */
    hasAlert: boolean;
}>();

const { __ } = useTrans();

const fieldKeys: FieldKey[] = [
    'what_text',
    'why_text',
    'when_date',
    'where_location',
    'who_department_id',
    'how_text',
    'how_much_cost_idr',
    'how_much_hours',
];

/** Follow-up picked from the history; null means "the default one". */
const selectedId = ref<number | null>(null);
const startNew = ref(false);

const latestDraft = computed(
    () => props.followUps.find((item) => item.status === 'draft') ?? null,
);

/** The draft when there is one (one draft per alert), else the newest (sent) follow-up. */
const defaultFollowUp = computed(
    () => latestDraft.value ?? props.followUps[0] ?? null,
);

const activeFollowUp = computed<AlertFollowUpItem | null>(() => {
    if (startNew.value) {
        return null;
    }

    return (
        props.followUps.find((item) => item.id === selectedId.value) ??
        defaultFollowUp.value
    );
});

const isReadOnly = computed(
    () =>
        !props.hasAlert ||
        (activeFollowUp.value !== null && !activeFollowUp.value.is_editable),
);

/** "Follow-up baru" is offered once the draft is gone, i.e. when only sent follow-ups exist. */
const canStartNew = computed(
    () =>
        props.hasAlert &&
        props.followUps.length > 0 &&
        latestDraft.value === null &&
        !startNew.value,
);

/** Whether there is anything to show: a form for an alert, or the saved history of a non-alert. */
const showsForm = computed(() => props.hasAlert || props.followUps.length > 0);

/** What the fields are compared with: the snapshot of a saved follow-up, else the suggestion. */
const baseline = computed<Partial<FollowUpSuggestion> | null>(() =>
    activeFollowUp.value
        ? activeFollowUp.value.generated_payload
        : props.suggestion,
);

const form = useForm({
    section_id: props.sectionId,
    alert_date: props.alertDate,
    what_text: '',
    why_text: '',
    when_date: '',
    where_location: '',
    who_department_id: '' as number | string,
    how_text: '',
    how_much_cost_idr: 0 as number | string,
    how_much_hours: 0 as number | string,
    send: false,
});

function loadForm(
    source: Partial<FollowUpSuggestion> | AlertFollowUpItem | null,
): void {
    form.clearErrors();
    form.section_id = props.sectionId;
    form.alert_date = props.alertDate;
    form.send = false;
    form.what_text = source?.what_text ?? '';
    form.why_text = source?.why_text ?? '';
    form.when_date = source?.when_date ?? '';
    form.where_location = source?.where_location ?? '';
    form.who_department_id = source?.who_department_id ?? '';
    form.how_text = source?.how_text ?? '';
    form.how_much_cost_idr = source?.how_much_cost_idr ?? 0;
    form.how_much_hours = source?.how_much_hours ?? 0;
}

watch(
    () =>
        [
            activeFollowUp.value?.id ?? null,
            activeFollowUp.value?.status ?? null,
            startNew.value,
            props.sectionId,
            props.alertDate,
        ] as const,
    () => loadForm(activeFollowUp.value ?? props.suggestion),
    { immediate: true },
);

// Another alert reuses this component: forget any history navigation.
watch(
    () => [props.sectionId, props.alertDate],
    () => {
        selectedId.value = null;
        startNew.value = false;
    },
);

function isBlank(value: unknown): boolean {
    return value === null || value === undefined || String(value).trim() === '';
}

function sameValue(a: unknown, b: unknown): boolean {
    if (typeof a === 'number' || typeof b === 'number') {
        return Number(a) === Number(b);
    }

    return String(a ?? '').trim() === String(b ?? '').trim();
}

/**
 * "Dibuat sistem" while a field still equals what the system generated, "Diubah" once it differs.
 * Fields the system left empty (or zero) have nothing to compare with and carry no tag.
 */
function fieldState(key: FieldKey): 'generated' | 'edited' | null {
    const generated = baseline.value?.[key];

    if (isBlank(generated) || generated === 0) {
        return null;
    }

    return sameValue(form[key], generated) ? 'generated' : 'edited';
}

const hasEditedFields = computed(() =>
    fieldKeys.some((key) => fieldState(key) === 'edited'),
);

function resetField(key: FieldKey): void {
    const generated = baseline.value?.[key];

    // A field the system left empty has no suggestion to go back to; keep what the user typed.
    if (isBlank(generated) || generated === 0) {
        return;
    }

    form[key] = generated as never;
}

function resetAll(): void {
    fieldKeys.forEach(resetField);
}

function submit(send: boolean): void {
    if (isReadOnly.value || form.processing) {
        return;
    }

    form.send = send;

    const updatingId = activeFollowUp.value?.id ?? null;

    const options = {
        preserveScroll: true,
        preserveState: true,
        only: ['followUps', 'flash'],
        onSuccess: () => {
            // Keep the saved follow-up on screen: a new draft becomes the one that is edited next,
            // a sent one stays visible read-only as confirmation.
            void nextTick(() => {
                startNew.value = false;
                selectedId.value = updatingId ?? props.followUps[0]?.id ?? null;
            });
        },
    };

    if (updatingId !== null) {
        form.put(update.url(updatingId), options);

        return;
    }

    form.post(store.url(), options);
}

function showFollowUp(item: AlertFollowUpItem): void {
    startNew.value = false;
    selectedId.value = item.id;
}

function newFollowUp(): void {
    selectedId.value = null;
    startNew.value = true;
}

function chipLabel(item: AlertFollowUpItem): string {
    const state = item.status === 'sent' ? __('Terkirim') : __('Draft');

    return `${state} · ${item.created_at?.slice(0, 5) ?? ''}`.trim();
}

/** Errors that are not tied to one field (sent follow-up, second draft, non-alert date). */
const generalError = computed(() => {
    const errors = form.errors as Record<string, string | undefined>;

    return errors.status ?? errors.alert_date ?? errors.section_id;
});

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
                    <CardDescription v-if="hasAlert" class="text-xs">
                        {{
                            __(
                                'The system filled in what it could from the alert data. Review it, edit anything, then save a draft or send it by email to the PIC.',
                            )
                        }}
                    </CardDescription>
                </div>

                <FollowUpStatusPill
                    :status="activeFollowUp?.status ?? 'none'"
                />
            </div>
        </CardHeader>

        <CardContent class="space-y-4 pt-0">
            <p
                v-if="!showsForm"
                class="text-sm text-slate-500"
                data-test="followup-empty"
            >
                {{ __('No follow-up has been recorded for this alert yet.') }}
            </p>

            <template v-else>
                <div
                    v-if="followUps.length > 0"
                    class="flex flex-wrap items-center gap-2 text-xs"
                    data-test="followup-history"
                >
                    <span class="text-muted-foreground">
                        {{ __('Follow-up history') }}
                    </span>
                    <button
                        v-for="item in followUps"
                        :key="item.id"
                        type="button"
                        class="cursor-pointer rounded-full border px-2.5 py-0.5 font-semibold transition-colors"
                        :class="
                            item.id === activeFollowUp?.id
                                ? 'border-[#cc0000] bg-red-50 text-[#cc0000] dark:bg-red-950/40'
                                : 'border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800'
                        "
                        :aria-pressed="item.id === activeFollowUp?.id"
                        :data-test="`followup-history-item-${item.id}`"
                        @click="showFollowUp(item)"
                    >
                        {{ chipLabel(item) }}
                    </button>
                    <Button
                        v-if="canStartNew"
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
                    v-if="activeFollowUp?.status === 'sent'"
                    class="rounded-md border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300"
                    data-test="followup-sent-note"
                >
                    {{
                        __(
                            'Terkirim via email pada :time oleh :name (read-only).',
                            {
                                time: activeFollowUp.sent_at ?? '-',
                                name: activeFollowUp.sent_by_name ?? '-',
                            },
                        )
                    }}
                </div>

                <p
                    v-if="generalError"
                    class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-300"
                    role="alert"
                    data-test="followup-general-error"
                >
                    {{ generalError }}
                </p>

                <div class="grid gap-3 md:grid-cols-2">
                    <!-- What -->
                    <div class="space-y-1.5 md:col-span-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <Label
                                for="followup-what"
                                class="text-xs font-semibold"
                            >
                                {{ __('What — apa masalahnya') }}
                                <span class="text-red-500">*</span>
                            </Label>
                            <FollowUpFieldTag
                                field="what"
                                :state="fieldState('what_text')"
                                :read-only="isReadOnly"
                                @reset="resetField('what_text')"
                            />
                        </div>
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
                        <div class="flex flex-wrap items-center gap-2">
                            <Label
                                for="followup-why"
                                class="text-xs font-semibold"
                            >
                                {{ __('Why — kenapa terjadi') }}
                            </Label>
                            <FollowUpFieldTag
                                field="why"
                                :state="fieldState('why_text')"
                                :read-only="isReadOnly"
                                @reset="resetField('why_text')"
                            />
                        </div>
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
                        <div class="flex flex-wrap items-center gap-2">
                            <Label
                                for="followup-when"
                                class="text-xs font-semibold"
                            >
                                {{ __('When') }}
                            </Label>
                            <FollowUpFieldTag
                                field="when"
                                :state="fieldState('when_date')"
                                :read-only="isReadOnly"
                                @reset="resetField('when_date')"
                            />
                        </div>
                        <Input
                            id="followup-when"
                            v-model="form.when_date"
                            type="date"
                            class="h-9"
                            :disabled="isReadOnly"
                            data-test="followup-when"
                        />
                        <p
                            v-if="baseline?.when_text"
                            class="text-muted-foreground text-[11px]"
                            data-test="followup-when-hint"
                        >
                            {{ baseline.when_text }}
                        </p>
                        <InputError :message="form.errors.when_date" />
                    </div>

                    <!-- Where -->
                    <div class="space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <Label
                                for="followup-where"
                                class="text-xs font-semibold"
                            >
                                {{ __('Where') }}
                            </Label>
                            <FollowUpFieldTag
                                field="where"
                                :state="fieldState('where_location')"
                                :read-only="isReadOnly"
                                @reset="resetField('where_location')"
                            />
                        </div>
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
                        <div class="flex flex-wrap items-center gap-2">
                            <Label
                                for="followup-who"
                                class="text-xs font-semibold"
                            >
                                {{ __('Who — PIC terkait (departemen)') }}
                            </Label>
                            <FollowUpFieldTag
                                field="who"
                                :state="fieldState('who_department_id')"
                                :read-only="isReadOnly"
                                @reset="resetField('who_department_id')"
                            />
                        </div>
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
                        <div class="flex flex-wrap items-center gap-2">
                            <Label
                                for="followup-how"
                                class="text-xs font-semibold"
                            >
                                {{ __('How — rencana tindak lanjut') }}
                            </Label>
                            <FollowUpFieldTag
                                field="how"
                                :state="fieldState('how_text')"
                                :read-only="isReadOnly"
                                @reset="resetField('how_text')"
                            />
                        </div>
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

                    <!-- How much: cost -->
                    <div class="space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <Label
                                for="followup-cost"
                                class="text-xs font-semibold"
                            >
                                {{ __('How much — biaya (Rp)') }}
                            </Label>
                            <FollowUpFieldTag
                                field="cost"
                                :state="fieldState('how_much_cost_idr')"
                                :read-only="isReadOnly"
                                @reset="resetField('how_much_cost_idr')"
                            />
                        </div>
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
                            data-test="followup-cost-preview"
                        >
                            {{ costPreview }}
                        </p>
                        <p
                            v-if="baseline?.cost_note"
                            class="text-[11px] text-amber-700 dark:text-amber-400"
                            data-test="followup-cost-note"
                        >
                            {{ baseline.cost_note }}
                        </p>
                        <InputError :message="form.errors.how_much_cost_idr" />
                    </div>

                    <!-- How much: hours -->
                    <div class="space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <Label
                                for="followup-hours"
                                class="text-xs font-semibold"
                            >
                                {{ __('How much — jam lembur') }}
                            </Label>
                            <FollowUpFieldTag
                                field="hours"
                                :state="fieldState('how_much_hours')"
                                :read-only="isReadOnly"
                                @reset="resetField('how_much_hours')"
                            />
                        </div>
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
                        v-if="hasEditedFields"
                        type="button"
                        variant="ghost"
                        class="mr-auto cursor-pointer gap-1.5 text-xs"
                        data-test="followup-reset-all"
                        @click="resetAll"
                    >
                        <RotateCcw class="size-3.5" />
                        {{ __('Reset semua ke saran sistem') }}
                    </Button>
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
            </template>
        </CardContent>
    </Card>
</template>
