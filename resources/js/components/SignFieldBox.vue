<script setup lang="ts">
import { computed } from 'vue';
import {
    FIELD_LABELS,
    FIELD_SIZES,
    PAGE_HEIGHT,
    PAGE_WIDTH,
} from '@/lib/documents';
import type { SignField, Signer } from '@/types';

/** What a field needs to draw its owner: the public part of a signer. */
type FieldOwner = Pick<Signer, 'name' | 'siglas' | 'color'>;

const props = withDefaults(
    defineProps<{
        field: SignField;
        signer?: FieldOwner;
        /** Page size in PDF points, for pages that are not Letter. */
        pageWidth?: number;
        pageHeight?: number;
        /** Scale of the page container, so text follows the zoom. */
        zoom?: number;
        selected?: boolean;
        /** Signing screen: a field of the current signer, ready to be clicked. */
        own?: boolean;
        /** Signing screen: a field of someone else. */
        dimmed?: boolean;
    }>(),
    {
        pageWidth: PAGE_WIDTH,
        pageHeight: PAGE_HEIGHT,
        zoom: 1,
        selected: false,
        own: false,
        dimmed: false,
    },
);

const FALLBACK_COLOR = '#6b7078';

const color = computed(() => props.signer?.color ?? FALLBACK_COLOR);
const size = computed(() => FIELD_SIZES[props.field.type]);
const label = computed(() => FIELD_LABELS[props.field.type]);
const isImage = computed(
    () =>
        props.field.value !== null &&
        /^(data:|https?:|\/)/.test(props.field.value),
);
const signed = computed(() => props.field.value !== null);

const boxStyle = computed(() => ({
    left: `${props.field.x}%`,
    top: `${props.field.y}%`,
    width: `${(size.value.width / props.pageWidth) * 100}%`,
    height: `${(size.value.height / props.pageHeight) * 100}%`,
    borderColor: color.value,
    borderStyle: signed.value ? 'solid' : 'dashed',
    // `1f` / `33` are alpha suffixes over the signer color (12 % / 20 %).
    backgroundColor: signed.value
        ? 'transparent'
        : `${color.value}${props.own || props.selected ? '33' : '1f'}`,
    fontSize: `${11 * props.zoom}px`,
    boxShadow: props.selected
        ? `0 0 0 ${2 * props.zoom}px ${color.value}66`
        : undefined,
}));

const badgeStyle = computed(() => ({
    backgroundColor: color.value,
    minWidth: `${18 * props.zoom}px`,
    height: `${18 * props.zoom}px`,
    fontSize: `${9 * props.zoom}px`,
}));
</script>

<template>
    <div
        class="absolute flex touch-none items-center justify-center overflow-hidden rounded-[4px] border-[1.5px] select-none"
        :class="[
            dimmed ? 'pointer-events-none opacity-40' : '',
            own ? 'cursor-pointer' : '',
            selected ? 'z-10' : '',
        ]"
        :style="boxStyle"
        :data-field-id="field.id"
        :data-selected="selected || undefined"
        data-test="sign-field"
    >
        <img
            v-if="isImage"
            :src="field.value ?? undefined"
            :alt="`Firma de ${signer?.name ?? 'firmante'}`"
            class="pointer-events-none h-full w-full object-contain p-[3px]"
            draggable="false"
        />
        <span
            v-else-if="signed"
            class="pointer-events-none truncate px-2 font-display font-semibold text-[#1a3560] italic"
            :style="{ fontSize: `${16 * zoom}px` }"
            >{{ field.value }}</span
        >
        <template v-else>
            <span
                class="pointer-events-none absolute top-1 left-1 inline-flex items-center justify-center rounded-full px-1 leading-none font-bold text-white"
                :style="badgeStyle"
                >{{ signer?.siglas ?? '?' }}</span
            >
            <span
                class="pointer-events-none font-semibold tracking-[0.02em]"
                :style="{ color }"
                >{{ label }}</span
            >
        </template>
    </div>
</template>
