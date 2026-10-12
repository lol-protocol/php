import { loadTranslations, t } from "./translations/i18n.js";
import LanguageSelector from "./translations/language-selector.js";
import TimelineVisualization from "./timeline.js";
import MetadataDisplay from "./metadata-display.js";
import { parseCSV } from "./csv.js";

// Data Loaders
class JurisdictionLoader {
  constructor() {
    this.jurisdictions = [];
    this.jurisdictionMap = {};
    this.dataCache = {};
  }

  async load() {
    try {
      // Load the master CSV to get list of jurisdictions
      const response = await fetch("../jurisdictions/copyright_laws_master.csv");
      const csvText = await response.text();
      const rawData = this.parseCSV(csvText);

      // Build jurisdiction index from CSV
      const index = {};
      rawData.forEach((row) => {
        if (!index[row.country_code]) {
          index[row.country_code] = {
            code: row.country_code,
            name: row.country_name,
            region: this.getRegion(row.country_code),
            tld: row.country_code.toLowerCase(),
            laws: [],
          };
        }
        index[row.country_code].laws.push(row);
      });

      this.jurisdictions = Object.values(index);
      this.jurisdictions.forEach((j) => (this.jurisdictionMap[j.code] = j));

      return this.jurisdictions;
    } catch (error) {
      console.error("Error loading copyright laws:", error);
      throw error;
    }
  }

  async loadJurisdictionData(tld) {
    try {
      if (this.dataCache[tld]) return this.dataCache[tld];

      const response = await fetch(`../jurisdictions/${tld}/laws.json`);
      if (!response.ok) throw new Error(`Failed to load ${tld} data`);

      const laws = await response.json();
      this.dataCache[tld] = laws;
      return laws;
    } catch (error) {
      console.error(`Error loading jurisdiction data for ${tld}:`, error);
      return [];
    }
  }

  parseCSV(csvText) {
    return parseCSV(csvText);
  }

  getRegion(countryCode) {
    const regions = {
      EU: "europe",
      GB: "europe",
      CH: "europe",
      FR: "europe",
      ES: "europe",
      NL: "europe",
      US: "americas",
      CA: "americas",
      BR: "americas",
      MX: "americas",
      CL: "americas",
      JP: "asia_pacific",
      AU: "asia_pacific",
      SG: "asia_pacific",
      NZ: "asia_pacific",
      KR: "asia_pacific",
      TH: "asia_pacific",
      IN: "asia_pacific",
      ZA: "middle_east_africa",
    };
    return regions[countryCode] || "other";
  }
}

// Search & Filter
class CopyrightLawSearcher {
  constructor(jurisdictions) {
    this.jurisdictions = jurisdictions;
    this.results = [];
  }

  search(query, region = "", materialType = "") {
    this.results = this.jurisdictions.filter((jurisdiction) => {
      const matchesQuery =
        !query ||
        jurisdiction.name.toLowerCase().includes(query.toLowerCase()) ||
        jurisdiction.code.toLowerCase().includes(query.toLowerCase());

      const matchesRegion = !region || jurisdiction.region === region;

      const matchesMaterialType =
        !materialType ||
        jurisdiction.laws.some((law) =>
          law.protection_type.toLowerCase().includes(materialType.toLowerCase())
        );

      return matchesQuery && matchesRegion && matchesMaterialType;
    });
    return this.results;
  }

  getAllLaws() {
    const laws = [];
    this.results.forEach((jurisdiction) => {
      jurisdiction.laws.forEach((law) => {
        laws.push({ ...law, region: jurisdiction.region });
      });
    });
    return laws;
  }
}

// Comparator
class CopyrightLawComparator {
  constructor(loader) {
    this.loader = loader;
  }

  compareLaws(countryCodes) {
    const laws = [];
    countryCodes.forEach((code) => {
      const jurisdiction = this.loader.jurisdictions.find(
        (c) => c.code === code
      );
      if (jurisdiction) {
        jurisdiction.laws.forEach((law) => laws.push(law));
      }
    });
    return laws;
  }

  getComparisonMatrix(countryCodes) {
    const criteria = [
      "protection_type",
      "term_of_protection",
      "author_rights",
      "moral_rights",
      "orphan_works",
      "digital_protection",
      "fair_use_exceptions",
      "registration_required",
      "treaties_signatory",
    ];

    const matrix = {};
    countryCodes.forEach((code) => {
      const jurisdiction = this.loader.jurisdictions.find(
        (c) => c.code === code
      );
      if (jurisdiction && jurisdiction.laws.length > 0) {
        const law = jurisdiction.laws[0];
        matrix[code] = {};
        criteria.forEach((criterion) => {
          matrix[code][criterion] = law[criterion] || "N/A";
        });
      }
    });
    return matrix;
  }

