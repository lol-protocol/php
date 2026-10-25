// The demo page (index.html): its lists, tabs, address state and language switch.
// Loaded after document-formats.js, tabs.js and url-state.js, whose globals it uses.

const i18n = new LocalizationManager();
const tr = (key, values) => formatMessage(i18n.t(key), values);
// Text that tr() had to take from English because the language lacks it: it must be
// marked, or a screen reader would read it with the voice of the page's language.
const fallbackLang = (key) =>
    i18n.getLanguage() !== 'en' && LocalizationManager.lookup(i18n.translations, key) === null ? ' lang="en"' : '';
// The CSV files hold their category, country, color and use texts in English.
const dataLang = () => (i18n.getLanguage() === 'en' ? '' : ' lang="en"');
const announce = (text, key) => {
    const status = document.getElementById('status');
    status.textContent = text;
    if (key && fallbackLang(key)) status.lang = 'en';
    else status.removeAttribute('lang');
};
// Saves a file made in the page, the way a link with a download attribute would.
const downloadText = (filename, text, type) => {
    const link = document.createElement('a');
    link.href = URL.createObjectURL(new Blob([text], {type}));
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 0);
};

// One <option> per entry: [value, label] pairs, optionally with a placeholder already in the select.
const addOptions = (select, entries) => {
    for (const [value, label] of entries) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = label;
        select.appendChild(option);
    }
};
const sortedUnique = (values) => [...new Set(values.filter(Boolean))].sort((a, b) => a.localeCompare(b));
// Same-named formats in different countries share a size, so one entry per name.
const formatSizeOptions = (formats) => {
    const byName = indexByName(formats);
    return sortedUnique([...byName.keys()])
        .map(name => [name, `${name} (${byName.get(name).width_mm}×${byName.get(name).height_mm} mm)`]);
};

// A list of cards that a search box and some selects narrow down. The page draws it,
// announces how many are left, and redraws it when the language changes.
class FilterableList {
    constructor(items, {container, emptyKey, countKey}) {
        this.items = items;
        this.shown = items;
        this.container = container;
        this.emptyKey = emptyKey;
        this.countKey = countKey;
    }

    // Subclasses return the HTML of one card.
    card(item) { throw new Error('card() is not implemented'); }

    render(items) {
        this.shown = items;
        this.container.innerHTML = items.length === 0
            ? `<div class="no-results">
                    <div class="no-results-icon" aria-hidden="true">🔍</div>
                    <p${fallbackLang(this.emptyKey)}>${escapeHtml(tr(this.emptyKey))}</p>
                </div>`
            : items.map(item => this.card(item)).join('');
    }

    // Keeps what the predicate accepts, draws it and tells the live region the count
    // (not when the filters are restored from the URL: nobody asked for them just now).
    filter(accepts, {announceCount = true} = {}) {
        let filtered = this.items.filter(accepts);
        if (this.compare) filtered = [...filtered].sort(this.compare);
        if (this.reverse) filtered = [...filtered].reverse();
        this.render(filtered);
        if (announceCount) announce(tr(this.countKey, {count: filtered.length}), this.countKey);
    }

    refresh() { this.render(this.shown); }
}

// The query is looked up inside any of these texts, ignoring case and Latin accents.
const matchesQuery = (query, texts) => {
    const wanted = normalizeText(query);
    return !wanted || texts.some(text => normalizeText(text).includes(wanted));
};

// "Large Format" -> "largeFormat", "Asia-Pacific" -> "asiaPacific": where a region or a category
// of the data sits in the translation files (regions.*, categories.*).
const vocabularyKey = (value) => String(value).split(/[\s/-]+/).filter(Boolean)
    .map((word, i) => (i ? word[0].toUpperCase() + word.slice(1).toLowerCase() : word.toLowerCase())).join('');
// A region or category in the language of the page; one the files don't have stays as the CSV writes it.
const vocabularyLabel = (group, value) => i18n.t(`${group}.${vocabularyKey(value)}`, value);
// Words the page translates carry no lang of their own; the others are the CSV's English.
const vocabularyLang = (group, value) =>
    LocalizationManager.lookup(i18n.translations, `${group}.${vocabularyKey(value)}`) === null ? dataLang() : '';

