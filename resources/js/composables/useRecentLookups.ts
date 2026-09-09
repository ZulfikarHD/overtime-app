import { onMounted, ref } from 'vue';

export interface RecentLookupEmployee {
    id?: number;
    npk: string;
    name: string;
    job_position?: string;
    department_name?: string;
    section_name?: string;
}

const STORAGE_KEY = 'recentLookups';
const MAX_LOOKUPS = 5;

export function useRecentLookups() {
    const recentLookups = ref<RecentLookupEmployee[]>([]);

    const loadLookups = () => {
        if (typeof window === 'undefined' || !window.localStorage) {
            return;
        }

        try {
            const raw = localStorage.getItem(STORAGE_KEY);
            if (raw) {
                const parsed = JSON.parse(raw);
                if (Array.isArray(parsed)) {
                    recentLookups.value = parsed.slice(0, MAX_LOOKUPS);
                }
            } else {
                recentLookups.value = [];
            }
        } catch {
            recentLookups.value = [];
        }
    };

    const addLookup = (employee: RecentLookupEmployee) => {
        if (
            typeof window === 'undefined' ||
            !window.localStorage ||
            !employee.npk
        ) {
            return;
        }

        try {
            loadLookups();
            const filtered = recentLookups.value.filter(
                (item) => item.npk.toUpperCase() !== employee.npk.toUpperCase(),
            );

            const updated: RecentLookupEmployee[] = [
                {
                    id: employee.id,
                    npk: employee.npk,
                    name: employee.name,
                    job_position: employee.job_position,
                    department_name: employee.department_name,
                    section_name: employee.section_name,
                },
                ...filtered,
            ].slice(0, MAX_LOOKUPS);

            recentLookups.value = updated;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(updated));
        } catch {
            // Graceful fallback for storage quota or disabled storage
        }
    };

    const clearLookups = () => {
        if (typeof window === 'undefined' || !window.localStorage) {
            return;
        }

        try {
            localStorage.removeItem(STORAGE_KEY);
            recentLookups.value = [];
        } catch {
            // Graceful fallback
        }
    };

    onMounted(() => {
        loadLookups();
    });

    return {
        recentLookups,
        loadLookups,
        addLookup,
        clearLookups,
    };
}
