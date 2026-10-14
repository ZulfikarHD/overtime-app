<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { useTrans } from '@/composables/useTrans';

const { __ } = useTrans();
</script>

<template>
    <div class="space-y-6" data-test="early-warning-index">
        <Head :title="__('Early Warning')" />

        <Heading
            :title="__('Early Warning')"
            :description="
                __(
                    'Alerts from the daily overtime index that need a follow-up.',
                )
            "
        />
    </div>
</template>