  getProtectionTermCalculator() {
    return {
      calculateExpiration: (enactmentDate, term) => {
        // Parse term like "Author's life + 70 years" or "95 years (works for hire)"
        const match = term.match(/(\d+)\s*years?/i);
        if (!match) return "Unknown";
        const years = parseInt(match[1]);
        const date = new Date(enactmentDate);
        date.setFullYear(date.getFullYear() + years);
        return date.toLocaleDateString();
      },
    };
  }
}

// Region Manager
class RegionManager {
  static getRegionLabel(region) {
    const labels = {
      europe: "Europe",
      americas: "Americas",
      asia_pacific: "Asia-Pacific",
      middle_east_africa: "Middle East & Africa",
      other: "Other",
    };
    return labels[region] || region;
  }

  static groupByRegion(jurisdictions) {
    const grouped = {};
    jurisdictions.forEach((jurisdiction) => {
      if (!grouped[jurisdiction.region]) {
        grouped[jurisdiction.region] = [];
      }
      grouped[jurisdiction.region].push(jurisdiction);
    });
    return grouped;
  }
}

// UI Controller
class UIController {
  constructor(loader, searcher, comparator) {
    this.loader = loader;
    this.searcher = searcher;
    this.comparator = comparator;
    this.selectedRows = new Set();
    this.initializeEventListeners();
  }

  initializeEventListeners() {
    document.getElementById("searchBtn").addEventListener("click", () =>
      this.handleSearch()
    );
    document.getElementById("clearBtn").addEventListener("click", () =>
      this.handleClear()
    );
    document.getElementById("compareBtn").addEventListener("click", () =>
      this.handleCompare()
    );
    document.getElementById("exportBtn").addEventListener("click", () =>
      this.handleExport()
    );
    document.getElementById("selectAll").addEventListener("change", (e) =>
      this.handleSelectAll(e)
    );
    document
      .getElementById("closeComparison")
      .addEventListener("click", () => this.closeComparison());

    document
      .getElementById("jurisdictionSearch")
      .addEventListener("input", (e) => this.showSuggestions(e.target.value));
  }

  async handleSearch() {
    const query = document.getElementById("jurisdictionSearch").value;
    const region = document.getElementById("regionFilter").value;
    const materialType = document.getElementById("materialTypeFilter").value;

    this.searcher.search(query, region, materialType);
    this.renderResults();
  }

  handleClear() {
    document.getElementById("jurisdictionSearch").value = "";
    document.getElementById("regionFilter").value = "";
    document.getElementById("materialTypeFilter").value = "";
    this.selectedRows.clear();
    this.renderEmptyResults();
  }

  handleSelectAll(e) {
    const checkboxes = document.querySelectorAll('tbody input[type="checkbox"]');
    checkboxes.forEach((checkbox) => {
      checkbox.checked = e.target.checked;
      const rowKey = checkbox.dataset.rowKey;
      if (e.target.checked) {
        this.selectedRows.add(rowKey);
      } else {
        this.selectedRows.delete(rowKey);
      }
    });
  }

  handleCompare() {
    if (this.selectedRows.size === 0) {
      alert("Please select at least 2 jurisdictions to compare");
      return;
    }

    const countryCodes = Array.from(this.selectedRows).map(
      (key) => key.split("_")[0]
    );
    const uniqueCodes = [...new Set(countryCodes)];

    if (uniqueCodes.length < 2) {
      alert("Please select laws from at least 2 different jurisdictions");
      return;
    }

    this.renderComparison(uniqueCodes);
  }

