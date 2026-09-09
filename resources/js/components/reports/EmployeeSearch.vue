<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useDebounceFn, onClickOutside } from '@vueuse/core';
import { Loader2, Search, User as UserIcon, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { useTrans } from '@/composables/useTrans';
import { search, show } from '@/routes/reports/employees';

export interface SearchResultItem {
    id: number;
    npk: string;
    name: string;
    job_position: string;
    department_name: string;
    section_name: string;
    is_active: boolean;
}

const props = withDefaults(
    defineProps<{
        placeholder?: string;
        compact?: boolean;
    }>(),
    {
        placeholder: 'Cari nama atau NPK karyawan...',
        compact: false,
    },
);

const emit = defineEmits<{
    (e: 'select', employee: SearchResultItem): void;
}>();

const { __ } = useTrans();

const searchQuery = ref('');
const results = ref<SearchResultItem[]>([]);
const isLoading = ref(false);
const isOpen = ref(false);
const selectedIndex = ref(-1);
const searchContainerRef = ref<HTMLElement | null>(null);

onClickOutside(searchContainerRef, () => {
    isOpen.value = false;
});

const executeSearch = useDebounceFn(async (query: string) => {
    const trimmed = query.trim();

    if (trimmed.length < 3) {
        results.value = [];
        isLoading.value = false;
        isOpen.value = false;
        return;
    }

    isLoading.value = true;
    try {
        const response = await fetch(
            search.url({
                query: { q: trimmed },
            }),
            {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            },
        );

        if (response.ok) {
            const data = await response.json();
            results.value = Array.isArray(data.data) ? data.data : [];
            isOpen.value = true;
            selectedIndex.value = -1;
        } else {
            results.value = [];
        }
    } catch {
        results.value = [];
    } finally {
        isLoading.value = false;
    }
}, 300);

const onInput = () => {
    if (searchQuery.value.trim().length >= 3) {
        isLoading.value = true;
        executeSearch(searchQuery.value);
    } else {
        results.value = [];
        isOpen.value = false;
        isLoading.value = false;
    }
};

const clearSearch = () => {
    searchQuery.value = '';
    results.value = [];
    isOpen.value = false;
    selectedIndex.value = -1;
};

const selectEmployee = (employee: SearchResultItem) => {
    isOpen.value = false;
    emit('select', employee);
    router.visit(show.url({ npk: employee.npk }));
};

const onKeyDown = (event: KeyboardEvent) => {
    if (!isOpen.value || results.value.length === 0) {
        if (event.key === 'Escape') {
            isOpen.value = false;
        }
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        selectedIndex.value =
            selectedIndex.value < results.value.length - 1
                ? selectedIndex.value + 1
                : 0;
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        selectedIndex.value =
            selectedIndex.value > 0
                ? selectedIndex.value - 1
                : results.value.length - 1;
    } else if (event.key === 'Enter') {
        event.preventDefault();
        if (
            selectedIndex.value >= 0 &&
            selectedIndex.value < results.value.length
        ) {
            selectEmployee(results.value[selectedIndex.value]);
        }
    } else if (event.key === 'Escape') {
        isOpen.value = false;
    }
};

const hasQuery = computed(() => searchQuery.value.trim().length > 0);
</script>

<template>
    <div ref="searchContainerRef" class="relative w-full">
        <!-- Search Input Bar -->
        <div
            :class="[
                'bg-card focus-within:ring-primary/40 focus-within:border-primary relative flex items-center rounded-lg border shadow-xs transition-all focus-within:ring-2',
                compact ? 'h-9 text-sm' : 'h-11 text-base',
                isOpen ? 'rounded-b-none border-b-transparent' : '',
            ]"
        >
            <div
                class="text-muted-foreground pointer-events-none flex items-center pl-3.5"
            >
                <Search :class="compact ? 'size-4' : 'size-5'" />
            </div>

            <input
                v-model="searchQuery"
                type="text"
                data-test="employee-search-input"
                :placeholder="__(placeholder)"
                :class="[
                    'text-foreground placeholder:text-muted-foreground w-full bg-transparent px-3 py-1 font-normal outline-none',
                    compact ? 'text-sm' : 'text-base',
                ]"
                autocomplete="off"
                @input="onInput"
                @keydown="onKeyDown"
                @focus="onInput"
            />

            <!-- Trailing Status Indicator & Clear Button -->
            <div class="flex items-center gap-1 pr-2">
                <div
                    v-if="isLoading"
                    class="text-muted-foreground flex items-center pr-1"
                >
                    <Loader2 class="text-primary size-4 animate-spin" />
                </div>

                <button
                    v-if="hasQuery"
                    type="button"
                    data-test="employee-search-clear"
                    class="text-muted-foreground hover:bg-muted hover:text-foreground rounded-md p-1 transition-colors"
                    :title="__('Bersihkan Pencarian')"
                    @click="clearSearch"
                >
                    <X class="size-4" />
                </button>
            </div>
        </div>

        <!-- Dropdown Results Floating Panel -->
        <div
            v-if="isOpen"
            data-test="employee-search-results"
            class="bg-card absolute top-full right-0 left-0 z-50 max-h-80 overflow-y-auto rounded-b-lg border border-t-0 shadow-lg"
        >
            <div
                v-if="results.length > 0"
                class="divide-border/50 divide-y py-1"
            >
                <button
                    v-for="(employee, idx) in results"
                    :key="employee.id"
                    type="button"
                    data-test="employee-search-item"
                    :class="[
                        'flex w-full items-center justify-between px-4 py-2.5 text-left transition-colors',
                        selectedIndex === idx
                            ? 'bg-primary/10 text-primary'
                            : 'hover:bg-muted/60 text-foreground',
                    ]"
                    @click="selectEmployee(employee)"
                    @mouseenter="selectedIndex = idx"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="bg-primary/10 text-primary border-primary/20 flex size-9 shrink-0 items-center justify-center rounded-full border"
                        >
                            <UserIcon class="size-4" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="truncate text-sm font-medium">
                                    {{ employee.name }}
                                </span>
                                <span
                                    class="bg-muted text-muted-foreground border-border rounded border px-1.5 py-0.5 font-mono text-xs font-semibold tabular-nums"
                                >
                                    {{ employee.npk }}
                                </span>
                            </div>
                            <div
                                class="text-muted-foreground mt-0.5 flex items-center gap-1.5 truncate text-xs"
                            >
                                <span>{{ employee.job_position }}</span>
                                <span>&bull;</span>
                                <span class="truncate">{{
                                    employee.section_name
                                }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="shrink-0 pl-2">
                        <Badge
                            v-if="employee.is_active"
                            variant="outline"
                            class="border-emerald-300 bg-emerald-50 text-[11px] text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300"
                        >
                            {{ __('Aktif') }}
                        </Badge>
                        <Badge
                            v-else
                            variant="outline"
                            class="border-slate-300 bg-slate-50 text-[11px] text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400"
                        >
                            {{ __('Nonaktif') }}
                        </Badge>
                    </div>
                </button>
            </div>

            <!-- Empty State in Dropdown -->
            <div
                v-else-if="!isLoading && searchQuery.trim().length >= 3"
                class="text-muted-foreground px-4 py-6 text-center text-sm"
            >
                <p class="text-foreground font-medium">
                    {{ __('Tidak ada karyawan ditemukan') }}
                </p>
                <p class="mt-1 text-xs">
                    {{
                        __(
                            'Periksa kembali nama atau NPK karyawan yang Anda masukkan.',
                        )
                    }}
                </p>
            </div>
        </div>
    </div>
</template>
