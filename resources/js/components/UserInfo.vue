<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import type { User } from '@/types';

type Props = {
    user: User;
    showEmail?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    showEmail: false,
});

const initial = computed(() =>
    (props.user.name.trim()[0] ?? 'U').toUpperCase(),
);

// Compute whether we should show the avatar image
const showAvatar = computed(
    () => props.user.avatar && props.user.avatar !== '',
);
</script>

<template>
    <Avatar class="size-[34px] shrink-0 overflow-hidden rounded-full">
        <AvatarImage v-if="showAvatar" :src="user.avatar!" :alt="user.name" />
        <AvatarFallback
            class="rounded-full bg-foreground text-sm font-bold text-white"
        >
            {{ initial }}
        </AvatarFallback>
    </Avatar>

    <div class="grid min-w-0 flex-1 text-left leading-tight">
        <span class="truncate text-sm font-semibold">{{ user.name }}</span>
        <span v-if="showEmail" class="truncate text-xs text-muted-foreground">{{
            user.email
        }}</span>
    </div>
</template>