// Fills a select with the given data values, labelled in the language of the page and sorted in
// its alphabetical order. The first option (the "all" one) stays; so does the current choice.
const fillVocabularySelect = (select, group, values) => {
    const selected = select.value;
    while (select.options.length > 1) select.remove(1);
    addOptions(select, values.map(value => [value, vocabularyLabel(group, value)])
        .sort((a, b) => a[1].localeCompare(b[1], i18n.getLanguage())));
    select.value = selected;
};

// The comparison table shows this many rows at most, and says so when there are more.
const COMPARE_LIMIT = 100;

// How the Browser tab can order its cards. Ties keep the catalog order (sort is stable).
const FORMAT_SORTS = {
    name: (a, b) => a.format_name.localeCompare(b.format_name, i18n.getLanguage()),
    width: (a, b) => a.width_mm - b.width_mm,
    height: (a, b) => a.height_mm - b.height_mm,
    area: (a, b) => a.width_mm * a.height_mm - b.width_mm * b.height_mm
};

class FormatBrowser extends FilterableList {
    constructor(formats) {
        super(formats, {
            container: document.getElementById('formats-container'),
            emptyKey: 'messages.noResults',
            countKey: 'demo.resultsFormats'
        });
        this.formats = formats;
        this.statistics = new FormatStatistics(formats);
        this.converter = new FormatConverter(formats);
        this.cacheElements();
        this.setupEventListeners();
        this.populateFilters();
        this.updateStats();
        this.render(this.formats);
    }

    cacheElements() {
        this.els = {
            search: document.getElementById('search-input'),
            countryFilter: document.getElementById('country-filter'),
            categoryFilter: document.getElementById('category-filter'),
            regionFilter: document.getElementById('region-filter'),
            sortSelect: document.getElementById('sort-select'),
            sortReverse: document.getElementById('sort-reverse'),
            compareNote: document.getElementById('compare-note'),
            equivalents: document.getElementById('equivalents'),
            equivalentsTitle: document.getElementById('equivalents-title'),
            equivalentsList: document.getElementById('equivalents-list'),
            resetBtn: document.getElementById('reset-btn'),
            downloadBtn: document.getElementById('download-btn'),
            fromFormat: document.getElementById('from-format'),
            toFormat: document.getElementById('to-format'),
            compareSearch: document.getElementById('compare-search'),
            compareBody: document.getElementById('comparison-body'),
            totalFormats: document.getElementById('total-formats'),
            totalCountries: document.getElementById('total-countries'),
            totalCategories: document.getElementById('total-categories')
        };
    }

    setupEventListeners() {
        const {search, countryFilter, categoryFilter, regionFilter, sortSelect, sortReverse, resetBtn, downloadBtn, fromFormat, toFormat, compareSearch, equivalentsList} = this.els;

        document.addEventListener('change', (e) => {
            if (e.target === countryFilter || e.target === categoryFilter || e.target === regionFilter) {
                this.filterFormats();
            } else if (e.target === sortSelect) {
                this.filterFormats({announceCount: false});
            } else if (e.target === fromFormat || e.target === toFormat) {
                this.updateConverter();
            }
        });

        document.addEventListener('input', (e) => {
            if (e.target === search) this.filterFormats();
            else if (e.target === compareSearch) this.updateComparison({announceCount: true});
        });

        resetBtn.addEventListener('click', () => {
            search.value = '';
            countryFilter.value = '';
            categoryFilter.value = '';
            regionFilter.value = '';
            sortSelect.value = '';
            sortReverse.setAttribute('aria-pressed', 'false');
            this.filterFormats();
        });

        sortReverse.addEventListener('click', () => {
            sortReverse.setAttribute('aria-pressed', String(sortReverse.getAttribute('aria-pressed') !== 'true'));
            this.filterFormats({announceCount: false});
        });

        // A closest format becomes the target: the change event redraws the converter and the address.
        equivalentsList.addEventListener('click', (e) => {
            const button = e.target.closest('.equivalent-btn');
            if (!button) return;
            toFormat.value = button.dataset.format;
            toFormat.dispatchEvent(new Event('change', {bubbles: true}));
            toFormat.focus();
        });

        downloadBtn.addEventListener('click', () => {
            // UTF-8 byte order mark first, so a spreadsheet opens accents and kanji correctly.
            downloadText(`${tr('demo.csvFilename')}.csv`,
                '\uFEFF' + new FormatBatchProcessor(this.shown).exportAsCSV(), 'text/csv;charset=utf-8');
        });
    }

