<script setup lang="ts">
import { computed } from 'vue';
import { useBurnZone } from '@/composables/useBurnZone';
import { useTrans } from '@/composables/useTrans';

const props = defineProps<{
    zone: string;
}>();

const { __ } = useTrans();
const { styleOf, normalizeBurnZone } = useBurnZone();

const style = computed(() => styleOf(props.zone));
const zone = computed(() => normalizeBurnZone(props.zone));
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-bold"
        :class="style.badge"
        :data-zone="zone"
        data-test="alert-zone-badge"
    >
        <span class="size-1.5 rounded-full" :class="style.solid" />
        {{ __(style.label) }}
    </span>
</template>
