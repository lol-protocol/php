import { loadTranslations, t, getAvailableLanguages, getCurrentLanguage } from "./i18n.js";

export class LanguageSelector {
  constructor(containerSelector = "#languageSelector") {
    this.container = document.querySelector(containerSelector);
    this.currentLang = getCurrentLanguage();
    this.languages = getAvailableLanguages();
  }

  render() {
    if (!this.container) {
      console.warn("Language selector container not found");
      return;
    }

    const selector = document.createElement("div");
    selector.className = "language-selector";

    const label = document.createElement("label");
    label.textContent = "Language: ";
    label.className = "language-label";

    const select = document.createElement("select");
    select.id = "languageSelect";
    select.className = "language-select";
    select.value = this.currentLang;

    const languageMap = {
      eng: "English",
      spa: "Español",
      fra: "Français",
      deu: "Deutsch",
      por: "Português",
    };

    this.languages.forEach((lang) => {
      const option = document.createElement("option");
      option.value = lang;
      option.textContent = languageMap[lang] || lang;
      select.appendChild(option);
    });

    select.addEventListener("change", (e) => this.handleLanguageChange(e));

    selector.appendChild(label);
    selector.appendChild(select);
    this.container.appendChild(selector);

    this.addStyles();
    this.listenForLanguageChanges();
  }

  async handleLanguageChange(event) {
    const newLang = event.target.value;
    await loadTranslations(newLang);
    this.currentLang = newLang;
    localStorage.setItem("preferredLanguage", newLang);
    window.location.reload();
  }

  listenForLanguageChanges() {
    document.addEventListener("languageChanged", (e) => {
      this.currentLang = e.detail.lang;
      const select = document.getElementById("languageSelect");
      if (select) select.value = e.detail.lang;
    });
  }

  addStyles() {
    if (document.getElementById("language-selector-styles")) return;

    const style = document.createElement("style");
    style.id = "language-selector-styles";
    style.textContent = `
      .language-selector {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 12px;
        background: var(--color-bg-secondary);
        border-radius: 4px;
        border: 1px solid var(--color-border);
      }

      .language-label {
        font-size: 14px;
        font-weight: 500;
        color: var(--color-text);
        margin: 0;
      }

      .language-select {
        padding: 6px 8px;
        border: 1px solid var(--color-border);
        border-radius: 3px;
        background: var(--color-bg-primary);
        color: var(--color-text);
        font-size: 14px;
        cursor: pointer;
      }

      .language-select:hover {
        border-color: var(--color-primary);
      }

      .language-select:focus {
        outline: 2px solid var(--color-primary);
        outline-offset: 2px;
      }

      @media (prefers-color-scheme: dark) {
        .language-selector {
          background: var(--color-bg-secondary);
        }

        .language-select {
          background: var(--color-bg-primary);
        }
      }
    `;
    document.head.appendChild(style);
  }
}

// Auto-initialize if data attribute is present
document.addEventListener("DOMContentLoaded", () => {
  const selector = document.querySelector("[data-language-selector]");
  if (selector) {
    const languageSelector = new LanguageSelector("[data-language-selector]");
    languageSelector.render();
  }
});

export default LanguageSelector;
