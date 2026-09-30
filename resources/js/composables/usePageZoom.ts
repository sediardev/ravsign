import { useElementSize, useMediaQuery, useWindowSize } from '@vueuse/core';
import { computed, type Ref } from 'vue';
import { PAGE_WIDTH } from '@/lib/documents';

/**
 * Scale of the PDF pages. On desktop they are shown at their real size; below
 * 760px (the design's mobile breakpoint) they shrink to fit the scroll area.
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

    const zoom = computed(() => {
        if (!isMobile.value) {
            return 1;
        }

        const available =
            scrollerWidth.value > 0
                ? scrollerWidth.value
                : windowWidth.value - 24;

        return Math.min(1, available / PAGE_WIDTH);
    });

    return { isMobile, zoom };
}