    populateFilters() {
        const {countryFilter, categoryFilter, regionFilter, fromFormat, toFormat} = this.els;
        const distinct = (field) => sortedUnique(this.formats.map(f => f[field])).map(value => [value, value]);

        addOptions(countryFilter, distinct('country'));
        this.vocabularyValues = {
            categories: sortedUnique(this.formats.map(f => f.category)),
            regions: sortedUnique(this.formats.map(f => f.region))
        };
        this.fillVocabularyFilters();

        const formatOptions = formatSizeOptions(this.formats);
        addOptions(fromFormat, formatOptions);
        addOptions(toFormat, formatOptions);
    }

    // The category and region lists, in the language of the page and in its alphabetical order.
    // The option value stays the CSV's word, because that is what the formats are compared with.
    fillVocabularyFilters() {
        const {categoryFilter, regionFilter} = this.els;
        fillVocabularySelect(categoryFilter, 'categories', this.vocabularyValues.categories);
        fillVocabularySelect(regionFilter, 'regions', this.vocabularyValues.regions);
    }

    filterFormats(options) {
        const {search, countryFilter, categoryFilter, regionFilter, sortSelect, sortReverse} = this.els;
        const query = search.value;
        const country = countryFilter.value;
        const category = categoryFilter.value;
        const region = regionFilter.value;
        this.compare = FORMAT_SORTS[sortSelect.value] ?? null;
        this.reverse = sortReverse.getAttribute('aria-pressed') === 'true';

        this.filter(format =>
            matchesQuery(query, [format.format_name, format.description, format.common_use,
                format.country, format.category, format.region]) &&
            (!country || format.country === country) &&
            (!category || format.category === category) &&
            (!region || format.region === region)
        , options);
    }

    // The download button saves what is on screen, so it has nothing to save when nothing is.
    render(formats) {
        super.render(formats);
        this.els.downloadBtn.disabled = formats.length === 0;
    }

    card(format) {
        return `
            <div class="format-card">
                <div class="format-card-header">
                    <h3>${escapeHtml(format.format_name)}</h3>
                    <span class="badge badge-${escapeHtml(String(format.category).toLowerCase())}"${vocabularyLang('categories', format.category)}>${escapeHtml(vocabularyLabel('categories', format.category))}</span>
                </div>
                <div class="format-info">
                    <div class="info-row">
                        <span class="info-label"${fallbackLang('demo.cardCountry')}>${escapeHtml(tr('demo.cardCountry'))}</span>
                        <span class="info-value"${dataLang()}>${escapeHtml(format.country)}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"${fallbackLang('demo.cardSizeMm')}>${escapeHtml(tr('demo.cardSizeMm'))}</span>
                        <span class="info-value">${escapeHtml(format.width_mm)} × ${escapeHtml(format.height_mm)}</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"${fallbackLang('demo.cardSizeIn')}>${escapeHtml(tr('demo.cardSizeIn'))}</span>
                        <span class="info-value">${escapeHtml(format.width_inches)}" × ${escapeHtml(format.height_inches)}"</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"${fallbackLang('demo.cardAspect')}>${escapeHtml(tr('demo.cardAspect'))}</span>
                        <span class="info-value">${escapeHtml(format.aspect_ratio)}</span>
                    </div>
                </div>
                <div class="format-preview">
                    ${this.generatePreviewSVG(format)}
                </div>
            </div>
        `;
    }

    generatePreviewSVG(format) {
        // Sizes run from 24 mm slides to 1.4 m posters: draw every sheet with its longest side at 100.
        const scale = 100 / Math.max(format.width_mm, format.height_mm);
        const w = format.width_mm * scale;
        const h = format.height_mm * scale;
        const label = escapeHtml(tr('demo.proportions', {name: format.format_name}));

        return `
            <svg viewBox="0 0 ${w} ${h}" role="img" aria-label="${label}">
                <rect x="1" y="1" width="${w - 2}" height="${h - 2}"
                      fill="white" stroke="#999" stroke-width="1"/>
            </svg>
        `;
    }

