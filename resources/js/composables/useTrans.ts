import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useTrans() {
    const page = usePage();

    const translations = computed(
        () => (page.props.translations as Record<string, string>) ?? {},
    );

    const locale = computed(() => (page.props.locale as string) ?? 'id');

    function __(
        key: string,
        replace: Record<string, string | number> = {},
    ): string {
        let translation = translations.value[key] ?? key;

        for (const [placeholder, value] of Object.entries(replace)) {
            translation = translation.replaceAll(
                `:${placeholder}`,
                String(value),
            );
        }

        return translation;
    }

    return {
        __,
        locale,
        translations,
    };
}
