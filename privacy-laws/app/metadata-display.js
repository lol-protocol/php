import { t } from "./translations/i18n.js";

export class MetadataDisplay {
  constructor(country, containerId = "metadataContainer") {
    this.country = country;
    this.containerId = containerId;
  }

  async render() {
    const container = document.getElementById(this.containerId);
    if (!container) return;

    try {
      const info = await this.fetchCountryInfo();
      container.innerHTML = this.generateMetadataHTML(info);
      this.addMetadataStyles();
    } catch (error) {
      console.error("Error loading metadata:", error);
      container.innerHTML = "<p>Error loading metadata</p>";
    }
  }

  async fetchCountryInfo() {
    const response = await fetch(
      `/privacy-laws/api.php?action=country_info&tld=${this.country.tld}`
    );
    if (!response.ok) throw new Error("Failed to fetch country info");
    const { data } = await response.json();
    return data;
  }

  generateMetadataHTML(info) {
    const regionLabel = this.getRegionLabel(info.region);
    const updatedDate = new Date(info.createdAt).toLocaleDateString();

    return `
      <div class="metadata-card">
        <div class="metadata-header">
          <h3>${info.name}</h3>
          <span class="metadata-code">${info.code}</span>
        </div>
        <div class="metadata-grid">
          <div class="metadata-item">
            <label>${t("metadata.region")}</label>
            <div class="metadata-value">${regionLabel}</div>
          </div>
          <div class="metadata-item">
            <label>${t("metadata.lawCount")}</label>
            <div class="metadata-value">${info.lawCount}</div>
          </div>
          <div class="metadata-item">
            <label>${t("metadata.updated")}</label>
            <div class="metadata-value">${updatedDate}</div>
          </div>
          <div class="metadata-item metadata-tld">
            <label>TLD</label>
            <div class="metadata-value">${info.tld}</div>
          </div>
        </div>
      </div>
    `;
  }

  getRegionLabel(region) {
    const labels = {
      europe: "🌍 Europe",
      americas: "🌎 Americas",
      asia_pacific: "🌏 Asia-Pacific",
      middle_east_africa: "🌍 Middle East & Africa",
      other: "Other",
    };
    return labels[region] || region;
  }

  addMetadataStyles() {
    if (document.getElementById("metadata-styles")) return;

    const style = document.createElement("style");
    style.id = "metadata-styles";
    style.textContent = `
      .metadata-card {
        background: var(--color-bg-secondary);
        border: 1px solid var(--color-border);
        border-radius: 8px;
        padding: 16px;
        margin: 16px 0;
      }

      .metadata-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        padding-bottom: 12px;
        border-bottom: 2px solid var(--color-border);
      }

      .metadata-header h3 {
        margin: 0;
        color: var(--color-text);
        font-size: 18px;
      }

      .metadata-code {
        background: var(--color-primary);
        color: white;
        padding: 4px 8px;
        border-radius: 4px;
        font-weight: 600;
        font-size: 12px;
      }

      .metadata-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 16px;
      }

      .metadata-item {
        display: flex;
        flex-direction: column;
      }

      .metadata-item label {
        font-size: 12px;
        font-weight: 600;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 6px;
      }

      .metadata-value {
        font-size: 16px;
        font-weight: 500;
        color: var(--color-text);
      }

      .metadata-tld {
        grid-column: auto;
      }

      @media (max-width: 768px) {
        .metadata-grid {
          grid-template-columns: repeat(2, 1fr);
        }

        .metadata-header {
          flex-direction: column;
          align-items: flex-start;
          gap: 8px;
        }

        .metadata-code {
          align-self: flex-start;
        }
      }

      @media (prefers-color-scheme: dark) {
        .metadata-card {
          background: var(--color-bg-secondary);
        }
      }
    `;
    document.head.appendChild(style);
  }
}

export default MetadataDisplay;
