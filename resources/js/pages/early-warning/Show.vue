<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { useTrans } from '@/composables/useTrans';
import { index as earlyWarningIndex } from '@/routes/early-warning';

defineProps<{
    section: {
        id: number;
        name: string;
        department_id: number;
        department_name: string | null;
    };
    date: string;
}>();

const { __ } = useTrans();
</script>

<template>
    <div class="space-y-6" data-test="early-warning-show">
        <Head :title="__('Early Warning')" />

        <Link
            :href="earlyWarningIndex()"
            class="text-muted-foreground hover:text-foreground text-sm"
            data-test="early-warning-back"
        >
            {{ __('Back to Early Warning') }}
        </Link>

        <Heading
            :title="__('Daily Index — :section', { section: section.name })"
            :description="date"
        />
    </div>
</template>
