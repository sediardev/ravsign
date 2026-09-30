<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Confirm your email',
        description: "You're one step away from signing documents.",
    },
});

defineProps<{
    email: string;
    status?: string;
}>();

function handleLogout(): void {
    router.flushAll();
}
</script>

<template>
    <Head title="Confirm Your Email" />

    <div
        v-if="status === 'verification-link-sent'"
        class="text-sm font-medium text-green-600"
        data-test="verify-email-status"
    >
        We sent the confirmation link again.
    </div>

    <p class="text-[15px] text-muted-foreground">
        We sent a confirmation link to
        <span class="font-semibold text-foreground">{{ email }}</span
        >. Open it to activate your account and access Documents.
    </p>

    <Form v-bind="send.form()" v-slot="{ processing }" class="flex flex-col gap-[18px]">
        <Button
            type="submit"
            size="lg"
            class="mt-1 w-full"
            :disabled="processing"
            data-test="resend-verification-button"
        >
            <Spinner v-if="processing" />
            Resend email
        </Button>
    </Form>

    <p class="text-center">
        <Link
            class="text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
            :href="logout()"
            as="button"
            @click="handleLogout"
            data-test="logout-button"
        >
            Log out
        </Link>
    </p>
</template>
