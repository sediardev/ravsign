/** Margin kept around a trimmed signature, in canvas pixels. */
const TRIM_PADDING = 6;

/** Widest image kept for an uploaded signature, in pixels. */
const MAX_IMAGE_WIDTH = 700;

/** Largest file accepted when choosing an image, in bytes. */
export const MAX_IMAGE_FILE_BYTES = 5 * 1024 * 1024;

export const ACCEPTED_IMAGE_TYPES = ['image/png', 'image/jpeg'];

/**
 * PNG data URL of the drawn strokes, cropped to their bounds with a small
 * margin. Null when nothing has been drawn.
 */
export function trimCanvas(canvas: HTMLCanvasElement): string | null {
    const context = canvas.getContext('2d');

    if (!context) {
        return null;
    }

    const { width, height } = canvas;
    const { data } = context.getImageData(0, 0, width, height);

    let minX = width;
    let minY = height;
    let maxX = -1;
    let maxY = -1;

    for (let y = 0; y < height; y++) {
        for (let x = 0; x < width; x++) {
            if (data[(y * width + x) * 4 + 3] > 0) {
                minX = Math.min(minX, x);
                maxX = Math.max(maxX, x);
                minY = Math.min(minY, y);
                maxY = Math.max(maxY, y);
            }
        }
    }

    if (maxX < 0) {
        return null;
    }

    const left = Math.max(0, minX - TRIM_PADDING);
    const top = Math.max(0, minY - TRIM_PADDING);
    const right = Math.min(width, maxX + 1 + TRIM_PADDING);
    const bottom = Math.min(height, maxY + 1 + TRIM_PADDING);

    const cropped = document.createElement('canvas');
    cropped.width = right - left;
    cropped.height = bottom - top;
    cropped
        .getContext('2d')
        ?.drawImage(
            canvas,
            left,
            top,
            cropped.width,
            cropped.height,
            0,
            0,
            cropped.width,
            cropped.height,
        );

    return cropped.toDataURL('image/png');
}

/**
 * PNG data URL of a chosen PNG or JPG file, scaled down when it is wide.
 * Rejects with a message meant for the user.
 */
export function fileToPngDataUrl(file: File): Promise<string> {
    if (!ACCEPTED_IMAGE_TYPES.includes(file.type)) {
        return Promise.reject(new Error('La imagen debe ser PNG o JPG.'));
    }

    if (file.size > MAX_IMAGE_FILE_BYTES) {
        return Promise.reject(
            new Error('La imagen no puede superar los 5 MB.'),
        );
    }

    return new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const image = new Image();

        image.onload = () => {
            URL.revokeObjectURL(url);

            const scale = Math.min(1, MAX_IMAGE_WIDTH / image.naturalWidth);
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(1, Math.round(image.naturalWidth * scale));
            canvas.height = Math.max(
                1,
                Math.round(image.naturalHeight * scale),
            );

            const context = canvas.getContext('2d');

            if (!context) {
                reject(new Error('No se pudo leer la imagen.'));

                return;
            }

            // A JPG has no transparency: put it on white rather than black.
            if (file.type === 'image/jpeg') {
                context.fillStyle = '#ffffff';
                context.fillRect(0, 0, canvas.width, canvas.height);
            }

            context.drawImage(image, 0, 0, canvas.width, canvas.height);
            resolve(canvas.toDataURL('image/png'));
        };

        image.onerror = () => {
            URL.revokeObjectURL(url);
            reject(new Error('No se pudo leer la imagen.'));
        };

        image.src = url;
    });
}
