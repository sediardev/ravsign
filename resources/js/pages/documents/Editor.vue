<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, PenLine, Users, ZoomIn, ZoomOut } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import PdfPages from '@/components/PdfPages.vue';
import SelectedFieldPanel from '@/components/editor/SelectedFieldPanel.vue';
import SignersPanel from '@/components/editor/SignersPanel.vue';
import type { SignerForm } from '@/components/editor/SignersPanel.vue';
import SignFieldBox from '@/components/SignFieldBox.vue';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Toaster } from '@/components/ui/sonner';
import { useFieldDrag } from '@/composables/useFieldDrag';
import { useFieldResize } from '@/composables/useFieldResize';
import { usePageZoom } from '@/composables/usePageZoom';
import type { DropResult } from '@/composables/useFieldDrag';
import type { ResizeResult } from '@/composables/useFieldResize';
import { ApiError, api } from '@/lib/api';
import { FIELD_LABELS, FIELD_SIZES, SIGNER_COLORS } from '@/lib/documents';
import { index, send } from '@/routes/documents';
import { privacy, terms } from '@/routes/legal';
import {
    destroy as destroyField,
    store as storeField,
    update as updateField,
} from '@/routes/fields';
import {
    destroy as destroySigner,
    store as storeSigner,
} from '@/routes/signers';
import type { DocumentItem, FieldType, SignField, Signer } from '@/types';

const props = defineProps<{
    document: DocumentItem;
    pdfUrl: string;
}>();

const statusLabels = {
    borrador: 'Draft',
    pendiente: 'Pending signature',
    completado: 'Completed',
} as const;

const signers = ref<Signer[]>([...props.document.signers]);
const fields = ref<SignField[]>([...props.document.fields]);
const activeSignerId = ref<string | null>(signers.value[0]?.id ?? null);

const selectedFieldId = ref<string | null>(null);
const scroller = ref<HTMLElement | null>(null);
const sheetOpen = ref(false);

// Below 760px the page shrinks to fit the screen and the panels move to a bottom bar.
const { isMobile, zoom, canZoomIn, canZoomOut, zoomIn, zoomOut, resetZoom } =
    usePageZoom(scroller);
const zoomPercent = computed(() => Math.round(zoom.value * 100));

const editable = computed(() => props.document.status === 'borrador');
const canAddSigner = computed(
    () => editable.value && signers.value.length < SIGNER_COLORS.length,
);
const selectedField = computed(
    () => fields.value.find((f) => f.id === selectedFieldId.value) ?? null,
);
const fieldsSummary = computed(() => {
    const count = fields.value.length;

    return `${count} ${count === 1 ? 'field' : 'fields'} in the document`;
});
const signersById = computed(
    () => new Map(signers.value.map((signer) => [signer.id, signer])),
);

const form = ref<SignerForm>({ name: '', email: '', siglas: '' });
const errors = ref<Record<string, string>>({});
const adding = ref(false);

const subtitle = computed(() => {
    const count = signers.value.length;

    return `${statusLabels[props.document.status]} · ${count} ${count === 1 ? 'signer' : 'signers'}`;
});

const { drag, start } = useFieldDrag({ zoom, scroller, onDrop: handleDrop });

/** The field's size right before a drag-resize starts, to revert it if saving fails. */
let resizeOriginal: { width: number; height: number } | null = null;

const { start: startResize } = useFieldResize({
    zoom,
    onResize: (result) => applyFieldSize(result),
    onFinish: (result) => void commitFieldSize(result),
});

function applyFieldSize(result: ResizeResult): void {
    const field = fields.value.find((item) => item.id === result.fieldId);

    if (field) {
        Object.assign(field, { width: result.width, height: result.height });
    }
}

async function commitFieldSize(result: ResizeResult): Promise<void> {
    const field = fields.value.find((item) => item.id === result.fieldId);

    if (!field || !editable.value) {
        return;
    }

    try {
        await api(
            'PATCH',
            updateField({ document: props.document.id, field: field.id }).url,
            { width: result.width, height: result.height },
        );
    } catch (error) {
        if (resizeOriginal) {
            Object.assign(field, resizeOriginal);
        }

        toast.error(
            error instanceof ApiError
                ? error.first()
                : 'Could not resize the field.',
        );
    } finally {
        resizeOriginal = null;
    }
}

