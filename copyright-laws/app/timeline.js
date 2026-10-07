import { t } from "./translations/i18n.js";

export class TimelineVisualization {
  constructor(laws, containerId = "timelineContainer") {
    this.laws = laws;
    this.containerId = containerId;
  }

  render() {
    const container = document.getElementById(this.containerId);
    if (!container) return;

    const timelineData = this.processLawTimeline();
    if (timelineData.length === 0) {
      container.innerHTML = "<p>No timeline data available</p>";
      return;
    }

    container.innerHTML = this.generateTimelineHTML(timelineData);
    this.addTimelineStyles();
  }

  processLawTimeline() {
    const timeline = [];

    this.laws.forEach((law) => {
      const termMatch = law.term_of_protection?.match(/(\d{1,3})\s*years?/i);
      if (termMatch) {
        const years = parseInt(termMatch[1]);
        const baseYear = 2026;
        const expiryYear = baseYear + years;

        timeline.push({
          year: expiryYear,
          name: law.law_name,
          jurisdiction: law.country_name,
          code: law.country_code,
          term: law.term_of_protection,
          protection: law.protection_type,
        });
      }
    });

    return timeline.sort((a, b) => a.year - b.year);
  }

  generateTimelineHTML(timelineData) {
    const grouped = this.groupByYear(timelineData);
    const years = Object.keys(grouped).sort((a, b) => Number(a) - Number(b));

    const minYear = Math.min(...years.map(Number));
    const maxYear = Math.max(...years.map(Number));
    const range = maxYear - minYear;
    const scale = range > 0 ? 100 / range : 100;

    let html = `
      <div class="timeline-wrapper">
        <div class="timeline-header">
          <h3>${t("timeline.title")}</h3>
          <p class="timeline-info">${timelineData.length} ${t("results.count")}</p>
        </div>
        <div class="timeline-container">
          <div class="timeline-axis" style="width: 100%;">
            <div class="timeline-scale">
              <span class="timeline-year">${minYear}</span>
              <span class="timeline-year">${maxYear}</span>
            </div>
    `;

    years.forEach((year) => {
      const position = ((Number(year) - minYear) * scale).toFixed(2);
      const items = grouped[year];
      const isOdd = years.indexOf(year) % 2 === 0;

      html += `
        <div class="timeline-event" style="left: ${position}%;">
          <div class="timeline-dot"></div>
          <div class="timeline-content ${isOdd ? "timeline-top" : "timeline-bottom"}">
            <div class="timeline-year-label">${year}</div>
            <div class="timeline-items">
      `;

      items.forEach((item) => {
        html += `
          <div class="timeline-item">
            <strong>${item.jurisdiction}</strong>
            <span class="timeline-law">${item.name}</span>
            <span class="timeline-term">${item.term}</span>
          </div>
        `;
      });

      html += `
            </div>
          </div>
        </div>
      `;
    });

    html += `
          </div>
        </div>
        <p class="timeline-note">Protection expiry dates calculated from 2026 baseline</p>
      </div>
    `;

    return html;
  }

  groupByYear(timelineData) {
    const grouped = {};
    timelineData.forEach((item) => {
      if (!grouped[item.year]) {
        grouped[item.year] = [];
      }
      grouped[item.year].push(item);
    });
    return grouped;
  }

  addTimelineStyles() {
    if (document.getElementById("timeline-styles")) return;

    const style = document.createElement("style");
    style.id = "timeline-styles";
    style.textContent = `
      .timeline-wrapper {
        padding: 20px;
        background: var(--color-bg-secondary);
        border-radius: 8px;
        margin: 20px 0;
      }

      .timeline-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        border-bottom: 2px solid var(--color-border);
        padding-bottom: 10px;
      }

      .timeline-header h3 {
        margin: 0;
        color: var(--color-text);
        font-size: 18px;
      }

      .timeline-info {
        margin: 0;
        color: var(--color-text-secondary);
        font-size: 14px;
      }

      .timeline-container {
        position: relative;
        height: 400px;
        margin: 40px 0;
      }

      .timeline-axis {
        position: relative;
        height: 2px;
        background: var(--color-primary);
        margin: 50px 0;
      }

      .timeline-scale {
        display: flex;
        justify-content: space-between;
        position: absolute;
        width: 100%;
        top: -25px;
        font-size: 12px;
        color: var(--color-text-secondary);
      }

      .timeline-year {
        font-weight: 600;
      }

      .timeline-event {
        position: absolute;
        top: 0;
        transform: translateX(-50%);
      }

      .timeline-dot {
        width: 16px;
        height: 16px;
        background: var(--color-primary);
        border: 3px solid var(--color-bg-primary);
        border-radius: 50%;
        position: absolute;
        top: -7px;
        left: -8px;
        box-shadow: 0 0 0 4px var(--color-bg-secondary);
      }

      .timeline-content {
        position: absolute;
        min-width: 200px;
        left: 30px;
        white-space: normal;
      }

      .timeline-top {
        bottom: 30px;
      }

      .timeline-bottom {
        top: 30px;
      }

      .timeline-year-label {
        font-weight: 700;
        color: var(--color-primary);
        font-size: 14px;
        margin-bottom: 8px;
      }

      .timeline-items {
        background: var(--color-bg-primary);
        border: 1px solid var(--color-border);
        border-radius: 4px;
        padding: 8px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      }

      .timeline-item {
        font-size: 12px;
        line-height: 1.4;
        margin-bottom: 4px;
      }

      .timeline-item:last-child {
        margin-bottom: 0;
      }

      .timeline-law {
        display: block;
        color: var(--color-text-secondary);
        font-style: italic;
      }

      .timeline-term {
        display: block;
        color: var(--color-text-secondary);
        font-size: 11px;
        margin-top: 2px;
      }

      .timeline-note {
        font-size: 12px;
        color: var(--color-text-secondary);
        text-align: center;
        margin-top: 10px;
        font-style: italic;
      }

      @media (prefers-color-scheme: dark) {
        .timeline-items {
          background: var(--color-bg-primary);
          box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }
      }

      @media (max-width: 768px) {
        .timeline-container {
          height: 600px;
        }

        .timeline-content {
          min-width: 150px;
          font-size: 12px;
        }

        .timeline-event {
          left: 50% !important;
        }

        .timeline-top,
        .timeline-bottom {
          left: 30px !important;
        }
      }
    `;
    document.head.appendChild(style);
  }
}

export default TimelineVisualization;
