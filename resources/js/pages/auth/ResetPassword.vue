<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Reset your password',
        description: 'Enter your new password.',
    },
});

const props = defineProps<{
    token: string;
    email: string;
    passwordRules: string;
}>();

const inputEmail = ref(props.email);
</script>

<template>
    <Head title="Reset Password" />

    <Form
        v-bind="update.form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-[18px]"
    >
        <div class="grid gap-1.5">
            <Label for="email">Email</Label>
            <Input
                id="email"
                type="email"
                name="email"
                autocomplete="email"
                v-model="inputEmail"
                class="bg-muted"
                readonly
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-1.5">
            <Label for="password">New password</Label>
            <PasswordInput
                id="password"
                name="password"
                autocomplete="new-password"
                autofocus
                placeholder="At least 8 characters"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-1.5">
            <Label for="password_confirmation">Confirm password</Label>
            <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                autocomplete="new-password"
                placeholder="Repeat your password"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <Button
            type="submit"
            size="lg"
            class="mt-1 w-full"
            :disabled="processing"
            data-test="reset-password-button"
        >
            <Spinner v-if="processing" />
            Reset password
        </Button>
    </Form>
</template>
