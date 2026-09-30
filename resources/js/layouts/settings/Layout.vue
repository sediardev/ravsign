<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { toUrl } from '@/lib/utils';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
    },
    {
        title: 'Security',
        href: editSecurity(),
    },
];

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div
        class="px-[clamp(16px,4vw,40px)] py-[clamp(16px,4vw,32px)]"
    >
        <Heading
            title="Settings"
            description="Manage your profile and account security"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Settings"
                >
                    <Link
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        :href="item.href"
                        class="rounded-lg px-3 py-[11px] text-sm transition-colors"
                        :class="
                            isCurrentOrParentUrl(item.href)
                                ? 'bg-accent font-semibold text-foreground'
                                : 'font-medium text-muted-foreground hover:bg-accent/60 hover:text-foreground'
                        "
                    >
                        {{ item.title }}
                    </Link>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section
                    class="max-w-xl space-y-12 rounded-[14px] border border-border bg-white p-[clamp(16px,3vw,28px)]"
                >
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
