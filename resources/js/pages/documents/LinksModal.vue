<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Copy, Download, ExternalLink, Mail } from '@lucide/vue';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { api, ApiError } from '@/lib/api';
import { download } from '@/routes/documents';
import { resendInvite } from '@/routes/signers';
import type { DocumentItem, SignLink, Signer } from '@/types';

const props = defineProps<{
    document: DocumentItem | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const rows = computed(() => {
    const document = props.document;

    if (!document) {
        return [];
    }

    return document.links.flatMap((link) => {
        const signer = document.signers.find((s) => s.id === link.signerId);

        return signer ? [{ signer, link }] : [];
    });
});

const statusChips: Record<
    SignLink['status'],
    { label: string; background: string; color: string }
> = {
    pendiente: { label: 'Pendiente', background: '#fdf1e2', color: '#9a5a12' },
    firmado: { label: 'Firmado', background: '#e3f3f9', color: '#0f6d8e' },
};

async function copy(link: SignLink, signer: Signer): Promise<void> {
    try {
        await writeToClipboard(link.url);
        toast.success(`Enlace de ${signer.name} copiado`);
    } catch {
        toast.error('No se pudo copiar el enlace.');
    }
}

const resending = ref<Set<string>>(new Set());

async function resend(signer: Signer): Promise<void> {
    const documentId = props.document?.id;

    if (!documentId || resending.value.has(signer.id)) {
        return;
    }

    resending.value.add(signer.id);

    try {
        await api(
            'POST',
            resendInvite({ document: documentId, signer: signer.id }).url,
        );
        toast.success(`Correo reenviado a ${signer.name}`);
    } catch (error) {
        toast.error(
            error instanceof ApiError
                ? error.first()
                : 'No se pudo reenviar el correo.',
        );
    } finally {
        resending.value.delete(signer.id);
    }
}

async function writeToClipboard(text: string): Promise<void> {
    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);

            return;
        } catch {
            // Denied (e.g. page without focus): try the legacy way below.
        }
    }

    // Fallback for browsers or contexts without, or refusing, the async clipboard API.
    const area = document.createElement('textarea');
    area.value = text;
    area.setAttribute('readonly', '');
    area.style.position = 'fixed';
    area.style.opacity = '0';
    // Inside the dialog: its focus trap would take the focus away from a node outside it.
    (document.querySelector('[role="dialog"]') ?? document.body).appendChild(
        area,
    );
    area.focus();
    area.select();

    const copied = document.execCommand('copy');
    area.remove();

    if (!copied) {
        throw new Error('copy failed');
    }
}

/** Same tab, so the in-app state and the way back to the list are kept. */
function openLink(link: SignLink): void {
    router.visit(link.url);
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-2xl" data-test="links-modal">
            <DialogHeader>
                <DialogTitle>Enlaces de firma</DialogTitle>
                <DialogDescription>
                    Comparte a cada firmante su enlace personal. Cada uno solo
                    ve y firma sus propios campos.
                </DialogDescription>
            </DialogHeader>

            <ul class="flex max-h-[60dvh] flex-col gap-3 overflow-y-auto">
                <li
                    v-for="{ signer, link } in rows"
                    :key="signer.id"
                    class="flex flex-col gap-3 rounded-[12px] border border-border p-3.5"
                    data-test="link-row"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                            :style="{ backgroundColor: signer.color }"
                            >{{ signer.siglas }}</span
                        >
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold">
                                {{ signer.name }}
                            </div>
                            <div class="truncate text-xs text-muted-foreground">
                                {{ signer.email }}
                            </div>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold"
                            :style="{
                                background: statusChips[link.status].background,
                                color: statusChips[link.status].color,
                            }"
                            data-test="link-status"
                            >{{ statusChips[link.status].label }}</span
                        >
                    </div>

                    <div
                        class="truncate rounded-md bg-[#f6f8fb] px-3 py-2 font-mono text-xs text-muted-foreground select-all"
                        data-test="link-url"
                    >
                        {{ link.url }}
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            data-test="copy-link"
                            @click="copy(link, signer)"
                        >
                            <Copy class="size-4" />
                            Copiar
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            data-test="open-link"
                            @click="openLink(link)"
                        >
                            <ExternalLink class="size-4" />
                            Abrir enlace
                        </Button>
                        <Button
                            v-if="link.status === 'pendiente'"
                            type="button"
                            variant="outline"
                            size="sm"
                            :disabled="resending.has(signer.id)"
                            data-test="resend-invite"
                            @click="resend(signer)"
                        >
                            <Mail class="size-4" />
                            Reenviar correo
                        </Button>
                    </div>
                </li>
            </ul>

            <p
                v-if="rows.length === 0"
                class="py-4 text-center text-sm text-muted-foreground"
            >
                Este documento todavía no tiene enlaces.
            </p>

            <div
                v-if="document?.status === 'completado'"
                class="flex flex-col gap-2 border-t border-border pt-4"
            >
                <p class="text-xs text-muted-foreground">
                    Todos firmaron. El PDF final lleva cada firma en su campo.
                </p>
                <Button as-child data-test="download-signed">
                    <a :href="download(document.id).url" download>
                        <Download class="size-4" />
                        Descargar PDF firmado
                    </a>
                </Button>
            </div>
        </DialogContent>
    </Dialog>
</template>
