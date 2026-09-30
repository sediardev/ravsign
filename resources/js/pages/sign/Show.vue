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
import { privacy, terms } from '@/routes/legal';
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

/**
 * Local copies so finishing can update the view in place instead of
 * navigating away — the signing link is public and whoever signs it may not
 * even have an account to be sent to.
 */
const documentStatus = ref(props.document.status);
const signerSigned = ref(props.signer.signed);

const completed = computed(() => documentStatus.value === 'completado');
/** Only a pending document can still be signed, and only by who has not yet. */
const canSign = computed(
    () => documentStatus.value === 'pendiente' && !signerSigned.value,
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
        return 'Document completed by all signers.';
    }

    return signerSigned.value
        ? 'You already signed this document. Waiting on the others to sign.'
        : null;
});

const finishing = ref(false);

/**
 * Finish signing. The owner who is testing their own link goes back to the
 * list, where the links open; a signer without an account — the common case,
 * since the signing link is public — stays right here and simply sees that
 * they are done, with no navigation away from the document.
 */
async function finish(): Promise<void> {
    if (!canSign.value || finishing.value) {
        return;
    }

    if (ownFields.value.some((f) => f.value === null)) {
        toast.error('Complete all your fields before finishing');

        return;
    }

    finishing.value = true;

    let result: { completed: boolean };

    try {
        result = await api(
            'POST',
            finishRoute({ token: props.signer.token }).url,
        );
    } catch (error) {
        finishing.value = false;
        toast.error(
            error instanceof ApiError
                ? error.first('fields')
                : 'Could not finish signing.',
        );

        return;
    }

    const signedIn = usePage().props.auth?.user != null;

    if (!signedIn) {
        signerSigned.value = true;
        documentStatus.value = result.completed ? 'completado' : 'pendiente';
        finishing.value = false;

        return;
    }

    router.visit(index().url, {
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

/**
 * Adopting a signature applies it to every one of the signer's own empty
 * fields of that same type right away — not just the one that was clicked —
 * so a signer with several signature spots never has to repeat the gesture.
 */
function onAdopt(dataUrl: string): void {
    const field = fields.value.find((f) => f.id === modalFieldId.value);

    if (!field) {
        return;
    }

    adopted.value[adoptedKey(field)] = dataUrl;

    const matching = ownFields.value.filter(
        (f) => f.value === null && adoptedKey(f) === adoptedKey(field),
    );

    for (const match of matching) {
        void applySignature(match, dataUrl);
    }
}

/**
 * The "Firmar" button simulates a click on a signer's next empty field, so
 * it doesn't need to be obvious that the boxes themselves are clickable.
 */
function onSignButtonClick(): void {
    const next = ownFields.value.find((f) => f.value === null);

    if (next) {
        onFieldClick(next);
    }
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
                : 'Could not save the signature.',
        );
    }
}
</script>

<template>
    <Head :title="`Sign ${document.name}`" />

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
                    Signing as
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
                <Link :href="index().url">Exit</Link>
            </Button>
            <Button
                v-if="!isMobile && canSign"
                type="button"
                variant="secondary"
                size="sm"
                class="shrink-0"
                :disabled="signedCount >= ownFields.length"
                data-test="sign-button"
                @click="onSignButtonClick"
            >
                Sign
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
                {{ finishing ? 'Finishing…' : 'Finish signing' }}
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
                Click each highlighted field to sign.
                <span class="text-foreground">
                    You have {{ ownFields.length }}
                    {{ ownFields.length === 1 ? 'field' : 'fields' }}.
                </span>
            </p>

            <p
                class="flex min-w-0 items-center gap-2 text-muted-foreground desk:ml-auto"
            >
                <span class="shrink-0 font-semibold">Your signing link</span>
                <span
                    class="truncate rounded-md bg-[#f6f8fb] px-2 py-1 font-mono select-all"
                    data-test="signer-link"
                    >{{ signer.link }}</span
                >
            </p>
        </div>

        <main
            ref="scroller"
            class="relative min-h-0 flex-1 [scrollbar-gutter:stable] overflow-x-hidden overflow-y-auto p-3 desk:overflow-auto desk:p-8"
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

            <div
                v-if="!isMobile"
                class="sticky bottom-0 left-0 z-20 mt-3 flex w-fit items-center gap-2 rounded-md bg-[#f6f8fb]/90 px-2 py-1 text-xs text-muted-foreground backdrop-blur-sm"
            >
                <Link :href="terms()" class="hover:text-foreground"
                    >Terms</Link
                >
                <span>·</span>
                <Link :href="privacy()" class="hover:text-foreground"
                    >Privacy</Link
                >
            </div>
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
                    Tap each highlighted field to sign.
                    <span class="font-semibold text-foreground"
                        >{{ signedCount }} of {{ ownFields.length }}
                        signed.</span
                    >
                </p>
                <Button
                    v-if="signedCount < ownFields.length"
                    type="button"
                    variant="secondary"
                    class="w-full"
                    :disabled="!canSign"
                    data-test="sign-button"
                    @click="onSignButtonClick"
                >
                    Sign
                </Button>
                <Button
                    type="button"
                    class="w-full"
                    :disabled="!canSign || finishing"
                    data-test="finish-button"
                    @click="finish"
                >
                    {{ finishing ? 'Finishing…' : 'Finish signing' }}
                </Button>
            </template>
        </div>

        <SignatureModal v-model:open="modalOpen" @adopt="onAdopt" />

        <Toaster />
    </div>
</template>
