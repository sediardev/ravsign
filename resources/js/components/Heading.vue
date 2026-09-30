<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header :class="variant === 'small' ? '' : 'mb-8 space-y-1'">
        <h2
            :class="
                variant === 'small'
                    ? 'mb-0.5 text-lg font-semibold'
                    : 'text-[clamp(24px,5vw,28px)] font-semibold tracking-[-0.01em]'
            "
        >
            {{ title }}
        </h2>
        <p v-if="description" class="text-sm text-muted-foreground">
            {{ description }}
        </p>
    </header>
</template>