  handleExport() {
    const laws = this.searcher.getAllLaws();
    let csv = this.searcherToCSV(laws);
    const blob = new Blob([csv], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = `copyright-laws-${new Date().toISOString().split("T")[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  }

  showSuggestions(query) {
    const suggestionsList = document.getElementById("jurisdictionSuggestions");
    if (!query) {
      suggestionsList.hidden = true;
      return;
    }

    const matches = this.loader.jurisdictions
      .filter((c) =>
        c.name.toLowerCase().includes(query.toLowerCase()) ||
        c.code.toLowerCase().includes(query.toLowerCase())
      )
      .slice(0, 8);

    suggestionsList.innerHTML = matches
      .map(
        (c) =>
          `<li><button data-code="${c.code}">${c.name} (${c.code})</button></li>`
      )
      .join("");

    suggestionsList.hidden = matches.length === 0;

    suggestionsList
      .querySelectorAll("button")
      .forEach((btn) =>
        btn.addEventListener("click", () => {
          document.getElementById("jurisdictionSearch").value =
            btn.textContent;
          suggestionsList.hidden = true;
        })
      );
  }

  renderResults() {
    const laws = this.searcher.getAllLaws();
    const tbody = document.getElementById("lawsTableBody");

    if (laws.length === 0) {
      this.renderEmptyResults();
      return;
    }

    tbody.innerHTML = laws
      .map(
        (law, index) => `
      <tr class="row-${law.country_code}">
        <td>
          <input type="checkbox" data-row-key="${law.country_code}_${index}" />
        </td>
        <td><strong>${law.country_name}</strong></td>
        <td>${law.law_name}</td>
        <td><small>${law.term_of_protection}</small></td>
        <td><small>${law.author_rights}</small></td>
        <td><small>${law.moral_rights}</small></td>
        <td><small>${law.treaties_signatory || "N/A"}</small></td>
        <td>
          ${
            law.linked_resources
              ? `<a href="${law.linked_resources}" target="_blank">📖</a>`
              : "-"
          }
        </td>
      </tr>
    `
      )
      .join("");

    document.getElementById("resultCount").textContent = laws.length;
    document.getElementById("resultsTitle").textContent =
      `Copyright Laws (${laws.length} results)`;

    // Show timeline visualization
    this.renderTimeline(laws);

    tbody.querySelectorAll("input[type='checkbox']").forEach((checkbox) => {
      checkbox.addEventListener("change", (e) => {
        if (e.target.checked) {
          this.selectedRows.add(e.target.dataset.rowKey);
        } else {
          this.selectedRows.delete(e.target.dataset.rowKey);
        }
      });
    });
  }

  renderTimeline(laws) {
    const timelineSection = document.getElementById("timelineSection");
    if (laws.length > 0) {
      const timeline = new TimelineVisualization(laws, "timelineContainer");
      timeline.render();
      timelineSection.hidden = false;
    } else {
      timelineSection.hidden = true;
    }
  }

  renderEmptyResults() {
    const tbody = document.getElementById("lawsTableBody");
    tbody.innerHTML = `
      <tr class="empty-state">
        <td colspan="8">Enter search criteria and click "Search" to view copyright laws</td>
      </tr>
    `;
    document.getElementById("resultCount").textContent = "0";
  }

  renderComparison(countryCodes) {
    const matrix = this.comparator.getComparisonMatrix(countryCodes);
    const section = document.getElementById("comparisonSection");
    const resultsDiv = document.getElementById("comparisonResults");

    let html = `<table class="comparison-table">
      <thead>
        <tr>
          <th>Criteria</th>
          ${countryCodes.map((code) => `<th>${code}</th>`).join("")}
        </tr>
      </thead>
      <tbody>`;

    const criteria = [
      "protection_type",
      "term_of_protection",
      "author_rights",
      "moral_rights",
      "orphan_works",
      "digital_protection",
      "fair_use_exceptions",
      "treaties_signatory",
    ];

    criteria.forEach((criterion) => {
      html += `<tr><th>${this.formatCriterion(criterion)}</th>`;
      countryCodes.forEach((code) => {
        const value = matrix[code]?.[criterion] || "N/A";
        html += `<td>${value}</td>`;
      });
      html += `</tr>`;
    });

    html += `</tbody></table>`;
    resultsDiv.innerHTML = html;
    section.hidden = false;
  }

  closeComparison() {
    document.getElementById("comparisonSection").hidden = true;
  }

  formatCriterion(criterion) {
    return criterion
      .split("_")
      .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
      .join(" ");
  }

  searcherToCSV(laws) {
    const headers = Object.keys(laws[0] || {});
    let csv = headers.join(",") + "\n";
    laws.forEach((law) => {
      csv += headers
        .map((h) => `"${(law[h] || "").replace(/"/g, '""')}"`)
        .join(",");
      csv += "\n";
    });
    return csv;
  }
}

// Initialize App
async function initApp() {
  try {
    // Load preferred language from localStorage or default to eng
    const preferredLang = localStorage.getItem("preferredLanguage") || "eng";
    await loadTranslations(preferredLang);

    const loader = new JurisdictionLoader();
    await loader.load();

    const searcher = new CopyrightLawSearcher(loader.jurisdictions);
    const comparator = new CopyrightLawComparator(loader);
    const ui = new UIController(loader, searcher, comparator);

    document.getElementById("lastUpdate").textContent = new Date()
      .toLocaleDateString();
  } catch (error) {
    console.error("Failed to initialize app:", error);
    document.body.innerHTML =
      '<p style="color:red">Error loading copyright laws database. Please refresh.</p>';
  }
}

// Load app when DOM is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initApp);
} else {
  initApp();
}
