<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronsUpDown } from '@lucide/vue';
import { computed } from 'vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserInfo from '@/components/UserInfo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { login } from '@/routes';

const page = usePage();
// The user is null while the web routes run without the `auth` middleware.
const user = computed(() => page.props.auth.user as typeof page.props.auth.user | null);
</script>

<template>
    <Link
        v-if="!user"
        :href="login()"
        class="block rounded-lg px-3 py-[11px] text-sm font-semibold text-primary hover:bg-accent"
    >
        Iniciar sesión
    </Link>
    <DropdownMenu v-else>
        <DropdownMenuTrigger as-child>
            <button
                type="button"
                class="flex w-full items-center gap-2.5 rounded-lg px-2 py-3 text-left outline-none hover:bg-accent focus-visible:ring-[3px] focus-visible:ring-ring/50 data-[state=open]:bg-accent"
                data-test="sidebar-menu-button"
            >
                <UserInfo :user="user" />
                <ChevronsUpDown class="size-4 shrink-0 text-muted-foreground" />
            </button>
        </DropdownMenuTrigger>
        <DropdownMenuContent
            class="w-(--reka-dropdown-menu-trigger-width) min-w-56 rounded-lg"
            side="top"
            align="start"
            :side-offset="4"
        >
            <UserMenuContent :user="user" />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
