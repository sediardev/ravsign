<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();

const steps = [
    { number: '01', text: 'Carga tus PDF y previsualízalos.' },
    {
        number: '02',
        text: 'Arrastra campos de firma y asígnalos a cada firmante.',
    },
    {
        number: '03',
        text: 'Firma con un trazo o con una imagen de tu firma.',
    },
];
</script>

<template>
    <div class="grid min-h-dvh desk:grid-cols-2">
        <div
            class="flex flex-col items-center justify-center px-5 py-8 sm:px-8"
        >
            <div class="flex w-full max-w-[400px] flex-col gap-[18px]">
                <Link :href="home()" class="mb-4 flex items-center">
                    <AppLogo />
                </Link>

                <div v-if="title || description" class="flex flex-col gap-1.5">
                    <h1
                        v-if="title"
                        class="text-3xl leading-tight font-semibold tracking-[-0.02em]"
                    >
                        {{ title }}
                    </h1>
                    <p v-if="description" class="text-[15px] text-muted-foreground">
                        {{ description }}
                    </p>
                </div>

                <slot />
            </div>
        </div>

        <div
            class="hidden flex-col justify-center gap-9 bg-foreground px-14 py-16 desk:flex"
        >
            <h2
                class="max-w-[440px] text-4xl leading-[1.15] font-semibold tracking-[-0.02em] text-white"
            >
                Sube, asigna y firma en un solo lugar.
            </h2>
            <ol class="flex max-w-[420px] flex-col gap-5">
                <li
                    v-for="step in steps"
                    :key="step.number"
                    class="flex items-baseline gap-4"
                >
                    <span
                        class="font-display text-sm font-semibold text-[#5fc0e0]"
                        >{{ step.number }}</span
                    >
                    <span class="text-[15px] leading-[1.55] text-[#dbe4ef]">{{
                        step.text
                    }}</span>
                </li>
            </ol>
        </div>
    </div>
</template>
