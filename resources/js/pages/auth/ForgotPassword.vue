<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Recover your password',
        description:
            "Enter your email and we'll send you a link to reset it.",
    },
});

defineProps<{
    status?: string;
}>();
</script>

<template>
    <Head title="Recover Password" />

    <div v-if="status" class="text-sm font-medium text-green-600">
        {{ status }}
    </div>

    <Form
        v-bind="email.form()"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-[18px]"
    >
        <div class="grid gap-1.5">
            <Label for="email">Email</Label>
            <Input
                id="email"
                type="email"
                name="email"
                autocomplete="off"
                v-focus
                placeholder="you@company.com"
            />
            <InputError :message="errors.email" />
        </div>

        <Button
            size="lg"
            class="mt-1 w-full"
            :disabled="processing"
            data-test="email-password-reset-link-button"
        >
            <Spinner v-if="processing" />
            Send recovery link
        </Button>

        <p class="text-center text-sm text-muted-foreground">
            Remembered it?
            <TextLink :href="login()">Log in</TextLink>
        </p>
    </Form>
</template>
