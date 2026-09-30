<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { home, login } from '@/routes';

const page = usePage();
// The user is null while the web routes run without the `auth` middleware.
const user = computed(() => page.props.auth.user as typeof page.props.auth.user | null);
const initial = computed(() =>
    (user.value?.name.trim()[0] ?? 'U').toUpperCase(),
);
</script>

<template>
    <header
        class="flex items-center justify-between gap-3 border-b border-border bg-white px-4 py-3 desk:hidden"
    >
        <Link :href="home()" class="flex items-center">
            <AppLogo />
        </Link>
        <Link
            v-if="!user"
            :href="login()"
            class="text-sm font-semibold text-primary"
        >
            Log in
        </Link>
        <DropdownMenu v-else>
            <DropdownMenuTrigger as-child>
                <button
                    type="button"
                    class="size-[38px] rounded-full bg-foreground text-sm font-bold text-white outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    :aria-label="`${user.name}'s account`"
                    data-test="mobile-menu-button"
                >
                    {{ initial }}
                </button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" class="min-w-56 rounded-lg">
                <UserMenuContent :user="user" />
            </DropdownMenuContent>
        </DropdownMenu>
    </header>
</template>
