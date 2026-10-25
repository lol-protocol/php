/**
 * The state of the demo (tab, language, search boxes, filters) as the fragment of its URL,
 * so a result can be shared: app/index.html#tab=converter&from=A4&to=Letter.
 * Only the keys listed here are read; empty values are left out.
 */
const URL_STATE_KEYS = ['lang', 'tab', 'q', 'country', 'category', 'region', 'compare', 'from', 'to', 'pq', 'pcat', 'size', 'sort', 'desc'];

// {tab: 'about', q: ''} -> "tab=about"
const encodeState = (state) => {
    const params = new URLSearchParams();
    for (const key of URL_STATE_KEYS) {
        if (state[key]) params.set(key, state[key]);
    }
    return params.toString();
};

// "#tab=about&q=A4" -> {tab: 'about', q: 'A4'}; anything that isn't a known key is dropped.
const decodeState = (hash) => {
    const state = {};
    for (const [key, value] of new URLSearchParams(String(hash ?? '').replace(/^#/, ''))) {
        if (URL_STATE_KEYS.includes(key) && value) state[key] = value;
    }
    return state;
};

if (typeof module !== 'undefined' && module.exports) {
    module.exports = {URL_STATE_KEYS, encodeState, decodeState};
}
