import type { FieldType } from '@/types';

/** Base page size in PDF points (Letter). */
export const PAGE_WIDTH = 612;
export const PAGE_HEIGHT = 792;

/** Fields snap to this grid, in points, when dropped. */
export const GRID = 8;

/** Signer colors, in the order they are assigned. */
export const SIGNER_COLORS = ['#1792bb', '#7a4fc9', '#d27a1f', '#2f9e6b'];

/** Field size in PDF points. */
export const FIELD_SIZES: Record<FieldType, { width: number; height: number }> =
    {
        firma: { width: 176, height: 58 },
        iniciales: { width: 92, height: 58 },
        fecha: { width: 132, height: 36 },
        nombre: { width: 176, height: 36 },
    };

export const FIELD_LABELS: Record<FieldType, string> = {
    firma: 'Firma',
    iniciales: 'Iniciales',
    fecha: 'Fecha',
    nombre: 'Nombre',
};

export type PageRect = {
    left: number;
    top: number;
    width: number;
    height: number;
};

export type Placement = {
    /** Top-left corner in PDF points, snapped to the grid. */
    xPoints: number;
    yPoints: number;
    /** Same corner as a percentage of the page (0-100), as stored. */
    x: number;
    y: number;
};

/**
 * Where a field ends up when it is dropped on a page.
 *
 * `grab` is the offset, in screen pixels, from the top-left of the field to the
 * pointer. The corner is snapped to the 8-point grid and kept inside the page,
 * also on the grid.
 */
export function placeField(input: {
    pointer: { x: number; y: number };
    grab: { x: number; y: number };
    pageRect: PageRect;
    zoom: number;
    type: FieldType;
}): Placement {
    const { pointer, grab, pageRect, zoom, type } = input;
    const size = FIELD_SIZES[type];
    const pageWidth = pageRect.width / zoom;
    const pageHeight = pageRect.height / zoom;

    const snap = (value: number, max: number): number =>
        Math.min(
            Math.max(0, Math.round(value / GRID) * GRID),
            Math.floor(max / GRID) * GRID,
        );

    const xPoints = snap(
        (pointer.x - grab.x - pageRect.left) / zoom,
        pageWidth - size.width,
    );
    const yPoints = snap(
        (pointer.y - grab.y - pageRect.top) / zoom,
        pageHeight - size.height,
    );

    return {
        xPoints,
        yPoints,
        x: Math.round((xPoints / pageWidth) * 1e6) / 1e4,
        y: Math.round((yPoints / pageHeight) * 1e6) / 1e4,
    };
}
