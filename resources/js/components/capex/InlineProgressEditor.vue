<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, Edit3, Loader2, RotateCcw, TrendingUp } from '@lucide/vue';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { useTrans } from '@/composables/useTrans';
import capexProjectsRoute from '@/routes/admin/capex-projects';

const props = defineProps<{
    projectId: number;
    initialProgress: number;
}>();

const emit = defineEmits<{
    (e: 'updated', newProgress: number): void;
}>();

const { __ } = useTrans();

const currentProgress = ref(props.initialProgress);
const isEditing = ref(false);
const isSaving = ref(false);
const errorMessage = ref<string | null>(null);

watch(
    () => props.initialProgress,
    (val) => {
        if (!isSaving.value) {
            currentProgress.value = Number(val) || 0;
        }
    },
);

function handleSliderChange(event: Event) {
    const target = event.target as HTMLInputElement;
    currentProgress.value = Math.min(100, Math.max(0, Number(target.value)));
    errorMessage.value = null;
}

function handleInputChange(event: Event) {
    const target = event.target as HTMLInputElement;
    const val = Number(target.value);
    if (!isNaN(val)) {
        currentProgress.value = Math.min(100, Math.max(0, val));
        errorMessage.value = null;
    }
}

function cancelEdit() {
    currentProgress.value = props.initialProgress;
    isEditing.value = false;
    errorMessage.value = null;
}

function saveProgress() {
    isSaving.value = true;
    errorMessage.value = null;

    router.patch(
        capexProjectsRoute.progress.update.url({
            capex_project: props.projectId,
        }),
        {
            physical_progress_pct: currentProgress.value,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSaving.value = false;
                isEditing.value = false;
                emit('updated', currentProgress.value);
            },
            onError: (errors) => {
                isSaving.value = false;
                errorMessage.value =
                    errors.physical_progress_pct ||
                    __('Gagal memperbarui kemajuan fisik.');
            },
        },
    );
}
</script>

<template>
    <Card class="border-border bg-card" data-test="inline-progress-editor-card">
        <CardHeader class="border-border border-b pb-3">
            <div class="flex items-center justify-between">
                <div class="space-y-0.5">
                    <CardTitle
                        class="text-foreground flex items-center gap-2 text-sm font-bold"
                    >
                        <TrendingUp class="size-4 text-sky-600" />
                        {{ __('Pembaruan Kemajuan Fisik Proyek (In-Place)') }}
                    </CardTitle>
                    <CardDescription class="text-xs">
                        {{
                            __(
                                'Perbarui persentase pekerjaan fisik di lapangan untuk sinkronisasi rasio burn milestone.',
                            )
                        }}
                    </CardDescription>
                </div>

                <Button
                    v-if="!isEditing"
                    variant="outline"
                    size="sm"
                    class="h-8 gap-1.5 text-xs font-semibold"
                    @click="isEditing = true"
                    data-test="btn-edit-progress"
                >
                    <Edit3 class="size-3.5 text-sky-600" />
                    {{ __('Ubah Kemajuan') }}
                </Button>
            </div>
        </CardHeader>

        <CardContent class="space-y-4 p-5">
            <!-- Display Mode -->
            <div
                v-if="!isEditing"
                class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="space-y-1">
                    <div class="flex items-baseline gap-2">
                        <span
                            class="font-mono text-3xl font-extrabold text-sky-700 tabular-nums dark:text-sky-300"
                            data-test="display-progress-pct"
                        >
                            {{ Number(initialProgress).toFixed(1) }}%
                        </span>
                        <span class="text-muted-foreground text-xs font-medium">
                            {{ __('Selesai Dikerjakan') }}
                        </span>
                    </div>

                    <div
                        class="bg-muted h-2.5 w-64 overflow-hidden rounded-full"
                    >
                        <div
                            class="h-full rounded-full bg-sky-600 transition-all duration-300"
                            :style="{
                                width: `${Math.min(100, Math.max(0, initialProgress))}%`,
                            }"
                        ></div>
                    </div>
                </div>

                <p
                    class="text-muted-foreground max-w-xs text-right text-xs leading-relaxed"
                >
                    {{
                        initialProgress >= 100
                            ? __(
                                  'Proyek telah mencapai 100% penyelesaian fisik dan siap ditransisikan ke status COMPLETED.',
                              )
                            : __(
                                  'Klik "Ubah Kemajuan" untuk menyesuaikan progres fisik sesuai laporan aktual mandor lapangan.',
                              )
                    }}
                </p>
            </div>

            <!-- Edit Mode -->
            <div v-else class="space-y-4" data-test="edit-progress-form">
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <!-- Slider Control -->
                    <div class="flex-1 space-y-2">
                        <div
                            class="flex items-center justify-between text-xs font-medium"
                        >
                            <label
                                for="progress-slider"
                                class="text-foreground"
                            >
                                {{ __('Geser Persentase (0% - 100%)') }}
                            </label>
                            <span
                                class="font-mono text-sm font-bold text-sky-700 dark:text-sky-300"
                            >
                                {{ currentProgress.toFixed(1) }}%
                            </span>
                        </div>
                        <input
                            id="progress-slider"
                            type="range"
                            min="0"
                            max="100"
                            step="0.5"
                            v-model.number="currentProgress"
                            class="h-2 w-full cursor-pointer appearance-none rounded-lg bg-slate-200 accent-sky-600 dark:bg-slate-700"
                            data-test="slider-progress"
                        />
                    </div>

                    <!-- Direct Number Input -->
                    <div class="w-full sm:w-36">
                        <label
                            for="progress-input"
                            class="text-muted-foreground block text-[11px] font-medium"
                        >
                            {{ __('Input Manual (%)') }}
                        </label>
                        <div class="relative mt-1">
                            <Input
                                id="progress-input"
                                type="number"
                                min="0"
                                max="100"
                                step="0.5"
                                v-model.number="currentProgress"
                                class="pr-7 font-mono text-sm font-bold tabular-nums"
                                data-test="input-progress-number"
                            />
                            <span
                                class="text-muted-foreground pointer-events-none absolute inset-y-0 right-2.5 flex items-center text-xs font-bold"
                            >
                                %
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Error Message if any -->
                <p
                    v-if="errorMessage"
                    class="text-xs font-semibold text-[#cc0000]"
                    data-test="error-progress"
                >
                    {{ errorMessage }}
                </p>

                <!-- Actions -->
                <div class="flex items-center justify-end gap-2 pt-2">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="h-8 gap-1 text-xs"
                        :disabled="isSaving"
                        @click="cancelEdit"
                        data-test="btn-cancel-progress"
                    >
                        <RotateCcw class="size-3" />
                        {{ __('Batal') }}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        class="h-8 gap-1.5 bg-sky-700 text-xs font-semibold text-white hover:bg-sky-800"
                        :disabled="isSaving"
                        @click="saveProgress"
                        data-test="btn-save-progress"
                    >
                        <Loader2
                            v-if="isSaving"
                            class="size-3.5 animate-spin"
                        />
                        <Check v-else class="size-3.5" />
                        {{ isSaving ? __('Menyimpan...') : __('Simpan') }}
                    </Button>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
