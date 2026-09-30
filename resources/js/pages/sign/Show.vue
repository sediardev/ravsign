<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import PdfPages from '@/components/PdfPages.vue';
import SignFieldBox from '@/components/SignFieldBox.vue';
import { Button } from '@/components/ui/button';
import { Toaster } from '@/components/ui/sonner';
import { usePageZoom } from '@/composables/usePageZoom';
import { ApiError, api } from '@/lib/api';
import { index } from '@/routes/documents';
import { field as signFieldRoute, finish as finishRoute } from '@/routes/sign';
import type { SignField, SigningDocument, SigningSigner } from '@/types';
import SignatureModal from './SignatureModal.vue';

const props = defineProps<{
    document: SigningDocument;
    signer: SigningSigner;
    pdfUrl: string;
}>();

const scroller = ref<HTMLElement | null>(null);
const { isMobile, zoom } = usePageZoom(scroller);

const signersById = computed(
    () => new Map(props.document.signers.map((s) => [s.id, s])),
);

const completed = computed(() => props.document.status === 'completado');
/** Only a pending document can still be signed, and only by who has not yet. */
const canSign = computed(
    () => props.document.status === 'pendiente' && !props.signer.signed,
);

const fields = ref<SignField[]>([...props.document.fields]);
const ownFields = computed(() =>
    fields.value.filter((f) => f.signerId === props.signer.id),
);
const signedCount = computed(
    () => ownFields.value.filter((f) => f.value !== null).length,
);

/** What to tell the signer when there is nothing left for them to do. */
const statusMessage = computed(() => {
    if (completed.value) {
        return 'Documento completado por todos los firmantes.';
    }

    return props.signer.signed
        ? 'Ya firmaste este documento. Falta que firmen los demás.'
        : null;
});

const finishing = ref(false);

/**
 * Finish signing. The owner who is testing their own link goes back to the
 * list, where the links open; a signer without an account stays here and sees
 * that they are done.
 */
async function finish(): Promise<void> {
    if (!canSign.value || finishing.value) {
        return;
    }

    if (ownFields.value.some((f) => f.value === null)) {
        toast.error('Completa todos tus campos antes de finalizar');

        return;
    }

    finishing.value = true;

    try {
        await api('POST', finishRoute({ token: props.signer.token }).url);
    } catch (error) {
        finishing.value = false;
        toast.error(
            error instanceof ApiError
                ? error.first('fields')
                : 'No se pudo finalizar la firma.',
        );

        return;
    }

    const signedIn = usePage().props.auth?.user != null;

    router.visit(signedIn ? index().url : usePage().url, {
        onFinish: () => {
            finishing.value = false;
        },
    });
}

/** Signatures adopted in this visit, by signer and field type, in memory only. */
const adopted = ref<Record<string, string>>({});
const modalOpen = ref(false);
const modalFieldId = ref<string | null>(null);

function adoptedKey(field: SignField): string {
    return `${field.signerId}:${field.type}`;
}

/** An empty field reuses the adopted signature; otherwise the modal opens. */
function onFieldClick(field: SignField): void {
    if (!canSign.value || field.signerId !== props.signer.id) {
        return;
    }

    const known = adopted.value[adoptedKey(field)];

    if (known && field.value === null) {
        void applySignature(field, known);

        return;
    }

    modalFieldId.value = field.id;
    modalOpen.value = true;
}

function onAdopt(dataUrl: string): void {
    const field = fields.value.find((f) => f.id === modalFieldId.value);

    if (!field) {
        return;
    }

    adopted.value[adoptedKey(field)] = dataUrl;
    void applySignature(field, dataUrl);
}

async function applySignature(
    field: SignField,
    dataUrl: string,
): Promise<void> {
    const previous = field.value;
    field.value = dataUrl;

    try {
        await api(
            'POST',
            signFieldRoute({ token: props.signer.token, field: field.id }).url,
            { image: dataUrl },
        );
    } catch (error) {
        field.value = previous;

        if (error instanceof ApiError && error.status === 422) {
            delete adopted.value[adoptedKey(field)];
        }

        toast.error(
            error instanceof ApiError
                ? error.first('image')
                : 'No se pudo guardar la firma.',
        );
    }
}
</script>

