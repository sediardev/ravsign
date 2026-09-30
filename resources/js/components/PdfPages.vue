<script setup lang="ts">
import {
    getDocument,
    GlobalWorkerOptions,
    type PDFDocumentLoadingTask,
    type PDFDocumentProxy,
    type RenderTask,
} from 'pdfjs-dist';
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import {
    markRaw,
    nextTick,
    onBeforeUnmount,
    ref,
    shallowRef,
    watch,
} from 'vue';

GlobalWorkerOptions.workerSrc = workerUrl;

const props = withDefaults(
    defineProps<{
        src: string;
        /** Scale applied to the page size in PDF points (1 = 612 × 792 for Letter). */
        zoom?: number;
    }>(),
    { zoom: 1 },
);

const emit = defineEmits<{
    loaded: [pages: number];
    error: [message: string];
}>();

type PageBox = {
    /** Zero-based page index. */
    index: number;
    /** Page size in PDF points. */
    width: number;
    height: number;
};

defineSlots<{
    /** Overlay rendered on top of each page, sized like the page container. */
    page?: (props: { page: number; width: number; height: number }) => unknown;
}>();

const pdf = shallowRef<PDFDocumentProxy | null>(null);
let loadingTask: PDFDocumentLoadingTask | null = null;
const pages = ref<PageBox[]>([]);
const status = ref<'loading' | 'ready' | 'error'>('loading');
const canvases = new Map<number, HTMLCanvasElement>();
const renders = new Map<number, RenderTask>();
let loadToken = 0;

function setCanvas(index: number, element: unknown): void {
    if (element instanceof HTMLCanvasElement) {
        canvases.set(index, element);
    } else {
        canvases.delete(index);
    }
}

async function load(): Promise<void> {
    const token = ++loadToken;
    status.value = 'loading';
    pages.value = [];
    await release();

    try {
        const task = getDocument({ url: props.src });
        loadingTask = task;
        const doc = markRaw(await task.promise);

        if (token !== loadToken) {
            await task.destroy();

            return;
        }

        const boxes: PageBox[] = [];
        for (let number = 1; number <= doc.numPages; number++) {
            const page = await doc.getPage(number);
            const { width, height } = page.getViewport({ scale: 1 });
            boxes.push({ index: number - 1, width, height });
        }

        if (token !== loadToken) {
            await task.destroy();

            return;
        }

        pdf.value = doc;
        pages.value = boxes;
        status.value = 'ready';
        emit('loaded', doc.numPages);

        await nextTick();
        await renderAll();
    } catch (error) {
        if (token !== loadToken) {
            return;
        }

        status.value = 'error';
        emit('error', error instanceof Error ? error.message : String(error));
    }
}

async function renderAll(): Promise<void> {
    const doc = pdf.value;

    if (!doc) {
        return;
    }

    await Promise.all(pages.value.map((box) => renderPage(doc, box)));
}

async function renderPage(doc: PDFDocumentProxy, box: PageBox): Promise<void> {
    const canvas = canvases.get(box.index);

    if (!canvas) {
        return;
    }

    renders.get(box.index)?.cancel();

    const ratio = window.devicePixelRatio || 1;
    const page = await doc.getPage(box.index + 1);
    const viewport = page.getViewport({ scale: props.zoom * ratio });

    // The canvas is drawn at device resolution but always fills its
    // container, which measures width × zoom CSS pixels.
    canvas.width = Math.floor(viewport.width);
    canvas.height = Math.floor(viewport.height);

    const task = page.render({ canvas, viewport });
    renders.set(box.index, task);

    try {
        await task.promise;
    } catch (error) {
        // A cancelled render (zoom changed mid-draw) is expected.
        if (
            (error as { name?: string }).name !== 'RenderingCancelledException'
        ) {
            throw error;
        }
    } finally {
        if (renders.get(box.index) === task) {
            renders.delete(box.index);
        }
    }
}

async function release(): Promise<void> {
    renders.forEach((task) => task.cancel());
    renders.clear();

    const task = loadingTask;
    loadingTask = null;
    pdf.value = null;

    if (task) {
        await task.destroy();
    }
}

watch(() => props.src, load, { immediate: true });
watch(
    () => props.zoom,
    () => {
        void renderAll();
    },
);

onBeforeUnmount(() => {
    loadToken++;
    void release();
});
</script>

<template>
    <div class="flex flex-col items-center gap-4" data-test="pdf-pages">
        <p
            v-if="status === 'loading'"
            class="py-10 text-sm text-muted-foreground"
        >
            Cargando documento…
        </p>
        <p
            v-else-if="status === 'error'"
            class="py-10 text-sm text-destructive"
        >
            No se pudo cargar el PDF.
        </p>

        <div
            v-for="box in pages"
            :key="box.index"
            class="relative shrink-0 bg-white shadow-[0_2px_12px_rgba(26,53,96,0.15)]"
            :style="{
                width: `${box.width * zoom}px`,
                height: `${box.height * zoom}px`,
            }"
            :data-page="box.index"
            data-test="pdf-page"
        >
            <canvas
                :ref="(element) => setCanvas(box.index, element)"
                class="absolute inset-0 block h-full w-full"
                :data-page="box.index"
            />
            <slot
                name="page"
                :page="box.index"
                :width="box.width"
                :height="box.height"
            />
        </div>
    </div>
</template>
