export const STORE_PREVIEW_MSG = 'store-theme-preview';
export const STORE_PREVIEW_ACK = 'store-theme-preview-ack';

/**
 * Envia o tema atualizado para o iframe de preview da loja.
 * @param {Window|null|undefined} win
 * @param {object} theme
 */
export function postStoreThemePreview(win, theme) {
    if (!win || typeof win.postMessage !== 'function') {
        return;
    }

    try {
        win.postMessage(
            {
                type: STORE_PREVIEW_MSG,
                theme,
            },
            '*',
        );
    } catch (_) {
        // Ignora falhas de postMessage (origem / iframe ainda carregando).
    }
}

/**
 * Responde ao editor que o preview aplicou o tema.
 * @param {MessageEvent} event
 */
export function ackStoreThemePreview(event) {
    if (!event?.source || typeof event.source.postMessage !== 'function') {
        return;
    }

    try {
        event.source.postMessage({ type: STORE_PREVIEW_ACK }, event.origin || '*');
    } catch (_) {
        // noop
    }
}
