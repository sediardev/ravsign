<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import type { DocumentItem, DocumentStatus } from '@/types';

type Filter = 'todos' | DocumentStatus;

const props = defineProps<{
    documents: DocumentItem[];
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

const filters = computed(() =>
    filterDefinitions.map((filter) => ({
        ...filter,
        count:
            filter.key === 'todos'
                ? props.documents.length
                : props.documents.filter((d) => d.status === filter.key)
                      .length,
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

function notifyUploadUnavailable(): void {
    toast.info('La subida de documentos estará disponible pronto.');
}

function onDrop(): void {
    dragOver.value = false;
    notifyUploadUnavailable();
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
                @click="notifyUploadUnavailable"
            >
                Subir documento
            </Button>
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
            @click="notifyUploadUnavailable"
            @keydown.enter.prevent="notifyUploadUnavailable"
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
                >PDF, Word o imagen</span
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

        <div class="overflow-hidden rounded-[14px] border border-border bg-white">
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
                    class="grid grid-cols-[minmax(0,2.4fr)_minmax(0,1.2fr)_minmax(0,1.4fr)_minmax(0,0.9fr)] items-center gap-4 border-b border-[#eef1f5] px-5 py-4 text-sm last:border-b-0"
                    data-test="document-row"
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
                    class="flex items-start gap-3 border-b border-[#eef1f5] px-4 py-3.5 last:border-b-0"
                    data-test="document-card"
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
                    <span class="self-center text-lg leading-none text-[#aab1bb]"
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
    </div>
</template>
