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
import { store } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

defineOptions({
    layout: {
        title: 'Crea tu cuenta',
        description: 'Empieza a enviar documentos para firma.',
    },
});
</script>

<template>
    <Head title="Crear cuenta" />

    <Form
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-[18px]"
    >
        <div class="grid gap-1.5">
            <Label for="name">Nombre completo</Label>
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
            <Label for="email">Correo electrónico</Label>
            <Input
                id="email"
                type="email"
                required
                :tabindex="2"
                autocomplete="email"
                name="email"
                placeholder="tu@empresa.com"
            />
            <InputError :message="errors.email" />
        </div>

        <div class="grid gap-1.5">
            <Label for="password">Contraseña</Label>
            <PasswordInput
                id="password"
                required
                :tabindex="3"
                autocomplete="new-password"
                name="password"
                placeholder="Mínimo 8 caracteres"
                :passwordrules="passwordRules"
            />
            <InputError :message="errors.password" />
        </div>

        <div class="grid gap-1.5">
            <Label for="password_confirmation">Confirmar contraseña</Label>
            <PasswordInput
                id="password_confirmation"
                required
                :tabindex="4"
                autocomplete="new-password"
                name="password_confirmation"
                placeholder="Repite tu contraseña"
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
                Acepto los
                <a href="#" class="font-semibold text-primary">
                    Términos de servicio
                </a>
                y la
                <a href="#" class="font-semibold text-primary">
                    Política de privacidad
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
            Crear cuenta
        </Button>

        <p class="text-center text-sm text-muted-foreground">
            ¿Ya tienes cuenta?
            <TextLink :href="login()" :tabindex="7">Inicia sesión</TextLink>
        </p>
    </Form>
</template>
