<script setup lang="ts">
import {
    AlertCircle,
    ArrowDown,
    ArrowUp,
    ArrowUpDown,
    CheckCircle2,
    Clock,
    Eye,
    Filter,
    Layers,
    RotateCcw,
    Search,
    Users,
    X,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import EmployeeQuickDossierDrawer, {
    type EmployeeSummaryItem,
} from '@/components/dashboard/EmployeeQuickDossierDrawer.vue';
import MiniCategoryBar from '@/components/dashboard/MiniCategoryBar.vue';
import MiniProgressBar from '@/components/dashboard/MiniProgressBar.vue';
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
import { useTrans } from '@/composables/useTrans';

export interface EmployeeSummaryData {
    items: EmployeeSummaryItem[];
    total_count: number;
    fiscal_year: number;
    fiscal_month: number;
    month_name: string;
    soft_limit_hours: number;
    scope: {
        department_id: number | null;
        section_id: number | null;
    };
}

interface Props {
    data?: EmployeeSummaryData;
    loading?: boolean;
    selectedCategoryFilter?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    data: undefined,
    loading: false,
    selectedCategoryFilter: null,
});

const emit = defineEmits<{
    (e: 'clear-category-filter'): void;
}>();

const { __ } = useTrans();

// Search with 150ms debounce
const searchQuery = ref('');
const debouncedSearch = ref('');
let debounceTimeout: ReturnType<typeof setTimeout> | null = null;

watch(searchQuery, (newVal) => {
    if (debounceTimeout) {
        clearTimeout(debounceTimeout);
    }
    debounceTimeout = setTimeout(() => {
        debouncedSearch.value = newVal.trim().toLowerCase();
        visibleCount.value = 50;
    }, 150);
});

// Sorting state: default total_hours desc
type SortField = 'total_hours' | 'name' | 'burn_index';
const sortField = ref<SortField>('total_hours');
const sortDirection = ref<'asc' | 'desc'>('desc');

function toggleSort(field: SortField) {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = field === 'name' ? 'asc' : 'desc';
    }
    visibleCount.value = 50;
}

// Pagination / virtualized slice
const PAGE_SIZE = 50;
const visibleCount = ref(PAGE_SIZE);

// Quick-look drawer state
const selectedEmployee = ref<EmployeeSummaryItem | null>(null);
const drawerOpen = ref(false);

function openDrawer(emp: EmployeeSummaryItem) {
    selectedEmployee.value = emp;
    drawerOpen.value = true;
}

// Filter and sort items
const filteredAndSortedItems = computed(() => {
    if (!props.data?.items?.length) {
        return [];
    }

    let items = props.data.items;

    // Category filter cross-filtering from Category Donut
    if (props.selectedCategoryFilter) {
        const cat = props.selectedCategoryFilter;
        items = items.filter((emp) => {
            switch (cat) {
                case 'production':
                    return emp.hours_production > 0;
                case 'tpm':
                    return emp.hours_tpm > 0;
                case 'project':
                    return emp.hours_project > 0;
                case 'others':
                    return emp.hours_others > 0;
                default:
                    return true;
            }
        });
    }

    // Client-side text search (NPK, name, section code/name)
    const q = debouncedSearch.value;
    if (q) {
        items = items.filter(
            (emp) =>
                emp.name.toLowerCase().includes(q) ||
                emp.npk.toLowerCase().includes(q) ||
                emp.section_code.toLowerCase().includes(q) ||
                emp.section_name.toLowerCase().includes(q),
        );
    }

    // Sort items
    const field = sortField.value;
    const isAsc = sortDirection.value === 'asc';

    return [...items].sort((a, b) => {
        let cmp = 0;
        if (field === 'name') {
            cmp = a.name.localeCompare(b.name, 'id');
        } else if (field === 'burn_index') {
            cmp = a.burn_index - b.burn_index;
        } else {
            cmp = a.total_hours - b.total_hours;
        }

        // Secondary tie breaker by name
        if (cmp === 0) {
            cmp = a.name.localeCompare(b.name, 'id');
        }

        return isAsc ? cmp : -cmp;
    });
});

const visibleItems = computed(() => {
    return filteredAndSortedItems.value.slice(0, visibleCount.value);
});

const hasMore = computed(() => {
    return visibleCount.value < filteredAndSortedItems.value.length;
});

function loadMore() {
    visibleCount.value += PAGE_SIZE;
}

