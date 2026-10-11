/**
 * Tope de letras iguales seguidas para los campos de texto del chat: como
 * máximo 2, nunca 3 («puuuta» no se puede escribir; «calle» y «perro» sí).
 *
 * Es la mitad del front de ChatLineReviewer: el servidor sigue leyendo las
 * letras repetidas (RepeatedLetters.php) porque no puede fiarse de lo que
 * llegue, pero con este tope el front no deja escribir las que ninguna
 * palabra de español o inglés necesita. Funciona en el navegador (script
 * clásico, deja `LimitRepeatedLetters` global y se engancha solo a los campos
 * con `data-max-repeat`) y en Node (require).
 *
 * Dos letras son «la misma» sin importar mayúsculas ni tildes: «uúU» son
 * tres. Espacios, signos y dígitos cortan la racha. Las marcas combinables y
 * los caracteres invisibles (U+200B…) no la cortan: no sirven para esquivarla.
 *
 * Atención: tampoco deja pasar «III» (Carlos III) ni «www»: pásalos en
 * `keep` si el campo los necesita, p. ej. [/\b[IVXLCDM]{3,}\b/, /\bwww\b/i].
 */
(function (root) {
    const DEFAULT_MAX = 2;
    const isLetter = (char) => /\p{L}/u.test(char);
    const isTransparent = (char) => /[\p{M}\p{Cf}]/u.test(char);
    const baseLetter = (char) => char.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase();

    function settings(options) {
        const max = Number.isInteger(options && options.max) && options.max >= 1 ? options.max : DEFAULT_MAX;
        return { max, keep: (options && options.keep) || [], onLimit: options && options.onLimit };
    }

    /** Rangos [desde, hasta) del texto que `keep` protege, en unidades UTF-16. */
    function protectedRanges(text, keep) {
        const ranges = [];
        for (const pattern of keep) {
            const flags = pattern.flags.includes('g') ? pattern.flags : pattern.flags + 'g';
            for (const found of text.matchAll(new RegExp(pattern.source, flags))) {
                if (found[0].length > 0) ranges.push([found.index, found.index + found[0].length]);
            }
        }
        return ranges;
    }

    /**
     * El texto recortado y, para cada posición del original, cuánto mide el
     * recortado hasta ahí (para recolocar el cursor).
     * @returns {{text: string, map: number[]}}
     */
    function limitWithMap(text, options) {
        const { max, keep } = settings(options);
        const ranges = protectedRanges(text, keep);
        const map = new Array(text.length + 1);
        let out = '', last = '', run = 0, lastKept = true, index = 0;

        for (const char of text) {
            for (let unit = 0; unit < char.length; unit++) map[index + unit] = out.length;
            if (ranges.some(([from, to]) => index >= from && index < to)) {
                out += char; last = ''; run = 0; lastKept = true;
            } else if (isTransparent(char)) {
                if (lastKept) out += char;
            } else if (!isLetter(char)) {
                out += char; last = ''; run = 0; lastKept = true;
            } else {
                const base = baseLetter(char);
                run = base === last ? run + 1 : 1;
                last = base;
                lastKept = run <= max;
                if (lastKept) out += char;
            }
            index += char.length;
        }
        map[text.length] = out.length;
        return { text: out, map };
    }

    const limitRepeatedLetters = (text, options) => limitWithMap(String(text), options).text;
    const hasTooManyRepeatedLetters = (text, options) => limitRepeatedLetters(text, options) !== String(text);

    /**
     * Recorta lo que se escribe o pega en `element` (input o textarea),
     * conserva el cursor y no toca el texto mientras un IME compone. Avisa con
     * `options.onLimit({element, removed, max})` y con el evento
     * `repeated-letters-limited`. Devuelve la función que lo desengancha.
     */
    function attach(element, options) {
        const config = settings(options);
        let composing = false;

        const clean = () => {
            const value = element.value;
            const { text, map } = limitWithMap(value, config);
            if (text === value) return;
            const start = map[element.selectionStart == null ? value.length : element.selectionStart];
            const end = map[element.selectionEnd == null ? value.length : element.selectionEnd];
            element.value = text;
            try { element.setSelectionRange(start, end); } catch (error) { /* tipos sin selección: email, number… */ }
            const detail = { element, removed: value.length - text.length, max: config.max };
            if (config.onLimit) config.onLimit(detail);
            if (typeof CustomEvent === 'function') element.dispatchEvent(new CustomEvent('repeated-letters-limited', { bubbles: true, detail }));
        };
        const onInput = (event) => { if (!composing && !event.isComposing) clean(); };
        const onStart = () => { composing = true; };
        const onEnd = () => { composing = false; clean(); };

        element.addEventListener('input', onInput);
        element.addEventListener('compositionstart', onStart);
        element.addEventListener('compositionend', onEnd);
        clean();

        return () => {
            element.removeEventListener('input', onInput);
            element.removeEventListener('compositionstart', onStart);
            element.removeEventListener('compositionend', onEnd);
        };
    }

    /** Engancha todos los campos de `container` con `data-max-repeat` (su valor es el tope; vacío = 2). */
    function attachAll(container, selector) {
        return Array.from((container || root.document).querySelectorAll(selector || '[data-max-repeat]'))
            .map((element) => attach(element, { max: Number.parseInt(element.dataset.maxRepeat, 10) }));
    }

    const api = { DEFAULT_MAX, limitRepeatedLetters, hasTooManyRepeatedLetters, limitWithMap, attach, attachAll };

    if (typeof module !== 'undefined' && module.exports) {
        module.exports = api;
    } else {
        root.LimitRepeatedLetters = api;
        if (root.document) {
            const start = () => attachAll(root.document);
            root.document.readyState === 'loading' ? root.document.addEventListener('DOMContentLoaded', start) : start();
        }
    }
})(typeof globalThis !== 'undefined' ? globalThis : this);
