<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineOptions({
    layout: {
        title: 'Log in',
        description: 'Enter your email and password to continue.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Log in" />

    <div v-if="status" class="text-sm font-medium text-green-600">
        {{ status }}
    </div>

    <Form
        v-bind="store.form()"
        :reset-on-success="['password']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-[18px]"
    >
        <div class="grid gap-1.5">
            <Label for="email">Email</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                v-focus
                :tabindex="1"
                autocomplete="email"
                placeholder="you@company.com"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-1.5">
            <div class="flex items-center justify-between">
                <Label for="password">Password</Label>
                <TextLink
                    v-if="canResetPassword"
                    :href="request()"
                    class="text-[13px]"
                    :tabindex="5"
                >
                    Forgot your password?
                </TextLink>
            </div>
            <PasswordInput
                id="password"
                name="password"
                required
                :tabindex="2"
                autocomplete="current-password"
                placeholder="Your password"
            />
            <InputError :message="errors.password" />
        </div>

        <Label for="remember" class="flex items-center gap-2.5 font-medium">
            <Checkbox id="remember" name="remember" :tabindex="3" />
            <span>Remember me</span>
        </Label>

        <Button
            type="submit"
            size="lg"
            class="mt-1 w-full"
            :tabindex="4"
            :disabled="processing"
            data-test="login-button"
        >
            <Spinner v-if="processing" />
            Log in
        </Button>

        <p class="text-center text-sm text-muted-foreground">
            Don't have an account?
            <TextLink :href="register()" :tabindex="6">Create one</TextLink>
        </p>
    </Form>
</template>
