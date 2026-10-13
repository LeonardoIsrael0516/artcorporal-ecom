/**
 * @param {File|Blob|null|undefined} file
 * @returns {boolean}
 */
export function isHeicFile(file) {
    if (!file) {
        return false;
    }
    const name = String(file.name || '').toLowerCase();
    const type = String(file.type || '').toLowerCase();

    return (
        name.endsWith('.heic') ||
        name.endsWith('.heif') ||
        type.includes('heic') ||
        type.includes('heif')
    );
}

/**
 * Convert HEIC/HEIF to JPEG in the browser so uploads work without server ImageMagick.
 *
 * @param {File} file
 * @returns {Promise<File>}
 */
export async function normalizeImageFile(file) {
    if (!file || !isHeicFile(file)) {
        return file;
    }

    const { default: heic2any } = await import('heic2any');
    const converted = await heic2any({
        blob: file,
        toType: 'image/jpeg',
        quality: 0.85,
    });
    const blob = Array.isArray(converted) ? converted[0] : converted;
    const base = String(file.name || 'image').replace(/\.(heic|heif)$/i, '') || 'image';

    return new File([blob], `${base}.jpg`, {
        type: 'image/jpeg',
        lastModified: Date.now(),
    });
}
