<script setup lang="ts">
import AppMobileHeader from '@/components/AppMobileHeader.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import { Toaster } from '@/components/ui/sonner';
</script>

<template>
    <div class="flex min-h-dvh bg-muted">
        <AppSidebar />
        <div class="flex min-w-0 flex-1 flex-col">
            <AppMobileHeader />
            <main class="flex min-w-0 flex-1 flex-col">
                <slot />
            </main>
        </div>
        <Toaster />
    </div>
</template>
