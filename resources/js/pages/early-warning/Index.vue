<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AlertFilterBar from '@/components/early-warning/AlertFilterBar.vue';
import AlertList from '@/components/early-warning/AlertList.vue';
import AlertSummaryStrip from '@/components/early-warning/AlertSummaryStrip.vue';
import Heading from '@/components/Heading.vue';
import { useTrans } from '@/composables/useTrans';
import type {
    AlertPage,
    AlertSummary,
    DepartmentOption,
    InboxFilters,
    SectionOption,
} from '@/lib/earlyWarning';
import { dashboard } from '@/routes';
import { index as earlyWarningIndex } from '@/routes/early-warning';
import type { User } from '@/types';

const props = defineProps<{
    filters: InboxFilters;
    departments: DepartmentOption[];
    sections: SectionOption[];
    /** Deferred: undefined until the server finished computing the alerts. */
    alerts?: AlertPage;
    /** Deferred: undefined until the server finished computing the alerts. */
    summary?: AlertSummary;
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Early Warning', href: earlyWarningIndex() },
        ],
    },
});

const { __ } = useTrans();
const page = usePage();

const departmentLocked = computed(() => {
    const user = page.props.auth?.user as User | null | undefined;

    return user?.role !== 'admin';
});

/**
 * Filters live in the URL: defaults ("all", no section) are left out so links stay short and shareable.
 */
function visit(filters: InboxFilters): void {
    const query: Record<string, string | number> = {
        date_from: filters.date_from,
        date_to: filters.date_to,
    };

    if (!departmentLocked.value && filters.department_id !== null) {
        query.department_id = filters.department_id;
    }

    if (filters.section_id !== null) {
        query.section_id = filters.section_id;
    }

    if (filters.zone !== 'all') {
        query.zone = filters.zone;
    }

    if (filters.status !== 'all') {
        query.status = filters.status;
    }

    if (filters.page > 1) {
        query.page = filters.page;
    }

    router.get(earlyWarningIndex.url(), query, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function changePage(pageNumber: number): void {
    visit({ ...props.filters, page: pageNumber });
}

function resetFilters(): void {
    router.get(
        earlyWarningIndex.url(),
        {},
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <div class="space-y-6 p-4 md:p-6" data-test="early-warning-index">
        <Head :title="__('Early Warning')" />

        <Heading
            :title="__('Early Warning')"
            :description="
                __(
                    'Alerts from the daily overtime index that need a follow-up.',
                )
            "
            class="mb-0"
        />

        <AlertFilterBar
            :filters="filters"
            :departments="departments"
            :sections="sections"
            :department-locked="departmentLocked"
            @change="visit"
            @reset="resetFilters"
        />

        <AlertSummaryStrip :summary="summary" />

        <AlertList :alerts="alerts" @page="changePage" />
    </div>
</template>
