// El timestamp ya llega del backend como texto en formato Y.m.d.H.i.s
// (ej. "2026.09.03.19.47.10"): se muestra tal cual, sin reformatear acá.

export function formatDuration(ms) {
  if (ms < 1000) return `${Math.round(ms)} ms`;
  if (ms < 60000) return `${(ms / 1000).toFixed(1)} s`;
  const minutes = Math.floor(ms / 60000);
  const seconds = Math.round((ms % 60000) / 1000);
  return `${minutes} m ${seconds} s`;
}

export function formatMoney(amount, currency) {
  return new Intl.NumberFormat("es", { style: "currency", currency }).format(amount);
}

export function formatFileSize(kb) {
  return kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${kb.toFixed(1)} KB`;
}

export function formatPct(pct) {
  const sign = pct > 0 ? "+" : "";
  return `${sign}${pct.toFixed(0)}%`;
}
