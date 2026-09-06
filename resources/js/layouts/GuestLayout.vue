<script setup lang="ts">
import AuthLayout from '@/layouts/AuthLayout.vue';

withDefaults(
    defineProps<{
        title?: string;
        description?: string;
    }>(),
    {
        title: '',
        description: '',
    },
);
</script>

<template>
    <AuthLayout :title="title" :description="description">
        <slot />
    </AuthLayout>
</template>
