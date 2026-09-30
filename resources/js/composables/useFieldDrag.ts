import { onBeforeUnmount, ref, type Ref } from 'vue';
import { placeField, type Placement } from '@/lib/documents';
import type { FieldType } from '@/types';

/** Pixels the pointer must travel before a press becomes a drag. */
const DRAG_THRESHOLD = 4;
/** Distance from the scroller edge where dragging auto-scrolls, and its speed. */
const EDGE = 64;
const SCROLL_STEP = 14;

export type DragInit = {
    /** A new field from the palette, or an existing one being moved. */
    kind: 'new' | 'move';
    fieldId?: string;
    signerId: string;
    type: FieldType;
    /** Field size in PDF points: the default for a new field, or the field's own for a move. */
    size: { width: number; height: number };
    /** Offset in screen pixels from the top-left of the field to the pointer. */
    grab: { x: number; y: number };
};

export type DragState = DragInit & {
    /** Pointer position in viewport coordinates. */
    pointer: { x: number; y: number };
    /** True once the pointer moved past the threshold. */
    started: boolean;
};

export type DropResult = {
    kind: DragInit['kind'];
    fieldId?: string;
    signerId: string;
    type: FieldType;
    page: number;
} & Placement;

/**
 * Pointer-based drag and drop of fields over the PDF pages.
 *
 * Uses Pointer Events instead of HTML5 drag and drop so it also works on touch.
 * A drop outside every page cancels the drag.
 */
export function useFieldDrag(options: {
    zoom: Ref<number>;
    /** Scrollable area that holds the pages, scrolled while dragging near its edges. */
    scroller: Ref<HTMLElement | null>;
    onDrop: (drop: DropResult) => void;
}) {
    const drag = ref<DragState | null>(null);
    let origin = { x: 0, y: 0 };
    let frame = 0;

    function pageAt(x: number, y: number): HTMLElement | null {
        const hit = document
            .elementsFromPoint(x, y)
            .find((element) => element.matches('[data-test="pdf-page"]'));

        return (hit as HTMLElement | undefined) ?? null;
    }

    function autoScroll(): void {
        const state = drag.value;
        const scroller = options.scroller.value;

        if (state?.started && scroller) {
            const rect = scroller.getBoundingClientRect();

            if (state.pointer.y < rect.top + EDGE) {
                scroller.scrollTop -= SCROLL_STEP;
            } else if (state.pointer.y > rect.bottom - EDGE) {
                scroller.scrollTop += SCROLL_STEP;
            }
        }

        frame = requestAnimationFrame(autoScroll);
    }

    function onMove(event: PointerEvent): void {
        const state = drag.value;

        if (!state) {
            return;
        }

        state.pointer = { x: event.clientX, y: event.clientY };

        if (
            !state.started &&
            Math.hypot(event.clientX - origin.x, event.clientY - origin.y) >=
                DRAG_THRESHOLD
        ) {
            state.started = true;
        }
    }

    function finish(event: PointerEvent | null): void {
        const state = drag.value;
        stop();

        if (!state || !state.started || !event) {
            return;
        }

        const page = pageAt(event.clientX, event.clientY);

        if (!page) {
            return;
        }

        const rect = page.getBoundingClientRect();

        options.onDrop({
            kind: state.kind,
            fieldId: state.fieldId,
            signerId: state.signerId,
            type: state.type,
            page: Number(page.dataset.page),
            ...placeField({
                pointer: { x: event.clientX, y: event.clientY },
                grab: state.grab,
                pageRect: {
                    left: rect.left,
                    top: rect.top,
                    width: rect.width,
                    height: rect.height,
                },
                zoom: options.zoom.value,
                size: state.size,
            }),
        });
    }

    function onUp(event: PointerEvent): void {
        finish(event);
    }

    function onCancel(): void {
        finish(null);
    }

    function onKey(event: KeyboardEvent): void {
        if (event.key === 'Escape') {
            finish(null);
        }
    }

    function stop(): void {
        drag.value = null;
        cancelAnimationFrame(frame);
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
        window.removeEventListener('pointercancel', onCancel);
        window.removeEventListener('keydown', onKey);
    }

    /** Begin a drag from a pointerdown event. */
    function start(event: PointerEvent, init: DragInit): void {
        if (event.button !== 0 || drag.value) {
            return;
        }

        (event.currentTarget as HTMLElement | null)?.setPointerCapture?.(
            event.pointerId,
        );

        origin = { x: event.clientX, y: event.clientY };
        drag.value = {
            ...init,
            pointer: { x: event.clientX, y: event.clientY },
            started: false,
        };

        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
        window.addEventListener('pointercancel', onCancel);
        window.addEventListener('keydown', onKey);
        frame = requestAnimationFrame(autoScroll);
    }

    onBeforeUnmount(stop);

    return { drag, start };
}
