<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { editor, store } from '@/routes/documents';
import LinksModal from './LinksModal.vue';
import type { DocumentItem, DocumentStatus } from '@/types';

type Filter = 'todos' | DocumentStatus;

const props = defineProps<{
    documents: DocumentItem[];
    /** Set right after sending a document: its links open straight away. */
    openLinks?: number | null;
    /** True right after sending: the links were just created. */
    linksGenerated?: boolean;
}>();

const statusChips: Record<
    DocumentStatus,
    { label: string; background: string; color: string }
> = {
    borrador: { label: 'Borrador', background: '#eef0f3', color: '#4b515a' },
    pendiente: {
        label: 'Pendiente de firma',
        background: '#fdf1e2',
        color: '#9a5a12',
    },
    completado: {
        label: 'Completado',
        background: '#e3f3f9',
        color: '#0f6d8e',
    },
};

const filterDefinitions: { key: Filter; label: string }[] = [
    { key: 'todos', label: 'Todos' },
    { key: 'borrador', label: 'Borradores' },
    { key: 'pendiente', label: 'Pendientes' },
    { key: 'completado', label: 'Completados' },
];

const activeFilter = ref<Filter>('todos');
const dragOver = ref(false);
const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
const linksOpen = ref(false);
const linksDocumentId = ref<number | null>(null);

const linksDocument = computed(
    () => props.documents.find((d) => d.id === linksDocumentId.value) ?? null,
);

const filters = computed(() =>
    filterDefinitions.map((filter) => ({
        ...filter,
        count:
            filter.key === 'todos'
                ? props.documents.length
                : props.documents.filter((d) => d.status === filter.key).length,
    })),
);

const rows = computed(() =>
    props.documents
        .filter(
            (d) =>
                activeFilter.value === 'todos' ||
                d.status === activeFilter.value,
        )
        .map((d) => ({
            id: d.id,
            name: d.name,
            extension: (d.name.split('.').pop() ?? 'pdf')
                .toUpperCase()
                .slice(0, 4),
            chip: statusChips[d.status],
            signers: d.signers.map((s) => s.name).join(', ') || '—',
            date: d.date,
        })),
);

/** A draft opens its editor; a sent or completed document opens its links. */
function openDocument(id: number): void {
    const document = props.documents.find((d) => d.id === id);

    if (!document) {
        return;
    }

    if (document.status === 'borrador') {
        router.visit(editor(document.id).url);

        return;
    }

    linksDocumentId.value = document.id;
    linksOpen.value = true;
}

onMounted(() => {
    const sent = props.documents.find((d) => d.id === props.openLinks);

    if (sent) {
        linksDocumentId.value = sent.id;
        linksOpen.value = true;

        if (props.linksGenerated) {
            toast.success(
                `Enlaces generados para ${sent.links.length} ${sent.links.length === 1 ? 'firmante' : 'firmantes'}`,
            );
        }
    }
});

function openPicker(): void {
    if (!uploading.value) {
        fileInput.value?.click();
    }
}

function upload(file: File | undefined): void {
    if (!file || uploading.value) {
        return;
    }

    uploading.value = true;

    router.post(
        store().url,
        { file },
        {
            forceFormData: true,
            onError: (errors) => {
                toast.error(errors.file ?? 'No se pudo subir el documento.');
            },
            onFinish: () => {
                uploading.value = false;
            },
        },
    );
}

function onPick(event: Event): void {
    const input = event.target as HTMLInputElement;
    upload(input.files?.[0]);
    input.value = '';
}

function onDrop(event: DragEvent): void {
    dragOver.value = false;
    upload(event.dataTransfer?.files?.[0]);
}
</script>

