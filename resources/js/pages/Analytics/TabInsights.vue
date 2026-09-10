<script setup lang="ts">
import {
    Activity,
    AlertCircle,
    CheckCircle2,
    Clock,
    FileSpreadsheet,
    ListTodo,
    RefreshCw,
    ShieldAlert,
} from '@lucide/vue';
import { computed, onMounted, ref, watch } from 'vue';
import ActionItemStatusModal, {
    type ActionItemTarget,
} from '@/components/analytics/ActionItemStatusModal.vue';
import AnomalyDetectionChart, {
    type AnomalyDetectionData,
} from '@/components/analytics/AnomalyDetectionChart.vue';
import ManagementActionTable from '@/components/analytics/ManagementActionTable.vue';
import RiskIndicatorPanel, {
    type RiskIndicatorsData,
} from '@/components/analytics/RiskIndicatorPanel.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTrans } from '@/composables/useTrans';
import {
    exportMethod as analyticsExport,
    insights as insightsRoute,
} from '@/routes/analytics';

export interface InsightsData {
    risk_indicators: RiskIndicatorsData;
    anomaly_detection: AnomalyDetectionData;
    action_items: ActionItemTarget[];
    scope: {
        department_id: number | null;
        department_name: string;
        start_date: string;
        end_date: string;
        fiscal_year: number;
        fiscal_month: number;
    };
}

interface Props {
    filters?: {
        department_id?: string | number | null;
        start_date?: string;
        end_date?: string;
    };
    initialData?: unknown;
}

const props = withDefaults(defineProps<Props>(), {
    filters: () => ({
        department_id: 'all',
        start_date: '',
        end_date: '',
    }),
    initialData: undefined,
});

const { __ } = useTrans();

const insightsData = ref<InsightsData | null>(null);
const isLoading = ref(false);
const error = ref<string | null>(null);

const selectedActionItem = ref<ActionItemTarget | null>(null);
const isStatusModalOpen = ref(false);

const parsedInitialData = computed<InsightsData | null>(() => {
    if (!props.initialData || typeof props.initialData !== 'object') {
        return null;
    }
    const d = props.initialData as Partial<InsightsData>;
    if (
        d.risk_indicators &&
        d.anomaly_detection &&
        Array.isArray(d.action_items)
    ) {
        return d as InsightsData;
    }
    return null;
});

async function fetchInsightsData() {
    isLoading.value = true;
    error.value = null;

    try {
        const queryParams: Record<string, string | number> = {};
        if (
            props.filters.department_id &&
            props.filters.department_id !== 'all'
        ) {
            queryParams.department_id = props.filters.department_id;
        }
        if (props.filters.start_date) {
            queryParams.start_date = props.filters.start_date;
        }
        if (props.filters.end_date) {
            queryParams.end_date = props.filters.end_date;
        }

        const url = insightsRoute.url({ query: queryParams });
        const response = await fetch(url, {
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (!response.ok) {
            throw new Error(
                `HTTP error ${response.status}: ${__('Gagal memuat data wawasan kunci.')}`,
            );
        }

        const data = await response.json();
        insightsData.value = data as InsightsData;
    } catch (err: unknown) {
        const e = err as Error;
        error.value =
            e.message ||
            __('Terjadi kesalahan saat memuat data wawasan kunci.');
    } finally {
        isLoading.value = false;
    }
}

onMounted(() => {
    if (parsedInitialData.value) {
        insightsData.value = parsedInitialData.value;
    } else {
        fetchInsightsData();
    }
});

watch(
    () => props.filters,
    () => {
        fetchInsightsData();
    },
    { deep: true },
);

function handleOpenStatusModal(item: ActionItemTarget) {
    selectedActionItem.value = item;
    isStatusModalOpen.value = true;
}

function handleActionItemUpdated(payload: {
    id: string;
    status: 'pending' | 'in_progress' | 'resolved';
    resolution_note: string | null;
    updated_at: string;
}) {
    if (!insightsData.value) {
        return;
    }

    const item = insightsData.value.action_items.find(
        (i) => i.id === payload.id,
    );
    if (item) {
        item.status = payload.status;
        item.resolution_note = payload.resolution_note;
        item.updated_at = payload.updated_at;
        item.status_label =
            payload.status === 'resolved'
                ? __('Selesai')
                : payload.status === 'in_progress'
                  ? __('Dalam Pengerjaan')
                  : __('Tertunda');
    }
}

function handleExportCsv() {
    const queryParams: Record<string, string | number> = {
        format: 'csv',
        tab: 'insights',
    };

    if (props.filters.department_id && props.filters.department_id !== 'all') {
        queryParams.department_id = props.filters.department_id;
    }
    if (props.filters.start_date) {
        queryParams.start_date = props.filters.start_date;
    }
    if (props.filters.end_date) {
        queryParams.end_date = props.filters.end_date;
    }

    const downloadUrl = analyticsExport.url({ query: queryParams });
    window.location.href = downloadUrl;
}
</script>

<template>
    <div class="space-y-6" data-test="tab-insights-content">
        <!-- Error Notification Banner -->
        <div
            v-if="error"
            class="flex items-center justify-between rounded-lg border border-red-200 bg-red-50 p-4 text-xs text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
            data-test="insights-error-banner"
        >
            <div class="flex items-center gap-2">
                <AlertCircle class="size-4 shrink-0 text-[#cc0000]" />
                <span>{{ error }}</span>
            </div>
            <Button
                variant="outline"
                size="sm"
                class="h-7 border-red-300 text-xs text-red-900 hover:bg-red-100"
                @click="fetchInsightsData"
            >
                <RefreshCw class="mr-1 size-3" />
                <span>{{ __('Coba Lagi') }}</span>
            </Button>
        </div>

        <!-- 1. Risk Indicators Panel (E09-11) -->
        <RiskIndicatorPanel
            :data="insightsData?.risk_indicators"
            :loading="isLoading"
        />

        <!-- 2. 30-Day Anomaly Detection Line Chart (E09-11) -->
        <AnomalyDetectionChart
            :data="insightsData?.anomaly_detection"
            :loading="isLoading"
        />

        <!-- 3. Management Action Items Table (E09-11) -->
        <ManagementActionTable
            :items="insightsData?.action_items || []"
            :loading="isLoading"
            @select-item="handleOpenStatusModal"
            @export-csv="handleExportCsv"
        />

        <!-- 4. Single-Level Action Item Status Modal (E09-11) -->
        <ActionItemStatusModal
            v-model:open="isStatusModalOpen"
            :action-item="selectedActionItem"
            @updated="handleActionItemUpdated"
        />
    </div>
</template>
