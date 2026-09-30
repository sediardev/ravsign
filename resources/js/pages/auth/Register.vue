<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { privacy, terms } from '@/routes/legal';
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Create your account',
        description: 'Start sending documents for signature.',
    },
});
</script>

<template>
    <Head title="Create Account" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-[18px]"
    >
        <div class="grid gap-1.5">
            <Label for="name">Full name</Label>
            <Input
                id="name"
                type="text"
                required
                v-focus
                :tabindex="1"
                autocomplete="name"
                name="name"
            />
            <InputError :message="errors.name" />
        </div>

        <div class="grid gap-1.5">
            <Label for="email">Email</Label>
            <Input
                id="email"
                type="email"
                required
                :tabindex="2"
                autocomplete="email"
                name="email"
                placeholder="you@company.com"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-1.5">
            <Label for="password">Password</Label>
            <PasswordInput
                id="password"
                required
                :tabindex="3"
                autocomplete="new-password"
                name="password"
                placeholder="At least 8 characters"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-1.5">
            <Label for="password_confirmation">Confirm password</Label>
            <PasswordInput
                id="password_confirmation"
                required
                :tabindex="4"
                autocomplete="new-password"
                name="password_confirmation"
                placeholder="Repeat your password"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password_confirmation" />
        </div>

        <label
            class="flex items-start gap-2.5 text-[13px] leading-normal text-muted-foreground"
        >
            <input
                type="checkbox"
                required
                class="mt-[3px] accent-primary"
                :tabindex="5"
            />
            <span>
                I accept the
                <a
                    :href="terms().url"
                    target="_blank"
                    rel="noopener"
                    class="font-semibold text-primary"
                >
                    Terms of Service
                </a>
                and the
                <a
                    :href="privacy().url"
                    target="_blank"
                    rel="noopener"
                    class="font-semibold text-primary"
                >
                    Privacy Policy
                </a>
                .
            </span>
        </label>

        <Button
            type="submit"
            size="lg"
            class="mt-1 w-full"
            :tabindex="6"
            :disabled="processing"
            data-test="register-user-button"
        >
            <Spinner v-if="processing" />
            Create account
        </Button>

        <p class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink :href="login()" :tabindex="7">Log in</TextLink>
        </p>
    </Form>
</template>