function startFieldResize(
    event: PointerEvent,
    field: SignField,
    pageWidth: number,
    pageHeight: number,
): void {
    if (!editable.value) {
        return;
    }

    resizeOriginal = { width: field.width, height: field.height };

    startResize(event, {
        fieldId: field.id,
        x: field.x,
        y: field.y,
        width: field.width,
        height: field.height,
        pageWidth,
        pageHeight,
    });
}

const ghost = computed(() => {
    const state = drag.value;

    if (!state?.started) {
        return null;
    }

    const signer = signersById.value.get(state.signerId);

    return {
        color: signer?.color ?? '#6b7078',
        siglas: signer?.siglas ?? '?',
        label: FIELD_LABELS[state.type],
        style: {
            left: `${state.pointer.x - state.grab.x}px`,
            top: `${state.pointer.y - state.grab.y}px`,
            width: `${state.size.width * zoom.value}px`,
            height: `${state.size.height * zoom.value}px`,
        },
    };
});

const movingFieldId = computed(() =>
    drag.value?.started && drag.value.kind === 'move'
        ? drag.value.fieldId
        : null,
);

function startPaletteDrag(event: PointerEvent, type: FieldType): void {
    if (!activeSignerId.value) {
        toast('Add a signer first');

        return;
    }

    const size = FIELD_SIZES[type];

    start(event, {
        kind: 'new',
        signerId: activeSignerId.value,
        type,
        size,
        grab: {
            x: (size.width * zoom.value) / 2,
            y: (size.height * zoom.value) / 2,
        },
    });
}

function startFieldDrag(event: PointerEvent, field: SignField): void {
    selectedFieldId.value = field.id;

    if (!editable.value) {
        return;
    }

    const box = (event.currentTarget as HTMLElement).getBoundingClientRect();

    start(event, {
        kind: 'move',
        fieldId: field.id,
        signerId: field.signerId,
        type: field.type,
        size: { width: field.width, height: field.height },
        grab: { x: event.clientX - box.left, y: event.clientY - box.top },
    });
}

function deselect(event: PointerEvent): void {
    if (!(event.target as HTMLElement).closest('[data-field-id]')) {
        selectedFieldId.value = null;
    }
}

async function handleDrop(drop: DropResult): Promise<void> {
    if (drop.kind === 'new') {
        try {
            const { data } = await api<{ data: SignField }>(
                'POST',
                storeField(props.document.id).url,
                {
                    signer_id: drop.signerId,
                    type: drop.type,
                    page: drop.page,
                    x: drop.x,
                    y: drop.y,
                },
            );

            fields.value.push(data);
            selectedFieldId.value = data.id;
        } catch (error) {
            toast.error(
                error instanceof ApiError
                    ? error.first()
                    : 'Could not place the field.',
            );
        }

        return;
    }

    const field = fields.value.find((item) => item.id === drop.fieldId);

    if (
        !field ||
        (field.page === drop.page && field.x === drop.x && field.y === drop.y)
    ) {
        return;
    }

    const previous = { page: field.page, x: field.x, y: field.y };
    Object.assign(field, { page: drop.page, x: drop.x, y: drop.y });

    try {
        await api(
            'PATCH',
            updateField({
                document: props.document.id,
                field: field.id,
            }).url,
            { page: drop.page, x: drop.x, y: drop.y },
        );
    } catch (error) {
        Object.assign(field, previous);
        toast.error(
            error instanceof ApiError
                ? error.first()
                : 'Could not move the field.',
        );
    }
}

async function reassignField(signerId: unknown): Promise<void> {
    const field = selectedField.value;

    if (!field || typeof signerId !== 'string' || field.signerId === signerId) {
        return;
    }

    const previous = field.signerId;
    field.signerId = signerId;

    try {
        await api(
            'PATCH',
            updateField({
                document: props.document.id,
                field: field.id,
            }).url,
            { signer_id: signerId },
        );
    } catch (error) {
        field.signerId = previous;
        toast.error(
            error instanceof ApiError
                ? error.first()
                : 'Could not reassign the field.',
        );
    }
}

async function resizeField(size: { width: number; height: number }): Promise<void> {
    const field = selectedField.value;

    if (!field || !editable.value) {
        return;
    }

    const previous = { width: field.width, height: field.height };
    Object.assign(field, size);

    try {
        await api(
            'PATCH',
            updateField({
                document: props.document.id,
                field: field.id,
            }).url,
            size,
        );
    } catch (error) {
        Object.assign(field, previous);
        toast.error(
            error instanceof ApiError
                ? error.first()
                : 'Could not resize the field.',
        );
    }
}