<template>
    <Head :title="`Firmar ${document.name}`" />

    <div class="flex h-dvh flex-col bg-[#f6f8fb]">
        <header
            class="flex items-center gap-3 border-b border-border bg-white px-3 py-3 desk:gap-4 desk:px-6"
        >
            <img
                src="/img/icon.svg"
                alt=""
                aria-hidden="true"
                class="h-7 w-auto shrink-0"
            />

            <div class="min-w-0 flex-1">
                <div
                    class="truncate text-[15px] font-semibold"
                    data-test="document-name"
                >
                    {{ document.name }}
                </div>
                <div
                    class="truncate text-xs text-muted-foreground"
                    data-test="signing-as"
                >
                    Firmando como
                    <span class="font-semibold text-foreground">{{
                        signer.name
                    }}</span>
                </div>
            </div>

            <Button
                as-child
                variant="outline"
                size="sm"
                class="shrink-0"
                data-test="exit-button"
            >
                <Link :href="index().url">Salir</Link>
            </Button>
            <Button
                v-if="!isMobile"
                type="button"
                size="sm"
                class="shrink-0"
                :disabled="!canSign || finishing"
                data-test="finish-button"
                @click="finish"
            >
                {{ finishing ? 'Finalizando…' : 'Finalizar firma' }}
            </Button>
        </header>

        <div
            class="flex flex-col gap-1 border-b border-border bg-white px-3 py-2.5 text-xs desk:flex-row desk:items-center desk:gap-6 desk:px-6"
            data-test="signing-info"
        >
            <p
                v-if="statusMessage && !isMobile"
                class="font-semibold text-[#0f6d8e]"
                data-test="signing-hint"
            >
                {{ statusMessage }}
            </p>
            <p
                v-else-if="!isMobile"
                class="text-muted-foreground"
                data-test="signing-hint"
            >
                Haz clic en cada campo resaltado para firmar.
                <span class="text-foreground">
                    Tienes {{ ownFields.length }}
                    {{ ownFields.length === 1 ? 'campo' : 'campos' }}.
                </span>
            </p>

            <p
                class="flex min-w-0 items-center gap-2 text-muted-foreground desk:ml-auto"
            >
                <span class="shrink-0 font-semibold">Tu enlace de firma</span>
                <span
                    class="truncate rounded-md bg-[#f6f8fb] px-2 py-1 font-mono select-all"
                    data-test="signer-link"
                    >{{ signer.link }}</span
                >
            </p>
        </div>

        <main
            ref="scroller"
            class="min-h-0 flex-1 [scrollbar-gutter:stable] overflow-x-hidden overflow-y-auto p-3 desk:overflow-auto desk:p-8"
            data-test="document-area"
        >
            <PdfPages :src="pdfUrl" :zoom="zoom">
                <template #page="{ page, width, height }">
                    <template
                        v-for="field in fields.filter((f) => f.page === page)"
                        :key="field.id"
                    >
                        <SignFieldBox
                            :field="field"
                            :signer="signersById.get(field.signerId)"
                            :page-width="width"
                            :page-height="height"
                            :zoom="zoom"
                            :own="canSign && field.signerId === signer.id"
                            :dimmed="field.signerId !== signer.id"
                            @click="onFieldClick(field)"
                        />
                    </template>
                </template>
            </PdfPages>
        </main>

        <div
            v-if="isMobile"
            class="flex shrink-0 flex-col gap-2 border-t border-border bg-white px-3 py-2.5"
            data-test="mobile-bar"
        >
            <p
                v-if="statusMessage"
                class="text-xs font-semibold text-[#0f6d8e]"
                data-test="signing-hint"
            >
                {{ statusMessage }}
            </p>
            <template v-else>
                <p
                    class="text-xs text-muted-foreground"
                    data-test="signing-hint"
                >
                    Toca cada campo resaltado para firmar.
                    <span class="font-semibold text-foreground"
                        >{{ signedCount }} de {{ ownFields.length }}
                        {{
                            ownFields.length === 1 ? 'firmado' : 'firmados'
                        }}.</span
                    >
                </p>
                <Button
                    type="button"
                    class="w-full"
                    :disabled="!canSign || finishing"
                    data-test="finish-button"
                    @click="finish"
                >
                    {{ finishing ? 'Finalizando…' : 'Finalizar firma' }}
                </Button>
            </template>
        </div>

        <SignatureModal v-model:open="modalOpen" @adopt="onAdopt" />

        <Toaster />
    </div>
</template>