function resetSearch() {
    searchQuery.value = '';
    debouncedSearch.value = '';
    visibleCount.value = PAGE_SIZE;
}

const categoryFilterLabel = computed(() => {
    switch (props.selectedCategoryFilter) {
        case 'production':
            return 'Produksi';
        case 'tpm':
            return 'TPM / Maintenance';
        case 'project':
            return 'CapEx Project';
        case 'others':
            return 'Lain-lain';
        default:
            return '';
    }
});

function getSpklBadgeClass(status: string) {
    switch (status) {
        case 'overdue':
            return 'border-red-300 bg-red-50 text-[#cc0000] dark:border-red-800 dark:bg-red-950/40 dark:text-red-300';
        case 'grace_period':
            return 'border-amber-300 bg-amber-50 text-amber-700 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-300';
        case 'approved':
            return 'border-emerald-300 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300';
        case 'none':
        default:
            return 'border-slate-200 bg-slate-100 text-slate-600 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400';
    }
}
</script>

<template>
    <div class="space-y-4" data-test="employee-summary-table-section">
        <Card class="border-border/70 shadow-2xs">
            <CardHeader class="p-5 pb-4 sm:p-6">
                <div
                    class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
                >
                    <div>
                        <CardTitle
                            class="flex items-center gap-2 text-lg font-bold"
                        >
                            <Users class="text-primary size-5" />
                            {{ __('Ringkasan Data Lembur Karyawan') }}
                        </CardTitle>
                        <CardDescription
                            class="text-muted-foreground mt-1 text-xs"
                        >
                            {{
                                __(
                                    'Daftar detail jam lembur, Indeks Burn, dan kepatuhan SPKL seluruh karyawan untuk periode :month.',
                                    { month: data?.month_name ?? '' },
                                )
                            }}
                        </CardDescription>
                    </div>

                    <!-- Search Bar & Active Filters -->
                    <div class="flex flex-wrap items-center gap-2">
                        <!-- Category Filter Pill (from Category Donut) -->
                        <div
                            v-if="selectedCategoryFilter"
                            class="flex items-center gap-1.5 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 dark:border-blue-800 dark:bg-blue-950/60 dark:text-blue-300"
                            data-test="active-category-filter-badge"
                        >
                            <Filter class="size-3" />
                            <span>{{ categoryFilterLabel }}</span>
                            <button
                                type="button"
                                class="ml-1 rounded-full p-0.5 hover:bg-blue-200/60"
                                :title="__('Hapus filter kategori')"
                                @click="emit('clear-category-filter')"
                            >
                                <X class="size-3" />
                            </button>
                        </div>

                        <!-- Instant Live Search Input -->
                        <div class="relative w-full sm:w-64">
                            <Search
                                class="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2"
                            />
                            <Input
                                v-model="searchQuery"
                                type="text"
                                :placeholder="
                                    __('Cari nama karyawan atau NPK...')
                                "
                                class="h-9 pr-8 pl-9 text-xs font-medium"
                                data-test="employee-table-search-input"
                            />
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="text-muted-foreground hover:text-foreground absolute top-1/2 right-2.5 -translate-y-1/2"
                                @click="resetSearch"
                            >
                                <X class="size-3.5" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Scope & Filter Summary Counter -->
                <div
                    class="text-muted-foreground flex flex-wrap items-center justify-between gap-2 pt-2 text-xs"
                >
                    <span class="font-mono tabular-nums">
                        {{
                            __('Menampilkan :shown dari :total karyawan', {
                                shown: filteredAndSortedItems.length,
                                total: data?.total_count ?? 0,
                            })
                        }}
                    </span>
                    <span class="text-muted-foreground text-[11px] italic">
                        {{
                            __(
                                'Klik baris karyawan untuk membuka preview dossier cepat.',
                            )
                        }}
                    </span>
                </div>
            </CardHeader>

            <CardContent class="p-0">
                <!-- Table Container with Horizontal Scroll -->
                <div class="border-border/70 overflow-x-auto border-t">
                    <table
                        class="w-full text-left text-xs"
                        data-test="employee-summary-table"
                    >
                        <thead
                            class="bg-slate-50 text-slate-700 select-none dark:bg-slate-800/80 dark:text-slate-300"
                        >
                            <tr>
                                <!-- Column 1: Employee Name & NPK -->
                                <th
                                    class="cursor-pointer px-4 py-3 font-semibold transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                                    data-test="sort-header-name"
                                    @click="toggleSort('name')"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <span>{{ __('Karyawan (NPK)') }}</span>
                                        <ArrowUp
                                            v-if="
                                                sortField === 'name' &&
                                                sortDirection === 'asc'
                                            "
                                            class="text-primary size-3.5"
                                        />
                                        <ArrowDown
                                            v-else-if="
                                                sortField === 'name' &&
                                                sortDirection === 'desc'
                                            "
                                            class="text-primary size-3.5"
                                        />
                                        <ArrowUpDown
                                            v-else
                                            class="size-3 text-slate-400"
                                        />
                                    </div>
                                </th>

                                <!-- Column 2: Section Code -->
                                <th class="px-3 py-3 font-semibold">
                                    <div class="flex items-center gap-1">
                                        <Layers class="size-3 text-slate-400" />
                                        <span>{{ __('Seksi') }}</span>
                                    </div>
                                </th>

                                <!-- Column 3: Burn Index % -->
                                <th
                                    class="min-w-36 cursor-pointer px-4 py-3 font-semibold transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                                    data-test="sort-header-burn-index"
                                    @click="toggleSort('burn_index')"
                                >
                                    <div class="flex items-center gap-1.5">
                                        <span>{{ __('Indeks Burn') }}</span>
                                        <ArrowUp
                                            v-if="
                                                sortField === 'burn_index' &&
                                                sortDirection === 'asc'
                                            "
                                            class="text-primary size-3.5"
                                        />
                                        <ArrowDown
                                            v-else-if="
                                                sortField === 'burn_index' &&
                                                sortDirection === 'desc'
                                            "
                                            class="text-primary size-3.5"
                                        />
                                        <ArrowUpDown
                                            v-else
                                            class="size-3 text-slate-400"
                                        />
                                    </div>
                                </th>

                                <!-- Column 4: Total Approved Hours -->
                                <th
                                    class="cursor-pointer px-4 py-3 text-right font-semibold transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                                    data-test="sort-header-total-hours"
                                    @click="toggleSort('total_hours')"
                                >
                                    <div
                                        class="flex items-center justify-end gap-1.5"
                                    >
                                        <span>{{ __('Total Jam') }}</span>
                                        <ArrowUp
                                            v-if="
                                                sortField === 'total_hours' &&
                                                sortDirection === 'asc'
                                            "
                                            class="text-primary size-3.5"
                                        />
                                        <ArrowDown
                                            v-else-if="
                                                sortField === 'total_hours' &&
                                                sortDirection === 'desc'
                                            "
                                            class="text-primary size-3.5"
                                        />
                                        <ArrowUpDown
                                            v-else
                                            class="size-3 text-slate-400"
                                        />
                                    </div>
                                </th>

                                <!-- Column 5: Category Mini Bar -->
                                <th class="min-w-36 px-4 py-3 font-semibold">
                                    <span>{{ __('Kategori') }}</span>
                                </th>

                                <!-- Column 6: SPKL Status Badge -->
                                <th class="px-3 py-3 text-center font-semibold">
                                    <span>{{ __('Status SPKL') }}</span>
                                </th>

                                <!-- Column 7: Actions -->
                                <th class="px-3 py-3 text-center font-semibold">
                                    <span>{{ __('Aksi') }}</span>
                                </th>
                            </tr>
                        </thead>

                        <tbody class="divide-border/60 divide-y">
                            <!-- Table Rows -->
                            <tr
                                v-for="emp in visibleItems"
                                :key="emp.id"
                                class="group cursor-pointer transition-colors hover:bg-slate-50 dark:hover:bg-slate-800/60"
                                data-test="employee-summary-row"
                                @click="openDrawer(emp)"
                            >
                                <!-- Name & NPK -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <span
                                            class="text-foreground group-hover:text-primary font-bold transition-colors"
                                        >
                                            {{ emp.name }}
                                        </span>
                                        <div
                                            class="text-muted-foreground flex items-center gap-1.5 font-mono text-[11px]"
                                        >
                                            <span>{{ emp.npk }}</span>
                                            <span>•</span>
                                            <span>{{ emp.job_position }}</span>
                                        </div>
                                    </div>
                                </td>

                                <!-- Section Code -->
                                <td
                                    class="px-3 py-3 font-mono text-xs font-medium whitespace-nowrap text-slate-600 dark:text-slate-400"
                                >
                                    <span :title="emp.section_name">
                                        {{ emp.section_code }}
                                    </span>
                                </td>

                                <!-- Burn Index Mini Bar -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <MiniProgressBar
                                        :percentage="emp.burn_index"
                                        :zone="emp.burn_zone"
                                        height-class="h-2"
                                    />
                                </td>

                                <!-- Total Hours -->
                                <td
                                    class="text-foreground px-4 py-3 text-right font-mono font-bold whitespace-nowrap tabular-nums"
                                >
                                    {{ emp.total_hours.toFixed(1) }}
                                    <span
                                        class="text-muted-foreground text-[11px] font-normal"
                                        >jam</span
                                    >
                                </td>

                                <!-- Category Mini Bar -->
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <MiniCategoryBar
                                        :categories="emp.categories"
                                        :total-hours="emp.total_hours"
                                        height-class="h-2"
                                    />
                                </td>

                                <!-- SPKL Status Badge -->
                                <td
                                    class="px-3 py-3 text-center whitespace-nowrap"
                                >
                                    <Badge
                                        variant="outline"
                                        :class="[
                                            'px-2 py-0.5 text-[11px] font-semibold',
                                            getSpklBadgeClass(emp.spkl_status),
                                        ]"
                                        data-test="spkl-status-badge"
                                    >
                                        {{ emp.spkl_status_label }}
                                    </Badge>
                                </td>

                                <!-- Action Button (Open Quick Drawer) -->
                                <td
                                    class="px-3 py-3 text-center whitespace-nowrap"
                                >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        class="hover:text-foreground h-7 w-7 p-0 text-slate-500"
                                        :title="__('Buka Quick Look')"
                                        @click.stop="openDrawer(emp)"
                                    >
                                        <Eye class="size-3.5" />
                                        <span class="sr-only">{{
                                            __('Lihat')
                                        }}</span>
                                    </Button>
                                </td>
                            </tr>

                            <!-- Empty State when filtering returns 0 rows -->
                            <tr v-if="visibleItems.length === 0">
                                <td
                                    colspan="7"
                                    class="text-muted-foreground py-12 text-center"
                                >
                                    <div
                                        class="flex flex-col items-center justify-center gap-2"
                                    >
                                        <AlertCircle
                                            class="size-8 text-slate-300 dark:text-slate-600"
                                        />
                                        <p
                                            class="text-foreground text-sm font-semibold"
                                        >
                                            {{
                                                __(
                                                    'Tidak ada data karyawan yang cocok',
                                                )
                                            }}
                                        </p>
                                        <p
                                            class="text-muted-foreground max-w-sm text-xs"
                                        >
                                            {{
                                                searchQuery
                                                    ? __(
                                                          'Tidak ditemukan karyawan dengan kata kunci pencarian tersebut.',
                                                      )
                                                    : __(
                                                          'Belum ada data lembur karyawan untuk lingkup dan periode yang dipilih.',
                                                      )
                                            }}
                                        </p>
                                        <Button
                                            v-if="
                                                searchQuery ||
                                                selectedCategoryFilter
                                            "
                                            variant="outline"
                                            size="sm"
                                            class="mt-2 text-xs"
                                            @click="
                                                () => {
                                                    resetSearch();
                                                    emit(
                                                        'clear-category-filter',
                                                    );
                                                }
                                            "
                                        >
                                            <RotateCcw class="mr-1.5 size-3" />
                                            {{ __('Reset Pencarian & Filter') }}
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer ("Muat Lebih Banyak") -->
                <div
                    v-if="hasMore"
                    class="border-border/70 flex items-center justify-center border-t bg-slate-50/50 p-4 dark:bg-slate-900/30"
                >
                    <Button
                        variant="outline"
                        size="sm"
                        class="px-4 text-xs font-semibold shadow-2xs"
                        data-test="load-more-employees-button"
                        @click="loadMore"
                    >
                        {{
                            __('Muat Lebih Banyak (:remaining Tersisa)', {
                                remaining:
                                    filteredAndSortedItems.length -
                                    visibleCount,
                            })
                        }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Slide-in Employee Quick-Look Drawer -->
        <EmployeeQuickDossierDrawer
            v-model:open="drawerOpen"
            :employee="selectedEmployee"
            :month-name="data?.month_name"
        />
    </div>
</template>