<template>
    <Head title="Documentos" />

    <div
        class="flex flex-1 flex-col gap-[clamp(16px,3vw,24px)] px-[clamp(16px,4vw,40px)] py-[clamp(16px,4vw,32px)]"
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1
                class="text-[clamp(24px,5vw,28px)] font-semibold tracking-[-0.01em]"
            >
                Documentos
            </h1>
            <Button
                type="button"
                data-test="upload-button"
                :disabled="uploading"
                @click="openPicker"
            >
                {{ uploading ? 'Subiendo…' : 'Subir documento' }}
            </Button>
            <input
                ref="fileInput"
                type="file"
                accept="application/pdf,.pdf"
                class="hidden"
                data-test="upload-input"
                @change="onPick"
            />
        </div>

        <div
            role="button"
            tabindex="0"
            data-test="upload-zone"
            class="flex cursor-pointer flex-col items-center gap-1.5 rounded-[14px] border-[1.5px] border-dashed p-[clamp(20px,4vw,28px)] text-center outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            :class="
                dragOver
                    ? 'border-primary bg-[#e8f4f9]'
                    : 'border-[#c6ced8] bg-white'
            "
            @click="openPicker"
            @keydown.enter.prevent="openPicker"
            @dragover.prevent="dragOver = true"
            @dragleave="dragOver = false"
            @drop.prevent="onDrop"
        >
            <span class="hidden text-[15px] font-semibold desk:inline">
                Arrastra un PDF aquí o haz clic para subirlo
            </span>
            <span class="text-[15px] font-semibold desk:hidden">
                Toca para subir un documento
            </span>
            <span class="text-[13px] text-muted-foreground"
                >Solo PDF, hasta 10 MB</span
            >
        </div>

        <div class="flex gap-1.5 overflow-x-auto border-b border-border">
            <button
                v-for="filter in filters"
                :key="filter.key"
                type="button"
                class="-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2.5 text-sm font-semibold whitespace-nowrap"
                :class="
                    activeFilter === filter.key
                        ? 'border-primary text-foreground'
                        : 'border-transparent text-muted-foreground'
                "
                :data-test="`filter-${filter.key}`"
                @click="activeFilter = filter.key"
            >
                {{ filter.label }}
                <span
                    class="rounded-full bg-[#eef0f3] px-2 py-0.5 text-xs text-[#4b515a]"
                    >{{ filter.count }}</span
                >
            </button>
        </div>

        <div
            class="overflow-hidden rounded-[14px] border border-border bg-white"
        >
            <div class="hidden desk:block">
                <div
                    class="grid grid-cols-[minmax(0,2.4fr)_minmax(0,1.2fr)_minmax(0,1.4fr)_minmax(0,0.9fr)] gap-4 border-b border-border px-5 py-3 text-xs font-semibold tracking-[0.05em] text-muted-foreground uppercase"
                >
                    <span>Documento</span>
                    <span>Estado</span>
                    <span>Firmantes</span>
                    <span>Actualizado</span>
                </div>
                <div
                    v-for="row in rows"
                    :key="row.id"
                    role="button"
                    tabindex="0"
                    class="grid cursor-pointer grid-cols-[minmax(0,2.4fr)_minmax(0,1.2fr)_minmax(0,1.4fr)_minmax(0,0.9fr)] items-center gap-4 border-b border-[#eef1f5] px-5 py-4 text-sm outline-none last:border-b-0 hover:bg-[#f6f8fb] focus-visible:bg-[#f6f8fb]"
                    data-test="document-row"
                    @click="openDocument(row.id)"
                    @keydown.enter.prevent="openDocument(row.id)"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <div
                            class="flex h-[42px] w-[34px] shrink-0 items-end justify-center rounded-sm border border-input bg-white pb-1 text-[8px] font-bold text-primary"
                        >
                            {{ row.extension }}
                        </div>
                        <span class="truncate font-semibold">{{
                            row.name
                        }}</span>
                    </div>
                    <span
                        class="justify-self-start rounded-full px-2.5 py-[5px] text-xs font-semibold"
                        :style="{
                            background: row.chip.background,
                            color: row.chip.color,
                        }"
                        >{{ row.chip.label }}</span
                    >
                    <span class="truncate text-muted-foreground">{{
                        row.signers
                    }}</span>
                    <span class="text-muted-foreground">{{ row.date }}</span>
                </div>
            </div>

            <div class="desk:hidden">
                <div
                    v-for="row in rows"
                    :key="row.id"
                    role="button"
                    tabindex="0"
                    class="flex cursor-pointer items-start gap-3 border-b border-[#eef1f5] px-4 py-3.5 outline-none last:border-b-0 focus-visible:bg-[#f6f8fb] active:bg-[#f6f8fb]"
                    data-test="document-card"
                    @click="openDocument(row.id)"
                    @keydown.enter.prevent="openDocument(row.id)"
                >
                    <div
                        class="flex h-[42px] w-[34px] shrink-0 items-end justify-center rounded-sm border border-input bg-white pb-1 text-[8px] font-bold text-primary"
                    >
                        {{ row.extension }}
                    </div>
                    <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                        <span class="text-sm leading-[1.35] font-semibold">{{
                            row.name
                        }}</span>
                        <span class="truncate text-xs text-muted-foreground">{{
                            row.signers
                        }}</span>
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="rounded-full px-[9px] py-1 text-xs font-semibold"
                                :style="{
                                    background: row.chip.background,
                                    color: row.chip.color,
                                }"
                                >{{ row.chip.label }}</span
                            >
                            <span class="text-xs text-muted-foreground">{{
                                row.date
                            }}</span>
                        </div>
                    </div>
                    <span
                        class="self-center text-lg leading-none text-[#aab1bb]"
                        >›</span
                    >
                </div>
            </div>

            <div
                v-if="rows.length === 0"
                class="p-10 text-center text-sm text-muted-foreground"
            >
                No hay documentos en esta vista.
            </div>
        </div>

        <LinksModal v-model:open="linksOpen" :document="linksDocument" />
    </div>
</template>
