<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    passwordRules: string;
}>();
</script>

<template>
    <Head title="Security" />

    <h1 class="sr-only">Security</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            title="Change password"
            description="Use a long, random password to keep your account secure"
        />

        <Form
            v-bind="SecurityController.update.form()"
            :options="{
                preserveScroll: true,
            }"
            reset-on-success
            :reset-on-error="[
                'password',
                'password_confirmation',
                'current_password',
            ]"
            class="space-y-[18px]"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-1.5">
                <Label for="current_password">Current password</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="block w-full"
                    autocomplete="current-password"
                    placeholder="Your current password"
                />
                <InputError :message="errors.current_password" />
            </div>

            <div class="grid gap-1.5">
                <Label for="password">New password</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="block w-full"
                    autocomplete="new-password"
                    placeholder="At least 8 characters"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-1.5">
                <Label for="password_confirmation">Confirm password</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="block w-full"
                    autocomplete="new-password"
                    placeholder="Repeat your new password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="processing"
                    data-test="update-password-button"
                >
                    Save
                </Button>
            </div>
        </Form>
    </div>
</template>
