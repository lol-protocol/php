// Minimal single-series bar chart built from plain HTML/CSS (styles: styles/filters.css, .viz*).
// All text reaches the DOM through textContent, so data can never inject markup.
//
// spec = {
//   title, subtitle,
//   bars: [{ key, label, name?, value, items?: [{ primary, secondary }], showLabel?: boolean }],
//                                       label = short axis text, name = long text for tooltip/table/screen readers
//   formatValue(n): string,            // "17 laws"
//   labels: { showTable, showChart, category, value, details, more(n) },
//   showTable?: boolean, onToggle?(isTable)   // remember the table/chart choice across re-renders
// }

export function niceScale(max) {
  if (!(max > 0)) return { max: 1, step: 1 };
  const steps = [1, 2, 5, 10, 20, 25, 50, 100, 200, 250, 500, 1000];
  const step = steps.find((s) => max / s <= 4) ?? Math.ceil(max / 4);
  return { max: Math.ceil(max / step) * step, step };
}

function el(tag, className, text) {
  const node = document.createElement(tag);
  if (className) node.className = className;
  if (text != null) node.textContent = text;
  return node;
}

const MAX_TOOLTIP_ITEMS = 6;

export function renderBarChart(host, spec) {
  host.replaceChildren();
  if (!spec.bars.length) return null;

  const max = Math.max(...spec.bars.map((b) => b.value));
  const scale = niceScale(max);
  const peakIndex = spec.bars.findIndex((b) => b.value === max);

  const figure = el("figure", "viz");
  figure.style.setProperty("--cols", String(spec.bars.length));

  const head = el("div", "viz-head");
  const titles = el("div");
  titles.append(el("h3", "viz-title", spec.title), el("p", "viz-sub", spec.subtitle));
  const toggle = el("button", "btn btn-sm btn-outline viz-toggle", spec.labels.showTable);
  toggle.type = "button";
  toggle.setAttribute("aria-pressed", "false");
  head.append(titles, toggle);

  // Axis + plot
  const body = el("div", "viz-body");
  const yaxis = el("div", "viz-yaxis");
  yaxis.setAttribute("aria-hidden", "true");
  const scroll = el("div", "viz-scroll");
  const plot = el("div", "viz-plot");
  const grid = el("div", "viz-grid");
  grid.setAttribute("aria-hidden", "true");

  for (let tick = 0; tick <= scale.max; tick += scale.step) {
    const position = String(tick / scale.max);
    const label = el("span", "viz-ytick", String(tick));
    label.style.setProperty("--p", position);
    yaxis.append(label);
    const line = el("i", tick === 0 ? "viz-baseline" : "");
    line.style.setProperty("--p", position);
    grid.append(line);
  }

  const cols = el("ol", "viz-cols");
  cols.setAttribute("aria-label", spec.title);
  const columns = spec.bars.map((bar, index) => {
    const col = el("li", "viz-col");
    col.tabIndex = index === 0 ? 0 : -1;
    col.setAttribute("role", "img");
    col.setAttribute("aria-label", `${bar.name ?? bar.label}: ${spec.formatValue(bar.value)}`);

    const wrap = el("span", "viz-barwrap");
    const mark = el("span", "viz-bar");
    mark.style.setProperty("--v", String(bar.value / scale.max));
    wrap.append(mark);
    if (index === peakIndex && bar.value > 0) {
      const peak = el("span", "viz-peak", String(bar.value));
      peak.style.setProperty("--v", String(bar.value / scale.max));
      wrap.append(peak);
    }
    col.append(wrap, el("span", "viz-x", bar.showLabel === false ? "" : bar.label));
    cols.append(col);
    return col;
  });

  plot.append(grid, cols);
  scroll.append(plot);
  body.append(yaxis, scroll);

  // Tooltip (one for the whole chart)
  const tip = el("div", "viz-tip");
  tip.setAttribute("role", "tooltip");
  tip.hidden = true;

  function showTip(index) {
    const bar = spec.bars[index];
    tip.replaceChildren();
    const lead = el("div", "viz-tip-lead");
    lead.append(el("strong", null, spec.formatValue(bar.value)), el("span", "viz-tip-label", bar.name ?? bar.label));
    tip.append(lead);
    const items = bar.items ?? [];
    if (items.length) {
      const list = el("ul", "viz-tip-list");
      for (const item of items.slice(0, MAX_TOOLTIP_ITEMS)) {
        const li = el("li");
        li.append(el("span", null, item.primary));
        if (item.secondary) li.append(el("span", "viz-tip-label", item.secondary));
        list.append(li);
      }
      tip.append(list);
      if (items.length > MAX_TOOLTIP_ITEMS) {
        tip.append(el("div", "viz-tip-label", spec.labels.more(items.length - MAX_TOOLTIP_ITEMS)));
      }
    }
    tip.hidden = false;

    const fig = figure.getBoundingClientRect();
    const col = columns[index].getBoundingClientRect();
    const width = tip.offsetWidth;
    let left = col.left - fig.left + col.width / 2 - width / 2;
    left = Math.max(4, Math.min(left, fig.width - width - 4));
    const top = Math.max(4, Math.min(col.top - fig.top + 4, fig.height - tip.offsetHeight - 4));
    tip.style.left = `${left}px`;
    tip.style.top = `${top}px`;
  }
  const hideTip = () => {
    tip.hidden = true;
  };

  columns.forEach((col, index) => {
    col.addEventListener("pointerenter", () => {
      if (spec.bars[index].value > 0) showTip(index);
    });
    col.addEventListener("pointerleave", hideTip);
    col.addEventListener("focus", () => showTip(index));
    col.addEventListener("blur", hideTip);
  });

  // One tab stop for the whole chart; arrows move between bars (roving tabindex).
  cols.addEventListener("keydown", (event) => {
    const current = columns.indexOf(document.activeElement);
    if (current < 0) return;
    let next = current;
    if (event.key === "ArrowRight") next = Math.min(columns.length - 1, current + 1);
    else if (event.key === "ArrowLeft") next = Math.max(0, current - 1);
    else if (event.key === "Home") next = 0;
    else if (event.key === "End") next = columns.length - 1;
    else if (event.key === "Escape") return hideTip();
    else return;
    event.preventDefault();
    columns[current].tabIndex = -1;
    columns[next].tabIndex = 0;
    columns[next].focus();
    columns[next].scrollIntoView({ block: "nearest", inline: "nearest" });
  });

  // Table twin of the chart (every value reachable without hovering)
  const tableWrap = el("div", "viz-tablewrap table-container");
  tableWrap.hidden = true;
  const table = el("table", "data-table viz-table");
  table.append(el("caption", "sr-only", spec.title));
  const headRow = el("tr");
  for (const text of [spec.labels.category, spec.labels.value, spec.labels.details]) {
    const th = el("th", null, text);
    th.scope = "col";
    headRow.append(th);
  }
  const thead = el("thead");
  thead.append(headRow);
  table.append(thead);
  const tbody = el("tbody");
  for (const bar of spec.bars) {
    if (!bar.value) continue;
    const tr = el("tr");
    const details = (bar.items ?? []).map((i) => (i.secondary ? `${i.primary} — ${i.secondary}` : i.primary)).join("; ");
    tr.append(el("td", null, bar.name ?? bar.label), el("td", null, String(bar.value)), el("td", null, details));
    tbody.append(tr);
  }
  table.append(tbody);
  tableWrap.append(table);

  const setTableView = (showingTable) => {
    toggle.setAttribute("aria-pressed", String(showingTable));
    toggle.textContent = showingTable ? spec.labels.showChart : spec.labels.showTable;
    body.hidden = showingTable;
    tableWrap.hidden = !showingTable;
    hideTip();
  };
  toggle.addEventListener("click", () => {
    const showingTable = toggle.getAttribute("aria-pressed") !== "true";
    setTableView(showingTable);
    spec.onToggle?.(showingTable);
  });
  if (spec.showTable) setTableView(true);

  figure.append(head, body, tip, tableWrap);
  host.append(figure);
  return figure;
}
