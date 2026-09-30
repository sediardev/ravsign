<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const name = usePage().props.name;
</script>

<template>
    <AppLogoIcon class="h-[30px] w-auto shrink-0" />
    <span
        class="ml-2.5 truncate font-display text-xl leading-tight font-semibold text-foreground"
        >{{ name }}</span
    >
</template>
