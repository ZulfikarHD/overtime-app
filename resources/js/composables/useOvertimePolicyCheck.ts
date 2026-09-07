import { ref, watch, type Ref } from 'vue';
import { useDebounceFn } from '@vueuse/core';
import { policyCheck as policyCheckRoute } from '@/routes/overtime';

export interface PolicyWarningData {
    level: 'none' | 'warning' | 'danger';
    message: string;
    weekly_total: number;
    weeklyTotal?: number;
    consecutive_weeks: number;
    consecutiveWeeks?: number;
    weekly_limit: number;
    weeklyLimit?: number;
}

export function useOvertimePolicyCheck(
    employeeIdRef: Ref<number>,
    totalHoursRef: Ref<number>,
    operationalDateRef?: Ref<string | undefined>,
    submissionIdRef?: Ref<number | null | undefined>,
    initialWarning?: PolicyWarningData | null,
) {
    const warning = ref<PolicyWarningData | null>(initialWarning ?? null);
    const isLoading = ref(false);
    let lastCheckedKey = '';

    async function executeCheck() {
        const empId = employeeIdRef.value;
        if (!empId) {
            warning.value = null;
            return;
        }

        const hours = Number(totalHoursRef.value) || 0;
        const date = operationalDateRef?.value;
        const excludeId = submissionIdRef?.value;

        const checkKey = `${empId}_${hours}_${date ?? ''}_${excludeId ?? ''}`;
        if (checkKey === lastCheckedKey && warning.value !== null) {
            return;
        }

        isLoading.value = true;
        try {
            const url = policyCheckRoute.url({
                query: {
                    employee_id: empId,
                    additional_hours: hours,
                    ...(date ? { date } : {}),
                    ...(excludeId ? { exclude_submission_id: excludeId } : {}),
                },
            });

            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (response.ok) {
                const data = await response.json();
                warning.value = {
                    level: data.level ?? 'none',
                    message: data.message ?? '',
                    weekly_total: Number(
                        data.weekly_total ?? data.weeklyTotal ?? 0,
                    ),
                    weeklyTotal: Number(
                        data.weekly_total ?? data.weeklyTotal ?? 0,
                    ),
                    consecutive_weeks: Number(
                        data.consecutive_weeks ?? data.consecutiveWeeks ?? 0,
                    ),
                    consecutiveWeeks: Number(
                        data.consecutive_weeks ?? data.consecutiveWeeks ?? 0,
                    ),
                    weekly_limit: Number(
                        data.weekly_limit ?? data.weeklyLimit ?? 20,
                    ),
                    weeklyLimit: Number(
                        data.weekly_limit ?? data.weeklyLimit ?? 20,
                    ),
                };
                lastCheckedKey = checkKey;
            }
        } catch (error) {
            console.error('Failed to check overtime policy limits:', error);
        } finally {
            isLoading.value = false;
        }
    }

    const debouncedCheck = useDebounceFn(executeCheck, 500);

    // Watch totalHours and trigger debounced evaluation
    watch(
        () => totalHoursRef.value,
        () => {
            void debouncedCheck();
        },
    );

    // Watch employeeId or date changes
    watch(
        [() => employeeIdRef.value, () => operationalDateRef?.value],
        () => {
            void executeCheck();
        },
        { immediate: true },
    );

    return {
        warning,
        isLoading,
        checkPolicy: executeCheck,
    };
}