async function deleteSelectedField(): Promise<void> {
    const field = selectedField.value;

    if (!field || !editable.value) {
        return;
    }

    try {
        await api(
            'DELETE',
            destroyField({
                document: props.document.id,
                field: field.id,
            }).url,
        );
    } catch (error) {
        toast.error(
            error instanceof ApiError
                ? error.first()
                : 'Could not delete the field.',
        );

        return;
    }

    fields.value = fields.value.filter((item) => item.id !== field.id);

    if (selectedFieldId.value === field.id) {
        selectedFieldId.value = null;
    }
}

/** Delete or Backspace remove the selected field, unless the user is typing. */
function onKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Delete' && event.key !== 'Backspace') {
        return;
    }

    const target = event.target as HTMLElement | null;

    if (
        target?.closest(
            'input, textarea, select, [contenteditable="true"], [role="combobox"], [role="listbox"]',
        )
    ) {
        return;
    }

    if (selectedFieldId.value && editable.value) {
        event.preventDefault();
        void deleteSelectedField();
    }
}

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

const sending = ref(false);

/** Check what the server checks too, so the message shows without a round trip. */
function sendProblem(): string | null {
    if (signers.value.length === 0) {
        return 'Add a signer first';
    }

    if (fields.value.length === 0) {
        return 'Add at least one field to the document';
    }

    if (
        signers.value.some(
            (signer) => !fields.value.some((f) => f.signerId === signer.id),
        )
    ) {
        return 'Every signer needs at least one field';
    }

    return null;
}

async function sendForSigning(): Promise<void> {
    if (sending.value || !editable.value) {
        return;
    }

    const problem = sendProblem();

    if (problem) {
        toast.error(problem);

        return;
    }

    sending.value = true;

    try {
        await api('POST', send(props.document.id).url);
    } catch (error) {
        toast.error(
            error instanceof ApiError
                ? error.first('document')
                : 'Could not send the document.',
        );
        sending.value = false;

        return;
    }

    // The list opens the links of the document that was just sent.
    router.visit(index().url, {
        onFinish: () => {
            sending.value = false;
        },
    });
}

async function addSigner(): Promise<void> {
    if (adding.value) {
        return;
    }

    adding.value = true;
    errors.value = {};

    try {
        const { data } = await api<{ data: Signer }>(
            'POST',
            storeSigner(props.document.id).url,
            {
                name: form.value.name,
                email: form.value.email,
                siglas: form.value.siglas,
            },
        );

        signers.value.push(data);
        activeSignerId.value ??= data.id;
        form.value = { name: '', email: '', siglas: '' };
    } catch (error) {
        if (error instanceof ApiError && error.status === 422) {
            errors.value = Object.fromEntries(
                Object.entries(error.errors).map(([key, messages]) => [
                    key,
                    messages[0],
                ]),
            );
        } else {
            toast.error(
                error instanceof ApiError
                    ? error.message
                    : 'Could not add the signer.',
            );
        }
    } finally {
        adding.value = false;
    }
}

async function removeSigner(signer: Signer): Promise<void> {
    try {
        await api(
            'DELETE',
            destroySigner({
                document: props.document.id,
                signer: signer.id,
            }).url,
        );
    } catch (error) {
        toast.error(
            error instanceof ApiError
                ? error.message
                : 'Could not remove the signer.',
        );

        return;
    }

    signers.value = signers.value.filter((item) => item.id !== signer.id);
    fields.value = fields.value.filter((field) => field.signerId !== signer.id);

    if (activeSignerId.value === signer.id) {
        activeSignerId.value = signers.value[0]?.id ?? null;
    }
}
</script>

