import { loadTranslations, t } from "./translations/i18n.js";
import LanguageSelector from "./translations/language-selector.js";
import TimelineVisualization from "./timeline.js";
import MetadataDisplay from "./metadata-display.js";

// Data Loaders
class CountryLoader {
  constructor() {
    this.countries = [];
    this.countryMap = {};
    this.dataCache = {};
  }

  async load() {
    try {
      // Load the master CSV to get list of countries
      const response = await fetch("../countries/privacy_laws_master.csv");
      const csvText = await response.text();
      const rawData = this.parseCSV(csvText);

      // Build country index from CSV
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

      this.countries = Object.values(index);
      this.countries.forEach((c) => (this.countryMap[c.code] = c));

      return this.countries;
    } catch (error) {
      console.error("Error loading privacy laws:", error);
      throw error;
    }
  }

  async loadCountryData(tld) {
    try {
      if (this.dataCache[tld]) return this.dataCache[tld];

      const response = await fetch(`../countries/${tld}/laws.json`);
      if (!response.ok) throw new Error(`Failed to load ${tld} data`);

      const laws = await response.json();
      this.dataCache[tld] = laws;
      return laws;
    } catch (error) {
      console.error(`Error loading country data for ${tld}:`, error);
      return [];
    }
  }

  parseCSV(csvText) {
    const lines = csvText.trim().split("\n");
    const headers = lines[0].split(",");
    const records = [];

    for (let i = 1; i < lines.length; i++) {
      const obj = {};
      const cols = lines[i].split(",");
      headers.forEach((header, index) => {
        obj[header.trim()] = cols[index]?.trim() || "";
      });
      records.push(obj);
    }
    return records;
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
      SV: "americas",
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
class PrivacyLawSearcher {
  constructor(countries) {
    this.countries = countries;
    this.results = [];
  }

  search(query, region = "", lawType = "") {
    this.results = this.countries.filter((country) => {
      const matchesQuery =
        !query ||
        country.name.toLowerCase().includes(query.toLowerCase()) ||
        country.code.toLowerCase().includes(query.toLowerCase());

      const matchesRegion = !region || country.region === region;

      const matchesLawType =
        !lawType ||
        country.laws.some((law) =>
          law.law_name.toLowerCase().includes(lawType.toLowerCase())
        );

      return matchesQuery && matchesRegion && matchesLawType;
    });
    return this.results;
  }

  getAllLaws() {
    const laws = [];
    this.results.forEach((country) => {
      country.laws.forEach((law) => {
        laws.push({ ...law, region: country.region });
      });
    });
    return laws;
  }
}

// Comparator
class PrivacyLawComparator {
  constructor(loader) {
    this.loader = loader;
  }

  compareLaws(countryCodes) {
    const laws = [];
    countryCodes.forEach((code) => {
      const country = this.loader.countries.find((c) => c.code === code);
      if (country) {
        country.laws.forEach((law) => laws.push(law));
      }
    });
    return laws;
  }

  getComparisonMatrix(countryCodes) {
    const criteria = [
      "scope",
      "applies_to",
      "key_requirements",
      "data_categories",
      "retention_period",
      "penalties_range",
      "exemptions",
    ];

    const matrix = {};
    countryCodes.forEach((code) => {
      const country = this.loader.countries.find((c) => c.code === code);
      if (country && country.laws.length > 0) {
        const law = country.laws[0];
        matrix[code] = {};
        criteria.forEach((criterion) => {
          matrix[code][criterion] = law[criterion] || "N/A";
        });
      }
    });
    return matrix;
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

  static groupByRegion(countries) {
    const grouped = {};
    countries.forEach((country) => {
      if (!grouped[country.region]) {
        grouped[country.region] = [];
      }
      grouped[country.region].push(country);
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
      .getElementById("countrySearch")
      .addEventListener("input", (e) => this.showSuggestions(e.target.value));
  }

  async handleSearch() {
    const query = document.getElementById("countrySearch").value;
    const region = document.getElementById("regionFilter").value;
    const lawType = document.getElementById("lawTypeFilter").value;

    this.searcher.search(query, region, lawType);
    this.renderResults();
  }

  handleClear() {
    document.getElementById("countrySearch").value = "";
    document.getElementById("regionFilter").value = "";
    document.getElementById("lawTypeFilter").value = "";
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
      alert("Please select at least 2 countries to compare");
      return;
    }

    const countryCodes = Array.from(this.selectedRows).map(
      (key) => key.split("_")[0]
    );
    const uniqueCodes = [...new Set(countryCodes)];

    if (uniqueCodes.length < 2) {
      alert("Please select laws from at least 2 different countries");
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
    a.download = `privacy-laws-${new Date().toISOString().split("T")[0]}.csv`;
    a.click();
    window.URL.revokeObjectURL(url);
  }

  showSuggestions(query) {
    const suggestionsList = document.getElementById("countrySuggestions");
    if (!query) {
      suggestionsList.hidden = true;
      return;
    }

    const matches = this.loader.countries
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
          document.getElementById("countrySearch").value = btn.textContent;
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
        <td>${law.enactment_date}</td>
        <td>${law.effective_date}</td>
        <td>${law.enforcement_authority}</td>
        <td><small>${law.penalties_range}</small></td>
        <td>
          ${
            law.website_url
              ? `<a href="${law.website_url}" target="_blank">📖</a>`
              : "-"
          }
        </td>
      </tr>
    `
      )
      .join("");

    document.getElementById("resultCount").textContent = laws.length;
    document.getElementById("resultsTitle").textContent =
      `Privacy Laws (${laws.length} results)`;

    // Show timeline visualization
    this.renderTimeline(laws);

    // Re-attach checkbox listeners
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
        <td colspan="8">Enter search criteria and click "Search" to view privacy laws</td>
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
      "scope",
      "applies_to",
      "key_requirements",
      "data_categories",
      "retention_period",
      "penalties_range",
      "exemptions",
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

    const loader = new CountryLoader();
    await loader.load();

    const searcher = new PrivacyLawSearcher(loader.countries);
    const comparator = new PrivacyLawComparator(loader);
    const ui = new UIController(loader, searcher, comparator);

    document.getElementById("lastUpdate").textContent = new Date()
      .toLocaleDateString();
  } catch (error) {
    console.error("Failed to initialize app:", error);
    document.body.innerHTML =
      '<p style="color:red">Error loading privacy laws database. Please refresh.</p>';
  }
}

// Load app when DOM is ready
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", initApp);
} else {
  initApp();
}
