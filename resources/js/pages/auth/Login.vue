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
        title: 'Inicia sesión',
        description: 'Ingresa tu correo y contraseña para continuar.',
    },
});

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();
</script>

<template>
    <Head title="Iniciar sesión" />

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
            <Label for="email">Correo electrónico</Label>
            <Input
                id="email"
                type="email"
                name="email"
                required
                v-focus
                :tabindex="1"
                autocomplete="email"
                placeholder="tu@empresa.com"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-1.5">
            <div class="flex items-center justify-between">
                <Label for="password">Contraseña</Label>
                <TextLink
                    v-if="canResetPassword"
                    :href="request()"
                    class="text-[13px]"
                    :tabindex="5"
                >
                    ¿Olvidaste tu contraseña?
                </TextLink>
            </div>
            <PasswordInput
                id="password"
                name="password"
                required
                :tabindex="2"
                autocomplete="current-password"
                placeholder="Tu contraseña"
            />
            <InputError :message="errors.password" />
        </div>

        <Label for="remember" class="flex items-center gap-2.5 font-medium">
            <Checkbox id="remember" name="remember" :tabindex="3" />
            <span>Recordarme</span>
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
            Iniciar sesión
        </Button>

        <p class="text-center text-sm text-muted-foreground">
            ¿No tienes cuenta?
            <TextLink :href="register()" :tabindex="6">Crea una</TextLink>
        </p>
    </Form>
</template>
