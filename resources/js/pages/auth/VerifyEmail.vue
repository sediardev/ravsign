<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineOptions({
    layout: {
        title: 'Confirma tu correo',
        description: 'Te falta un paso para empezar a firmar documentos.',
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
    <Head title="Confirma tu correo" />

    <div
        v-if="status === 'verification-link-sent'"
        class="text-sm font-medium text-green-600"
        data-test="verify-email-status"
    >
        Te reenviamos el enlace de confirmación.
    </div>

    <p class="text-[15px] text-muted-foreground">
        Te enviamos un enlace de confirmación a
        <span class="font-semibold text-foreground">{{ email }}</span
        >. Ábrelo para activar tu cuenta y entrar a Documentos.
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
            Reenviar correo
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
            Cerrar sesión
        </Link>
    </p>
</template>