    updateStats() {
        const {totalFormats, totalCountries, totalCategories} = this.els;
        const stats = this.statistics.getStats();
        totalFormats.textContent = stats.totalFormats;
        totalCountries.textContent = stats.totalCountries;
        totalCategories.textContent = stats.totalCategories;
    }

    updateComparison({announceCount = false} = {}) {
        const {compareNote} = this.els;
        const terms = this.els.compareSearch.value.split(',').map(normalizeText).map(t => t.trim()).filter(Boolean);
        const all = terms.length === 0 ? [] : this.formats.filter(format =>
            terms.some(term => normalizeText(format.format_name).includes(term)));
        const matches = all.slice(0, COMPARE_LIMIT);

        // Say so when the table doesn't show every match, on screen and to a screen reader.
        const cut = all.length > matches.length;
        compareNote.hidden = !cut;
        compareNote.textContent = cut ? tr('demo.compareShown', {shown: matches.length, count: all.length}) : '';
        if (cut && fallbackLang('demo.compareShown')) compareNote.lang = 'en';
        else compareNote.removeAttribute('lang');
        if (announceCount && terms.length) {
            if (cut) announce(compareNote.textContent, 'demo.compareShown');
            else announce(tr('demo.resultsFormats', {count: all.length}), 'demo.resultsFormats');
        }

        this.els.compareBody.innerHTML = matches.map(format => `
            <tr>
                <td>${escapeHtml(format.format_name)}</td>
                <td>${escapeHtml(format.country)}</td>
                <td>${escapeHtml(format.width_mm)}</td>
                <td>${escapeHtml(format.height_mm)}</td>
                <td>${escapeHtml(format.aspect_ratio)}</td>
            </tr>
        `).join('');
    }

    // The five formats closest in size to the one chosen as origin, each a button that makes it the target.
    // Same-named formats of several countries share a size, so each name appears once.
    updateEquivalents(fromName) {
        const {equivalents, equivalentsTitle, equivalentsList} = this.els;
        if (!fromName) {
            equivalents.hidden = true;
            return;
        }
        const seen = new Set();
        const closest = this.converter.findEquivalents(fromName, 0)
            .filter(e => !seen.has(e.name) && seen.add(e.name))
            .slice(0, 5);
        equivalentsTitle.textContent = tr('demo.closestTitle', {format: fromName});
        equivalentsList.innerHTML = closest.map(e => `
            <li>
                <button type="button" class="equivalent-btn" data-format="${escapeHtml(e.name)}"
                        aria-label="${escapeHtml(tr('demo.useAsTarget', {format: e.name, percent: e.similarity}))}">
                    <strong>${escapeHtml(e.name)}</strong>
                    <span class="equivalent-size">${escapeHtml(e.dimensions.mm)} mm · <span${dataLang()}>${escapeHtml(e.country)}</span></span>
                    — ${escapeHtml(e.similarity)}%
                </button>
            </li>`).join('');
        equivalents.hidden = closest.length === 0;
    }

    updateConverter() {
        const fromName = this.els.fromFormat.value;
        const toName = this.els.toFormat.value;
        const result = document.getElementById('conversion-result');
        this.updateEquivalents(fromName);

        const conversion = fromName && toName ? this.converter.convert(fromName, toName) : null;
        if (!conversion) {
            result.classList.remove('show');
            return;
        }

        document.getElementById('conversion-info').innerHTML = `
            <p><strong>${escapeHtml(fromName)}</strong> (${escapeHtml(conversion.fromSize.mm)} mm)
                → <strong>${escapeHtml(toName)}</strong> (${escapeHtml(conversion.toSize.mm)} mm)</p>
            <p class="similarity-note">
                ${escapeHtml(tr('demo.similarity', {percent: conversion.similarity}))}
            </p>
        `;

        const fill = document.getElementById('equivalence-fill');
        fill.style.width = conversion.similarity + '%';
        fill.textContent = conversion.similarity + '%';
        result.classList.add('show');
    }

    // After a language change: the option labels, and the cards that JavaScript wrote
    // with words in them (a name sort also depends on the language).
    refresh() {
        this.fillVocabularyFilters();
        this.filterFormats({announceCount: false});
        this.updateComparison();
        this.updateConverter();
    }
}

