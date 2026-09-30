<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import NavUser from '@/components/NavUser.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { home } from '@/routes';
import { index as documents } from '@/routes/documents';

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <aside
        class="sticky top-0 hidden h-dvh w-60 shrink-0 flex-col gap-7 border-r border-border bg-sidebar px-4 py-6 desk:flex"
    >
        <Link :href="home()" class="flex items-center px-2">
            <AppLogo />
        </Link>

        <nav class="flex flex-col gap-1" aria-label="Principal">
            <Link
                :href="documents()"
                class="rounded-lg px-3 py-[11px] text-sm transition-colors"
                :class="
                    isCurrentOrParentUrl(documents())
                        ? 'bg-accent font-semibold text-foreground'
                        : 'font-medium text-muted-foreground hover:bg-accent/60 hover:text-foreground'
                "
            >
                Documentos
            </Link>
        </nav>

        <div class="mt-auto border-t border-border pt-3">
            <NavUser />
        </div>
    </aside>
</template>
