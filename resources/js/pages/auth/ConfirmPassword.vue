<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/password/confirm';

defineOptions({
    layout: {
        title: 'Confirma tu contraseña',
        description:
            'Esta es una zona segura de la aplicación. Confirma tu contraseña para continuar.',
    },
});
</script>

<template>
    <Head title="Confirmar contraseña" />

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
        class="flex flex-col gap-[18px]"
    >
        <div class="grid gap-1.5">
            <Label for="password">Contraseña</Label>
            <PasswordInput
                id="password"
                name="password"
                required
                autocomplete="current-password"
                autofocus
            />
            <InputError :message="errors.password" />
        </div>

        <Button
            size="lg"
            class="mt-1 w-full"
            :disabled="processing"
            data-test="confirm-password-button"
        >
            <Spinner v-if="processing" />
            Confirmar contraseña
        </Button>
    </Form>
</template>
