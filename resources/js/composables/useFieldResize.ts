import { onBeforeUnmount, ref, type Ref } from 'vue';
import { clampFieldSize } from '@/lib/documents';

export type ResizeInit = {
    fieldId: string;
    /** The field's own position and size when the drag starts. */
    x: number;
    y: number;
    width: number;
    height: number;
    /** Real size of the field's page, in PDF points. */
    pageWidth: number;
    pageHeight: number;
};

export type ResizeResult = {
    fieldId: string;
    width: number;
    height: number;
};

/**
 * Drag-resize of a field's bottom-right corner.
 *
 * Reports every intermediate size through `onResize` (for live visual
 * feedback) and the final one through `onFinish` (to save to the server).
 */
export function useFieldResize(options: {
    zoom: Ref<number>;
    onResize: (result: ResizeResult) => void;
    onFinish: (result: ResizeResult) => void;
}) {
    const resizing = ref(false);
    let origin = { x: 0, y: 0 };
    let init: ResizeInit | null = null;
    let last: ResizeResult | null = null;

    function sizeAt(event: PointerEvent): ResizeResult {
        const state = init as ResizeInit;
        const dx = (event.clientX - origin.x) / options.zoom.value;
        const dy = (event.clientY - origin.y) / options.zoom.value;

        const { width, height } = clampFieldSize({
            width: state.width + dx,
            height: state.height + dy,
            x: state.x,
            y: state.y,
            pageWidth: state.pageWidth,
            pageHeight: state.pageHeight,
        });

        return { fieldId: state.fieldId, width, height };
    }

    function onMove(event: PointerEvent): void {
        if (!init) {
            return;
        }

        last = sizeAt(event);
        options.onResize(last);
    }

    function onUp(event: PointerEvent): void {
        if (init && last) {
            options.onFinish(sizeAt(event));
        }

        stop();
    }

    function onCancel(): void {
        stop();
    }

    function stop(): void {
        resizing.value = false;
        init = null;
        last = null;
        window.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
        window.removeEventListener('pointercancel', onCancel);
    }

    function start(event: PointerEvent, initial: ResizeInit): void {
        if (event.button !== 0 || init) {
            return;
        }

        (event.currentTarget as HTMLElement | null)?.setPointerCapture?.(
            event.pointerId,
        );

        origin = { x: event.clientX, y: event.clientY };
        init = initial;
        last = null;
        resizing.value = true;

        window.addEventListener('pointermove', onMove);
        window.addEventListener('pointerup', onUp);
        window.addEventListener('pointercancel', onCancel);
    }

    onBeforeUnmount(stop);

    return { resizing, start };
}