class PaperTypeBrowser extends FilterableList {
    constructor(paperTypes, translations, formats) {
        super(paperTypes, {
            container: document.getElementById('paper-types-container'),
            emptyKey: 'demo.noPaperTypes',
            countKey: 'demo.resultsPaperTypes'
        });
        this.paperTypes = paperTypes;
        this.translations = new Map(translations.map(row => [row.type_name, row]));
        this.formats = formats;
        this.cacheElements();
        this.setupEventListeners();
        this.populateFilters();
        this.render(this.paperTypes);
    }

    cacheElements() {
        this.els = {
            search: document.getElementById('paper-search-input'),
            categoryFilter: document.getElementById('paper-category-filter'),
            sizeSelect: document.getElementById('paper-size-select'),
            combinedSection: document.getElementById('paper-combined-section'),
            combinedTitle: document.getElementById('paper-combined-title'),
            combinedBody: document.getElementById('paper-combined-body')
        };
    }

    // The paper type's name in the language the page is showing.
    name(paperType) {
        return paperTypeName(paperType, this.translations.get(paperType.type_name), i18n.getLanguage());
    }

    setupEventListeners() {
        const {search, categoryFilter, sizeSelect} = this.els;

        document.addEventListener('input', (e) => {
            if (e.target === search) this.applyFilters();
        });

        document.addEventListener('change', (e) => {
            if (e.target === categoryFilter) this.applyFilters();
            else if (e.target === sizeSelect) this.updateCombined();
        });
    }

    populateFilters() {
        const {categoryFilter, sizeSelect} = this.els;
        this.categories = sortedUnique(this.paperTypes.map(p => p.category));
        fillVocabularySelect(categoryFilter, 'paperCategories', this.categories);
        addOptions(sizeSelect, formatSizeOptions(this.formats));
    }

    applyFilters(options) {
        const {search, categoryFilter} = this.els;
        const query = search.value;
        const category = categoryFilter.value;

        this.filter(p =>
            matchesQuery(query, [p.type_name, p.type_name_en, this.name(p), p.common_use, p.notes]) &&
            (!category || p.category === category)
        , options);
    }

    // Language of the name shown as the title: the paper type's own Spanish or English name,
    // or the translation when the language has one.
    nameLang(paperType) {
        const lang = i18n.getLanguage();
        if (lang === 'es' || lang === 'en') return lang;
        return this.translations.get(paperType.type_name)?.[lang] ? lang : 'en';
    }

    card(p) {
        const badgeSlug = (category) => String(category).toLowerCase().replace(/[^a-z0-9]+/g, '-');
        // labelKey: the translation key of the label, to mark it when it fell back to English;
        // valueLang: the lang attribute of the value (the CSV texts are English).
        const row = (label, value, valueLang = dataLang(), labelKey = null) => `
            <div class="info-row">
                <span class="info-label"${labelKey ? fallbackLang(labelKey) : ''}>${escapeHtml(label)}</span>
                <span class="info-value"${valueLang}>${escapeHtml(value)}</span>
            </div>`;

        const title = this.name(p);
        // The original names stay visible, except the one already used as the title.
        const originals = [['es', p.type_name], ['en', p.type_name_en]]
            .filter(([, value]) => value !== title)
            .map(([lang, value]) => row(i18n.getLanguageName(lang), value, ` lang="${lang}"`)).join('');
        return `
            <div class="format-card">
                <div class="format-card-header">
                    <h3 lang="${this.nameLang(p)}">${escapeHtml(title)}</h3>
                    <span class="badge badge-${badgeSlug(p.category)}"${vocabularyLang('paperCategories', p.category)}>${escapeHtml(vocabularyLabel('paperCategories', p.category))}</span>
                </div>
                <div class="format-info">
                    ${originals}
                    ${row(tr('paperTypes.color'), p.color, dataLang(), 'paperTypes.color')}
                    ${row(tr('paperTypes.texture'), p.texture, dataLang(), 'paperTypes.texture')}
                    ${row(tr('paperTypes.finish'), p.finish, dataLang(), 'paperTypes.finish')}
                    ${row(tr('paperTypes.weight'), p.weight_gsm_range, '', 'paperTypes.weight')}
                    ${row(tr('paperTypes.commonUse'), p.common_use, dataLang(), 'paperTypes.commonUse')}
                </div>
                <p class="paper-notes"${dataLang()}>${escapeHtml(p.notes)}</p>
            </div>`;
    }

