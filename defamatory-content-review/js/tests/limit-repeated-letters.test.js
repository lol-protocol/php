const test = require('node:test');
const assert = require('node:assert/strict');
const {
    DEFAULT_MAX, limitRepeatedLetters, hasTooManyRepeatedLetters, limitWithMap, attach, attachAll,
} = require('../limit-repeated-letters.js');

// El mínimo de un campo de texto que usa attach(): valor, cursor y eventos.
class FakeInput {
    constructor(value = '') {
        this.value = value;
        this.selectionStart = this.selectionEnd = value.length;
        this.dataset = {};
        this.listeners = {};
        this.dispatched = [];
    }
    addEventListener(type, fn) { (this.listeners[type] ||= []).push(fn); }
    removeEventListener(type, fn) { this.listeners[type] = (this.listeners[type] || []).filter((f) => f !== fn); }
    setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end; }
    dispatchEvent(event) { this.dispatched.push(event); return true; }
    fire(type, props = {}) { (this.listeners[type] || []).slice().forEach((fn) => fn({ type, ...props })); }
    type(value, caret = value.length) { this.value = value; this.selectionStart = this.selectionEnd = caret; this.fire('input'); }
}

test('el tope por defecto es 2', () => assert.equal(DEFAULT_MAX, 2));

test('recorta a 2 las rachas de 3 o más letras iguales', () => {
    assert.equal(limitRepeatedLetters('puuuuta'), 'puuta');
    assert.equal(limitRepeatedLetters('aaa'), 'aa');
    assert.equal(limitRepeatedLetters('hooolaaa mmmundo'), 'hoolaa mmundo');
});

test('deja pasar las palabras con letra doble legítima', () => {
    for (const word of ['perro', 'calle', 'llamar', 'coordinar', 'Hannah', 'class', 'looser', 'aa', 'ñoño']) {
        assert.equal(limitRepeatedLetters(word), word);
    }
});

test('«la misma letra» no distingue mayúsculas ni tildes', () => {
    assert.equal(limitRepeatedLetters('PUUUTA'), 'PUUTA');
    assert.equal(limitRepeatedLetters('aAa'), 'aA');
    assert.equal(limitRepeatedLetters('uúu'), 'uú');
    assert.equal(limitRepeatedLetters('sí, sííí'), 'sí, síí');
});

test('espacios, signos y dígitos cortan la racha; los dígitos no cuentan como letras', () => {
    assert.equal(limitRepeatedLetters('a a a'), 'a a a');
    assert.equal(limitRepeatedLetters('a.a.a'), 'a.a.a');
    assert.equal(limitRepeatedLetters('1000 y 111'), '1000 y 111');
    assert.equal(limitRepeatedLetters('😀😀😀'), '😀😀😀');
});

test('las marcas combinables y los caracteres invisibles no esquivan el tope', () => {
    assert.equal(limitRepeatedLetters('úúú'), 'úú');
    assert.equal(limitRepeatedLetters('pu​uuta'), 'pu​uta');
});

test('funciona con letras fuera del plano básico', () => {
    assert.equal(limitRepeatedLetters('𝒜𝒜𝒜'), '𝒜𝒜');
});

test('el tope se puede cambiar y un valor inválido vuelve al de 2', () => {
    assert.equal(limitRepeatedLetters('aaaa', { max: 1 }), 'a');
    assert.equal(limitRepeatedLetters('aaaa', { max: 3 }), 'aaa');
    for (const max of [0, -1, NaN, 'x', 1.5]) assert.equal(limitRepeatedLetters('aaaa', { max }), 'aa');
});

test('keep protege lo que debe poder llevar 3 iguales', () => {
    const keep = [/\b[IVXLCDM]{3,}\b/, /\bwww\b/i];
    assert.equal(limitRepeatedLetters('Carlos III y www.sitio.com', { keep }), 'Carlos III y www.sitio.com');
    assert.equal(limitRepeatedLetters('Carlos III y www.sitio.com'), 'Carlos II y ww.sitio.com');
    assert.equal(limitRepeatedLetters('wwww', { keep }), 'ww');
});

test('hasTooManyRepeatedLetters', () => {
    assert.equal(hasTooManyRepeatedLetters('puuuta'), true);
    assert.equal(hasTooManyRepeatedLetters('puuta'), false);
    assert.equal(hasTooManyRepeatedLetters(''), false);
});

test('limitWithMap dice cuánto queda antes de cada posición', () => {
    const { text, map } = limitWithMap('puuuta');
    assert.equal(text, 'puuta');
    assert.deepEqual(map, [0, 1, 2, 3, 3, 4, 5]);
});

test('attach recorta al escribir y deja el cursor donde estaba', () => {
    const input = new FakeInput();
    const removed = [];
    attach(input, { onLimit: (detail) => removed.push(detail.removed) });

    input.type('puuu');
    assert.deepEqual([input.value, input.selectionStart], ['puu', 3]);
    input.type('puuuta', 4); // el cursor estaba tras la 3.ª «u»
    assert.deepEqual([input.value, input.selectionStart, input.selectionEnd], ['puuta', 3, 3]);
    input.type('hola');
    assert.deepEqual(removed, [1, 1], 'sólo avisa cuando recorta');
});

test('attach recorta un texto pegado y avisa con un evento', () => {
    const input = new FakeInput();
    attach(input);

    input.type('aaaaaa bbbbbb');
    assert.equal(input.value, 'aa bb');
    assert.equal(input.dispatched.length, 1);
    assert.deepEqual([input.dispatched[0].type, input.dispatched[0].detail.removed, input.dispatched[0].detail.max], ['repeated-letters-limited', 8, 2]);
});

test('attach limpia el valor que el campo ya traía', () => {
    const input = new FakeInput('puuuuta');
    attach(input);
    assert.equal(input.value, 'puuta');
});

test('attach respeta la composición de un IME y limpia al terminarla', () => {
    const input = new FakeInput();
    attach(input);

    input.fire('compositionstart');
    input.value = 'aaaa';
    input.fire('input', { isComposing: true });
    assert.equal(input.value, 'aaaa');
    input.fire('compositionend');
    assert.equal(input.value, 'aa');
});

test('attach devuelve la función que lo desengancha', () => {
    const input = new FakeInput();
    const detach = attach(input);
    detach();
    input.type('aaaa');
    assert.equal(input.value, 'aaaa');
});

test('attach tolera campos sin cursor ni setSelectionRange', () => {
    const input = new FakeInput();
    input.selectionStart = input.selectionEnd = null;
    input.setSelectionRange = () => { throw new Error('InvalidStateError'); };
    attach(input);
    input.type('aaaa', null);
    assert.equal(input.value, 'aa');
});

test('attachAll engancha los campos marcados y lee el tope de data-max-repeat', () => {
    const [two, one] = [new FakeInput(), new FakeInput()];
    two.dataset.maxRepeat = '';
    one.dataset.maxRepeat = '1';
    const container = { querySelectorAll: (selector) => (selector === '[data-max-repeat]' ? [two, one] : []) };

    const detachers = attachAll(container);
    two.type('aaaa');
    one.type('aaaa');

    assert.equal(detachers.length, 2);
    assert.deepEqual([two.value, one.value], ['aa', 'a']);
});