<template>
    <Head :title="document.name" />

    <div class="flex h-dvh flex-col bg-[#f6f8fb]">
        <header
            class="flex items-center gap-3 border-b border-border bg-white px-3 py-3 desk:gap-4 desk:px-6"
        >
            <Link
                :href="index().url"
                class="inline-flex shrink-0 items-center gap-1.5 text-sm font-semibold text-muted-foreground hover:text-foreground"
                aria-label="Documents"
                data-test="back-to-documents"
            >
                <ArrowLeft class="size-4" />
                <span class="hidden desk:inline">Documents</span>
            </Link>

            <div class="min-w-0 flex-1">
                <div
                    class="truncate text-[15px] font-semibold"
                    data-test="document-name"
                >
                    {{ document.name }}
                </div>
                <div
                    class="truncate text-xs text-muted-foreground"
                    data-test="subtitle"
                >
                    {{ subtitle }}
                </div>
            </div>

            <ol
                class="hidden items-center gap-3 text-sm font-semibold desk:flex"
            >
                <li class="flex items-center gap-2 text-foreground">
                    <span
                        class="flex size-6 items-center justify-center rounded-full bg-primary text-xs text-white"
                        >1</span
                    >
                    Prepare
                </li>
                <li class="flex items-center gap-2 text-muted-foreground">
                    <span
                        class="flex size-6 items-center justify-center rounded-full bg-[#eef0f3] text-xs"
                        >2</span
                    >
                    Sign
                </li>
            </ol>

            <Button
                type="button"
                size="sm"
                :disabled="!editable || sending"
                data-test="send-button"
                @click="sendForSigning"
            >
                {{ sending ? 'Sending…' : 'Send for signature' }}
            </Button>
        </header>

        <div class="relative flex min-h-0 flex-1">
            <aside
                v-if="!isMobile"
                class="w-[300px] shrink-0 overflow-y-auto border-r border-border bg-white p-4"
                data-test="signers-panel"
            >
                <section v-if="editable" class="mb-5" data-test="palette">
                    <h2 class="mb-3 text-sm font-semibold">Fields</h2>
                    <div
                        role="button"
                        tabindex="0"
                        class="flex cursor-grab touch-none items-center gap-3 rounded-[10px] border-[1.5px] border-dashed border-[#c6ced8] bg-[#f6f8fb] p-3 select-none hover:border-primary"
                        data-test="palette-firma"
                        @pointerdown="startPaletteDrag($event, 'firma')"
                    >
                        <span
                            class="flex size-9 shrink-0 items-center justify-center rounded-md bg-white text-primary shadow-sm"
                        >
                            <PenLine class="size-[18px]" />
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold"
                                >Signature</span
                            >
                            <span class="block text-xs text-muted-foreground"
                                >Drag it onto the document</span
                            >
                        </span>
                    </div>
                    <p
                        class="mt-2 text-xs text-muted-foreground"
                        data-test="fields-summary"
                    >
                        {{ fieldsSummary }}
                    </p>
                </section>

                <SelectedFieldPanel
                    v-if="editable && selectedField"
                    :field="selectedField"
                    :signers="signers"
                    @reassign="reassignField"
                    @resize="resizeField"
                    @remove="deleteSelectedField"
                />

                <h2 class="mb-3 text-sm font-semibold">Signers</h2>

                <SignersPanel
                    v-model:form="form"
                    :signers="signers"
                    :active-signer-id="activeSignerId"
                    :editable="editable"
                    :can-add="canAddSigner"
                    :errors="errors"
                    :adding="adding"
                    @select="activeSignerId = $event"
                    @remove="removeSigner"
                    @add="addSigner"
                />
            </aside>

            <main
                ref="scroller"
                class="min-w-0 flex-1 [scrollbar-gutter:stable] overflow-x-hidden overflow-y-auto p-3 desk:overflow-auto desk:p-8"
                data-test="document-area"
                @pointerdown="deselect"
            >
                <PdfPages :src="pdfUrl" :zoom="zoom">
                    <template #page="{ page, width, height }">
                        <template
                            v-for="field in fields.filter(
                                (f) => f.page === page,
                            )"
                            :key="field.id"
                        >
                            <SignFieldBox
                                :field="field"
                                :signer="signersById.get(field.signerId)"
                                :page-width="width"
                                :page-height="height"
                                :zoom="zoom"
                                :selected="selectedFieldId === field.id"
                                :resizable="editable"
                                :class="[
                                    editable ? 'cursor-grab' : '',
                                    movingFieldId === field.id
                                        ? 'opacity-40'
                                        : '',
                                ]"
                                @pointerdown="startFieldDrag($event, field)"
                                @resize-start="
                                    startFieldResize(
                                        $event,
                                        field,
                                        width,
                                        height,
                                    )
                                "
                            />
                        </template>
                    </template>
                </PdfPages>
            </main>

            <div
                v-if="!isMobile"
                class="absolute right-6 bottom-6 z-20 flex items-center gap-1 rounded-full border border-border bg-white p-1 shadow-md"
                data-test="zoom-controls"
            >
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8 rounded-full"
                    aria-label="Zoom out"
                    :disabled="!canZoomOut"
                    data-test="zoom-out"
                    @click="zoomOut"
                >
                    <ZoomOut class="size-4" />
                </Button>
                <button
                    type="button"
                    class="min-w-[3.5ch] px-1 text-center text-xs font-semibold text-muted-foreground tabular-nums hover:text-foreground"
                    aria-label="Reset zoom"
                    data-test="zoom-reset"
                    @click="resetZoom"
                >
                    {{ zoomPercent }}%
                </button>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    class="size-8 rounded-full"
                    aria-label="Zoom in"
                    :disabled="!canZoomIn"
                    data-test="zoom-in"
                    @click="zoomIn"
                >
                    <ZoomIn class="size-4" />
                </Button>
            </div>

            <div
                v-if="!isMobile"
                class="absolute bottom-2 left-6 z-20 flex items-center gap-2 text-xs text-muted-foreground"
            >
                <Link :href="terms()" class="hover:text-foreground"
                    >Terms</Link
                >
                <span>·</span>
                <Link :href="privacy()" class="hover:text-foreground"
                    >Privacy</Link
                >
            </div>
        </div>

        <div
            v-if="isMobile"
            class="flex shrink-0 flex-col gap-2 border-t border-border bg-white px-3 py-2.5"
            data-test="mobile-bar"
        >
            <SelectedFieldPanel
                v-if="editable && selectedField"
                compact
                :field="selectedField"
                :signers="signers"
                @reassign="reassignField"
                @remove="deleteSelectedField"
            />

            <p
                v-if="editable"
                class="text-center text-xs text-muted-foreground"
                data-test="fields-summary"
            >
                {{ fieldsSummary }}
            </p>

            <div class="flex items-center gap-2">
                <div
                    v-if="editable"
                    role="button"
                    tabindex="0"
                    class="flex h-10 min-w-0 flex-1 cursor-grab touch-none items-center gap-2 rounded-[10px] border-[1.5px] border-dashed border-[#c6ced8] bg-[#f6f8fb] px-3 select-none"
                    data-test="palette-firma"
                    @pointerdown="startPaletteDrag($event, 'firma')"
                >
                    <PenLine class="size-4 shrink-0 text-primary" />
                    <span class="truncate text-sm font-semibold"
                        >Signature</span
                    >
                    <span class="truncate text-xs text-muted-foreground"
                        >Drag it</span
                    >
                </div>

                <Button
                    type="button"
                    variant="outline"
                    class="h-10 shrink-0"
                    data-test="open-signers"
                    @click="sheetOpen = true"
                >
                    <Users class="size-4" />
                    Signers ({{ signers.length }})
                </Button>
            </div>
        </div>

        <Sheet v-if="isMobile" v-model:open="sheetOpen">
            <SheetContent
                side="bottom"
                class="max-h-[85dvh] overflow-y-auto p-4"
                @open-auto-focus.prevent
                data-test="signers-sheet"
            >
                <SheetHeader class="p-0">
                    <SheetTitle>Signers</SheetTitle>
                    <SheetDescription
                        >Choose who to assign new fields
                        to.</SheetDescription
                    >
                </SheetHeader>

                <SignersPanel
                    v-model:form="form"
                    :signers="signers"
                    :active-signer-id="activeSignerId"
                    :editable="editable"
                    :can-add="canAddSigner"
                    :errors="errors"
                    :adding="adding"
                    @select="activeSignerId = $event"
                    @remove="removeSigner"
                    @add="addSigner"
                />
            </SheetContent>
        </Sheet>

        <div
            v-if="ghost"
            class="pointer-events-none fixed z-50 flex items-center justify-center rounded-[4px] border-[1.5px] border-dashed text-[11px] font-semibold opacity-90 shadow-lg"
            :style="{
                ...ghost.style,
                borderColor: ghost.color,
                backgroundColor: `${ghost.color}33`,
                color: ghost.color,
            }"
            data-test="drag-ghost"
        >
            <span
                class="absolute top-1 left-1 inline-flex size-[18px] items-center justify-center rounded-full text-[9px] font-bold text-white"
                :style="{ backgroundColor: ghost.color }"
                >{{ ghost.siglas }}</span
            >
            {{ ghost.label }}
        </div>

        <Toaster />
    </div>
</template>