    updateCombined() {
        const {sizeSelect, combinedSection, combinedTitle, combinedBody} = this.els;
        const formatName = sizeSelect.value;

        if (!formatName) {
            combinedSection.hidden = true;
            return;
        }

        const format = this.formats.find(f => f.format_name === formatName);
        combinedTitle.textContent = tr('paperTypes.combinedTitle', {
            format: formatName, size: `${format.width_mm}×${format.height_mm}`
        });
        combinedBody.innerHTML = this.paperTypes.map(p => `
            <tr>
                <td>${escapeHtml(this.name(p))}</td>
                <td>${escapeHtml(p.color)}</td>
                <td>${escapeHtml(p.texture)}</td>
                <td>${escapeHtml(p.weight_gsm_range)}</td>
            </tr>
        `).join('');
        combinedSection.hidden = false;
    }

    refresh() {
        fillVocabularySelect(this.els.categoryFilter, 'paperCategories', this.categories);
        super.refresh();
        this.updateCombined();
    }
}

// Each piece of state kept in the URL, and the field it comes from.
const STATE_FIELDS = {
    q: 'search-input', country: 'country-filter', category: 'category-filter', region: 'region-filter',
    compare: 'compare-search', from: 'from-format', to: 'to-format', sort: 'sort-select',
    pq: 'paper-search-input', pcat: 'paper-category-filter', size: 'paper-size-select'
};

