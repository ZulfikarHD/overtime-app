import { ref, watch, type Ref } from 'vue';
import { roster as rosterRoute } from '@/routes/overtime/submissions';

export interface RosterEmployee {
    id: number;
    npk: string;
    full_name: string;
    job_position: string;
    hourly_rate: number | string | null;
}

export interface BurnIndicator {
    planned_hours: number;
    actual_hours: number;
    burn_pct: number;
    burn_zone: 'safe' | 'caution' | 'danger';
}

export function useSectionRoster(
    sectionIdRef: Ref<number | null | undefined>,
    initialEmployees: RosterEmployee[] = [],
    initialBurnIndicator?: BurnIndicator,
) {
    const roster = ref<RosterEmployee[]>([...initialEmployees]);
    const burnIndicator = ref<BurnIndicator>(
        initialBurnIndicator ?? {
            planned_hours: 0,
            actual_hours: 0,
            burn_pct: 0,
            burn_zone: 'safe',
        },
    );
    const isLoading = ref(false);

    watch(
        () => initialEmployees,
        (newVal) => {
            if (newVal && newVal.length > 0) {
                roster.value = [...newVal];
            }
        },
        { immediate: true },
    );

    async function fetchRoster(sectionId?: number | null) {
        const id = sectionId ?? sectionIdRef.value;
        if (!id) {
            roster.value = [];
            return;
        }

        isLoading.value = true;
        try {
            const url = rosterRoute.url({ section: id });
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                const data = (await response.json()) as {
                    employees: RosterEmployee[];
                    burn_indicator: BurnIndicator;
                };
                roster.value = data.employees;
                if (data.burn_indicator) {
                    burnIndicator.value = data.burn_indicator;
                }
            }
        } catch (error) {
            console.error('Failed to fetch section roster:', error);
        } finally {
            isLoading.value = false;
        }
    }

    watch(sectionIdRef, (newId, oldId) => {
        if (newId !== oldId && newId) {
            void fetchRoster(newId);
        } else if (!newId) {
            roster.value = [];
        }
    });

    return {
        roster,
        burnIndicator,
        isLoading,
        fetchRoster,
    };
}
