const test = require('node:test');
const assert = require('node:assert/strict');

const { Tabs } = require('../app/tabs.js');
const { URL_STATE_KEYS, encodeState, decodeState } = require('../app/url-state.js');

// Just enough of an element for Tabs: classes, attributes, tabIndex and focus.
const element = (props = {}) => {
    const classes = new Set(props.active ? ['active'] : []);
    return {
        ...props,
        classList: { toggle: (name, on) => (on ? classes.add(name) : classes.delete(name)), contains: (name) => classes.has(name) },
        attributes: {},
        setAttribute(name, value) { this.attributes[name] = value; },
        tabIndex: 0,
        focused: false,
        focus() { this.focused = true; },
        closest() { return this; },
    };
};
const build = (options) => {
    const names = ['browser', 'comparison', 'converter', 'about'];
    const buttons = names.map(name => element({ dataset: { tab: name } }));
    const panels = names.map(name => element({ id: name }));
    const selected = [];
    const tabs = new Tabs(buttons, panels, { onSelect: (name) => selected.push(name), ...options });
    return { tabs, buttons, panels, selected };
};
const key = (target, name) => ({ target, key: name, prevented: false, preventDefault() { this.prevented = true; } });

test('targetIndex: arrows wrap around, Home and End jump, other keys are ignored', () => {
    assert.equal(Tabs.targetIndex(0, 4, 'ArrowRight'), 1);
    assert.equal(Tabs.targetIndex(3, 4, 'ArrowRight'), 0);
    assert.equal(Tabs.targetIndex(0, 4, 'ArrowLeft'), 3);
    assert.equal(Tabs.targetIndex(2, 4, 'Home'), 0);
    assert.equal(Tabs.targetIndex(1, 4, 'End'), 3);
    assert.equal(Tabs.targetIndex(1, 4, 'Enter'), null);
});

test('targetIndex: in a right-to-left page the arrows point the other way', () => {
    assert.equal(Tabs.targetIndex(1, 4, 'ArrowLeft', true), 2);
    assert.equal(Tabs.targetIndex(1, 4, 'ArrowRight', true), 0);
    assert.equal(Tabs.targetIndex(0, 4, 'Home', true), 0, 'Home and End do not depend on the direction');
});

test('select shows one panel, marks one tab and leaves only that tab in the Tab order', () => {
    const { tabs, buttons, panels, selected } = build();
    assert.equal(tabs.select('converter'), true);
    assert.deepEqual(panels.map(p => p.classList.contains('active')), [false, false, true, false]);
    assert.deepEqual(buttons.map(b => b.attributes['aria-selected']), ['false', 'false', 'true', 'false']);
    assert.deepEqual(buttons.map(b => b.tabIndex), [-1, -1, 0, -1]);
    assert.deepEqual(selected, ['converter']);
    assert.equal(buttons[2].focused, false, 'a click does not move the focus');

    tabs.select('about', { focus: true });
    assert.equal(buttons[3].focused, true);
});

test('select ignores a tab that does not exist', () => {
    const { tabs, panels, selected } = build();
    tabs.select('browser');
    assert.equal(tabs.select('nope'), false);
    assert.deepEqual(panels.map(p => p.classList.contains('active')), [true, false, false, false]);
    assert.deepEqual(selected, ['browser']);
});

test('handleKeydown moves the selection and the focus, and consumes only the tab keys', () => {
    const { tabs, buttons } = build();
    tabs.select('browser');
    const arrow = key(buttons[0], 'ArrowRight');
    assert.equal(tabs.handleKeydown(arrow), true);
    assert.equal(arrow.prevented, true);
    assert.equal(buttons[1].attributes['aria-selected'], 'true');
    assert.equal(buttons[1].focused, true);

    const end = key(buttons[1], 'End');
    tabs.handleKeydown(end);
    assert.equal(buttons[3].attributes['aria-selected'], 'true');

    const letter = key(buttons[3], 'a');
    assert.equal(tabs.handleKeydown(letter), false);
    assert.equal(letter.prevented, false, 'other keys keep their normal behaviour');

    assert.equal(tabs.handleKeydown(key({ closest: () => null }, 'ArrowRight')), false, 'a key pressed outside the tabs');
});

test('handleKeydown follows the direction of the page', () => {
    let rtl = false;
    const { tabs, buttons } = build({ isRtl: () => rtl });
    tabs.select('comparison');
    tabs.handleKeydown(key(buttons[1], 'ArrowLeft'));
    assert.equal(buttons[0].attributes['aria-selected'], 'true');
    rtl = true;
    tabs.handleKeydown(key(buttons[0], 'ArrowLeft'));
    assert.equal(buttons[1].attributes['aria-selected'], 'true');
});

test('the URL state round-trips, leaves out empty values and drops unknown keys', () => {
    const state = { tab: 'converter', from: 'A4', to: 'Letter', q: 'A4 & "Letter" = #1', country: '', lang: 'es' };
    const hash = encodeState(state);
    assert.equal(hash.includes('country'), false);
    assert.deepEqual(decodeState(`#${hash}`), { tab: 'converter', from: 'A4', to: 'Letter', q: 'A4 & "Letter" = #1', lang: 'es' });
    assert.equal(encodeState({}), '');
    assert.deepEqual(decodeState('#tab=about&evil=1&q='), { tab: 'about' });
    assert.deepEqual(decodeState(''), {});
    assert.deepEqual(decodeState(undefined), {});
});

test('every key of the URL state survives a round trip', () => {
    const state = Object.fromEntries(URL_STATE_KEYS.map(k => [k, `value of ${k}`]));
    assert.deepEqual(decodeState(encodeState(state)), state);
});
