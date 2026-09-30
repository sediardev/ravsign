<script setup lang="ts">
import { ImagePlus } from '@lucide/vue';
import SignaturePad from 'signature_pad';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { fileToPngDataUrl, trimCanvas } from '@/lib/signature';

const emit = defineEmits<{
    /** The signature to use, as a PNG data URL. */
    adopt: [dataUrl: string];
}>();

const open = defineModel<boolean>('open', { required: true });

type Tab = 'draw' | 'upload';

const tabs: { key: Tab; label: string }[] = [
    { key: 'draw', label: 'Dibujar' },
    { key: 'upload', label: 'Subir imagen' },
];

const tab = ref<Tab>('draw');
const canvas = ref<HTMLCanvasElement | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);
const hasStroke = ref(false);
const uploaded = ref<string | null>(null);
const uploadError = ref<string | null>(null);

let pad: SignaturePad | null = null;

const canAdopt = computed(() =>
    tab.value === 'draw' ? hasStroke.value : uploaded.value !== null,
);

/** The canvas is drawn at device resolution and shown at its CSS size. */
function sizeCanvas(element: HTMLCanvasElement): void {
    const ratio = Math.max(window.devicePixelRatio || 1, 1);

    element.width = element.offsetWidth * ratio;
    element.height = element.offsetHeight * ratio;
    element.getContext('2d')?.scale(ratio, ratio);
}

// The canvas exists only while the dialog is open on the drawing tab.
watch(
    canvas,
    (element) => {
        pad?.off();
        pad = null;
        hasStroke.value = false;

        if (!element) {
            return;
        }

        sizeCanvas(element);
        pad = new SignaturePad(element, {
            penColor: '#1a3560',
            backgroundColor: 'rgba(0, 0, 0, 0)',
            minWidth: 0.8,
            maxWidth: 2.4,
        });
        pad.addEventListener('endStroke', () => {
            hasStroke.value = pad !== null && !pad.isEmpty();
        });
    },
    { flush: 'post' },
);

watch(open, (isOpen) => {
    if (!isOpen) {
        return;
    }

    tab.value = 'draw';
    uploaded.value = null;
    uploadError.value = null;
});

function clear(): void {
    pad?.clear();
    hasStroke.value = false;
}

async function onFile(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';

    if (!file) {
        return;
    }

    try {
        uploaded.value = await fileToPngDataUrl(file);
        uploadError.value = null;
    } catch (error) {
        uploadError.value =
            error instanceof Error
                ? error.message
                : 'No se pudo leer la imagen.';
    }
}

function adopt(): void {
    if (!canAdopt.value) {
        return;
    }

    const dataUrl =
        tab.value === 'draw' && canvas.value
            ? trimCanvas(canvas.value)
            : uploaded.value;

    if (!dataUrl) {
        return;
    }

    emit('adopt', dataUrl);
    open.value = false;
}

onBeforeUnmount(() => pad?.off());
</script>

<template>
    <Dialog v-model:open="open">
        <DialogContent class="sm:max-w-lg" data-test="signature-modal">
            <DialogHeader>
                <DialogTitle>Adopta tu firma</DialogTitle>
                <DialogDescription>
                    Dibújala o sube una imagen. La usaremos en todos tus campos
                    de firma.
                </DialogDescription>
            </DialogHeader>

            <div
                role="tablist"
                class="grid grid-cols-2 gap-1 rounded-[10px] bg-[#eef0f3] p-1"
            >
                <button
                    v-for="item in tabs"
                    :key="item.key"
                    type="button"
                    role="tab"
                    :aria-selected="tab === item.key"
                    class="rounded-[8px] py-2 text-sm font-semibold transition-colors"
                    :class="
                        tab === item.key
                            ? 'bg-white text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    :data-test="`tab-${item.key}`"
                    @click="tab = item.key"
                >
                    {{ item.label }}
                </button>
            </div>

            <div v-if="tab === 'draw'" class="flex flex-col gap-2">
                <div
                    class="relative h-[180px] overflow-hidden rounded-[12px] border-[1.5px] border-dashed border-[#c6ced8] bg-white"
                >
                    <canvas
                        ref="canvas"
                        class="absolute inset-0 h-full w-full cursor-crosshair touch-none"
                        data-test="signature-canvas"
                    />
                    <span
                        v-if="!hasStroke"
                        class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm text-muted-foreground"
                        >Firma aquí</span
                    >
                </div>
                <div class="flex justify-end">
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        data-test="clear-signature"
                        @click="clear"
                    >
                        Limpiar
                    </Button>
                </div>
            </div>

            <div v-else class="flex flex-col gap-2">
                <input
                    ref="fileInput"
                    type="file"
                    accept="image/png,image/jpeg"
                    class="hidden"
                    data-test="signature-file"
                    @change="onFile"
                />
                <button
                    type="button"
                    class="flex h-[180px] flex-col items-center justify-center gap-2 overflow-hidden rounded-[12px] border-[1.5px] border-dashed border-[#c6ced8] bg-white p-3 text-center outline-none hover:border-primary focus-visible:ring-[3px] focus-visible:ring-ring/50"
                    data-test="signature-upload-zone"
                    @click="fileInput?.click()"
                >
                    <template v-if="uploaded">
                        <img
                            :src="uploaded"
                            alt="Vista previa de tu firma"
                            class="min-h-0 flex-1 object-contain"
                            data-test="signature-preview"
                        />
                        <span class="text-xs text-muted-foreground"
                            >Haz clic para cambiar la imagen</span
                        >
                    </template>
                    <template v-else>
                        <ImagePlus class="size-7 text-muted-foreground" />
                        <span class="text-sm font-semibold"
                            >Haz clic para subir una imagen</span
                        >
                        <span class="text-xs text-muted-foreground"
                            >PNG o JPG con tu firma</span
                        >
                    </template>
                </button>
                <p
                    v-if="uploadError"
                    class="text-xs text-destructive"
                    data-test="upload-error"
                >
                    {{ uploadError }}
                </p>
            </div>

            <p class="text-xs leading-relaxed text-muted-foreground">
                Al pulsar «Adoptar y firmar» acepto que esta firma electrónica
                es la representación de mi firma y que tiene validez en este
                documento.
            </p>

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="outline"
                    data-test="cancel-signature"
                    @click="open = false"
                >
                    Cancelar
                </Button>
                <Button
                    type="button"
                    :disabled="!canAdopt"
                    data-test="adopt-signature"
                    @click="adopt"
                >
                    Adoptar y firmar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
