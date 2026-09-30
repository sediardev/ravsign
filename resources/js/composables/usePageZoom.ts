import { useElementSize, useMediaQuery, useWindowSize } from '@vueuse/core';
import { computed, ref, type Ref } from 'vue';
import { PAGE_WIDTH } from '@/lib/documents';

/** Bounds and step for the manual zoom in/out controls. */
const MANUAL_ZOOM_MIN = 0.5;
const MANUAL_ZOOM_MAX = 2.5;
const MANUAL_ZOOM_STEP = 0.1;

/**
 * Scale of the PDF pages. On desktop they are shown at their real size; below
 * 760px (the design's mobile breakpoint) they shrink to fit the scroll area.
 * On top of that automatic scale, the user can zoom in or out manually to see
 * a page more closely, e.g. to place a signature field more precisely.
 *
 * `scroller` is the element with 12px of padding per side that holds the pages.
 * Its measured content width already leaves out a classic scrollbar, so the
 * page never overflows. Until it is measured, it falls back to the window
 * width minus that padding.
 */
export function usePageZoom(scroller: Ref<HTMLElement | null>) {
    const isMobile = useMediaQuery('(max-width: 759px)');
    const { width: windowWidth } = useWindowSize();
    const { width: scrollerWidth } = useElementSize(scroller);

    const autoZoom = computed(() => {
        if (!isMobile.value) {
            return 1;
        }

        const available =
            scrollerWidth.value > 0
                ? scrollerWidth.value
                : windowWidth.value - 24;

        return Math.min(1, available / PAGE_WIDTH);
    });

    /** Multiplier over `autoZoom`, controlled by `zoomIn`/`zoomOut`/`resetZoom`. */
    const manualZoom = ref(1);

    const zoom = computed(() => autoZoom.value * manualZoom.value);
    const canZoomIn = computed(() => manualZoom.value < MANUAL_ZOOM_MAX - 1e-9);
    const canZoomOut = computed(() => manualZoom.value > MANUAL_ZOOM_MIN + 1e-9);

    function zoomIn(): void {
        manualZoom.value = Math.min(
            MANUAL_ZOOM_MAX,
            Math.round((manualZoom.value + MANUAL_ZOOM_STEP) * 100) / 100,
        );
    }

    function zoomOut(): void {
        manualZoom.value = Math.max(
            MANUAL_ZOOM_MIN,
            Math.round((manualZoom.value - MANUAL_ZOOM_STEP) * 100) / 100,
        );
    }

    function resetZoom(): void {
        manualZoom.value = 1;
    }

    return {
        isMobile,
        zoom,
        manualZoom,
        canZoomIn,
        canZoomOut,
        zoomIn,
        zoomOut,
        resetZoom,
    };
}
