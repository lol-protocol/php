/**
 * Tabs - the WAI-ARIA tabs pattern for a row of buttons and the panels they show.
 * Arrow keys, Home and End move between tabs (in a right-to-left page the arrows point the
 * other way), and only the selected tab is in the Tab order. Works in the browser (as a
 * classic script) and in Node (via require), so it can be tested without a page.
 *
 *   const tabs = new Tabs(buttons, panels, {isRtl: () => document.dir === 'rtl'});
 *   tabs.attach(document);
 *   tabs.select('about');
 *
 * Each button has dataset.tab; the panel with that id is the one it shows.
 */
class Tabs {
    constructor(buttons, panels, {isRtl = () => false, onSelect = () => {}} = {}) {
        this.buttons = [...buttons];
        this.panels = [...panels];
        this.isRtl = isRtl;
        this.onSelect = onSelect;
    }

    // Index of the tab a key moves to, or null if the key isn't one of the tab keys.
    static targetIndex(current, count, key, rtl = false) {
        const steps = {ArrowRight: rtl ? -1 : 1, ArrowLeft: rtl ? 1 : -1};
        if (key in steps) return (current + steps[key] + count) % count;
        if (key === 'Home') return 0;
        if (key === 'End') return count - 1;
        return null;
    }

    names() {
        return this.buttons.map(button => button.dataset.tab);
    }

    select(name, {focus = false} = {}) {
        if (!this.names().includes(name)) return false;
        this.panels.forEach(panel => panel.classList.toggle('active', panel.id === name));
        this.buttons.forEach(button => {
            const selected = button.dataset.tab === name;
            button.classList.toggle('active', selected);
            button.setAttribute('aria-selected', String(selected));
            button.tabIndex = selected ? 0 : -1;
            if (selected && focus) button.focus();
        });
        this.onSelect(name);
        return true;
    }

    // Returns true if the key moved the selection (and the event was consumed).
    handleKeydown(event) {
        const current = this.buttons.indexOf(event.target?.closest?.('.tab-btn'));
        if (current === -1) return false;
        const target = Tabs.targetIndex(current, this.buttons.length, event.key, this.isRtl());
        if (target === null) return false;
        event.preventDefault();
        this.select(this.buttons[target].dataset.tab, {focus: true});
        return true;
    }

    attach(root) {
        root.addEventListener('click', (event) => {
            const button = event.target.closest?.('.tab-btn');
            if (button && this.buttons.includes(button)) this.select(button.dataset.tab);
        });
        root.addEventListener('keydown', (event) => this.handleKeydown(event));
    }
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {Tabs};
}