document.addEventListener('DOMContentLoaded', async () => {
    let formatBrowser = null;
    let paperBrowser = null;
    let lastModified = null;
    let loadError = null;
    let formats = null;
    let restoring = true;   // until the URL's state is applied, the URL is not rewritten
    const languageSelect = document.getElementById('language-select');
    const container = document.getElementById('formats-container');
    const urlState = decodeState(location.hash);

    // The URL keeps the tab, the language (when it isn't English) and every search box and filter.
    const saveState = () => {
        if (restoring) return;
        const state = {lang: i18n.getLanguage() === 'en' ? '' : i18n.getLanguage(),
            tab: document.querySelector('.tab-btn.active')?.dataset.tab === 'browser' ? '' : document.querySelector('.tab-btn.active')?.dataset.tab};
        for (const [key, id] of Object.entries(STATE_FIELDS)) state[key] = document.getElementById(id).value;
        state.desc = document.getElementById('sort-reverse').getAttribute('aria-pressed') === 'true' ? '1' : '';
        const hash = encodeState(state);
        history.replaceState(null, '', hash ? `#${hash}` : location.pathname + location.search);
    };
    document.addEventListener('input', saveState);
    document.addEventListener('change', saveState);
    // The reset button empties the fields in its own click handler, which runs after this one.
    document.getElementById('reset-btn').addEventListener('click', () => setTimeout(saveState));
    document.getElementById('sort-reverse').addEventListener('click', () => setTimeout(saveState));

    const tabs = new Tabs(document.querySelectorAll('.tab-btn'), document.querySelectorAll('.tab-content'), {
        isRtl: () => document.documentElement.dir === 'rtl',
        onSelect: saveState
    });
    tabs.attach(document);
    if (urlState.tab) tabs.select(urlState.tab);

    const showUpdated = () => {
        const updated = document.getElementById('about-updated');
        updated.textContent = lastModified
            ? lastModified.toLocaleDateString(i18n.getLanguage(), {year: 'numeric', month: 'long', day: 'numeric'})
            : tr('demo.updatedUnknown');
        if (!lastModified && fallbackLang('demo.updatedUnknown')) updated.lang = 'en';
        else updated.removeAttribute('lang');
    };

    // The two lines of the About tab that carry a number: {count} sits where the language wants it.
    const showAboutCounts = () => {
        const counts = {aboutFormats: formats?.length, aboutCountries: formatBrowser?.statistics.getStats().totalCountries};
        for (const [key, value] of Object.entries(counts)) {
            const item = document.getElementById(key === 'aboutFormats' ? 'about-formats' : 'about-countries');
            item.innerHTML = formatMessage(escapeHtml(i18n.t(`demo.${key}`)), {count: `<strong>${value ?? '…'}</strong>`});
            if (fallbackLang(`demo.${key}`)) item.lang = 'en';
            else item.removeAttribute('lang');
        }
    };

    const showLoadError = () => {
        const code = (text) => `<code>${escapeHtml(text)}</code>`;
        container.innerHTML = `
            <div class="no-results">
                <p${fallbackLang('demo.loadError')}>${escapeHtml(tr('demo.loadError', {message: loadError.message}))}</p>
                <p${fallbackLang('demo.loadErrorHelp')}>${formatMessage(escapeHtml(i18n.t('demo.loadErrorHelp')), {
                    command: code('python3 -m http.server'),
                    folder: code('document-formats/'),
                    address: code('http://localhost:8000/app/')
                })}</p>
            </div>
        `;
    };

    // Redraw the page in the language that just loaded: the words written in the HTML
    // (data-translate), the language and direction of the document, and the cards and
    // tables that JavaScript draws.
    const applyLanguage = () => {
        const lang = i18n.getLanguage();
        document.documentElement.lang = lang;
        document.documentElement.dir = LocalizationManager.isRtl(lang) ? 'rtl' : 'ltr';
        document.title = tr('demo.title');
        i18n.translateElement(document.body);
        // A string the language lacks shows in English: say so, or a screen reader
        // would read it with the voice of the page's language.
        document.querySelectorAll('[data-translate]').forEach(el => {
            const key = el.dataset.translate.split(':')[0];
            if (fallbackLang(key)) el.lang = 'en';
            else el.removeAttribute('lang');
        });
        languageSelect.value = lang;
        if (loadError) showLoadError();
        formatBrowser?.refresh();
        paperBrowser?.refresh();
        if (formatBrowser) showUpdated();
        showAboutCounts();
        saveState();
    };

    for (const [code, name] of Object.entries(LocalizationManager.LANGUAGE_NAMES)) {
        const option = document.createElement('option');
        option.value = code;
        option.lang = code;
        option.textContent = name;
        languageSelect.appendChild(option);
    }
    languageSelect.addEventListener('change', () => i18n.setLanguage(languageSelect.value));
    i18n.subscribe(applyLanguage);

    // The language: the one in the URL, else the saved one, else (first visit) the browser's
    // when the demo has it.
    try {
        const browserLanguage = (navigator.language || '').slice(0, 2).toLowerCase();
        if (urlState.lang in LocalizationManager.LANGUAGE_NAMES) {
            i18n.currentLanguage = urlState.lang;
        } else if (!localStorage.getItem('preferredLanguage') && browserLanguage in LocalizationManager.LANGUAGE_NAMES) {
            i18n.currentLanguage = browserLanguage;
        }
    } catch (error) { /* storage blocked: stay in English */ }
    await i18n.init();
    applyLanguage();

    container.innerHTML = `<p class="no-results">${escapeHtml(tr('messages.loading'))}</p>`;

    try {
        ({formats, lastModified} = await loadFormatCatalogs());
    } catch (error) {
        loadError = error;
        showLoadError();
        return;
    }

    formatBrowser = new FormatBrowser(formats);
    showAboutCounts();
    showUpdated();

    const specs = new SpecificationManager();
    await specs.loadAllSpecifications();
    paperBrowser = new PaperTypeBrowser(
        specs.getAllPaperTypes(), specs.specifications.get('paperTypeTranslations') ?? [], formats);

    // Now that every list has its options, put back what the URL says.
    for (const [key, id] of Object.entries(STATE_FIELDS)) {
        if (urlState[key] !== undefined) document.getElementById(id).value = urlState[key];
    }
    if (urlState.desc === '1') document.getElementById('sort-reverse').setAttribute('aria-pressed', 'true');
    const quiet = {announceCount: false};
    formatBrowser.filterFormats(quiet);
    formatBrowser.updateComparison();
    formatBrowser.updateConverter();
    paperBrowser.applyFilters(quiet);
    paperBrowser.updateCombined();
    restoring = false;
    saveState();
});
