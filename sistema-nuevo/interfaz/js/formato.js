// El timestamp ya llega del backend como texto en formato Y.m.d.H.i.s
// (ej. "2026.09.03.19.47.10"): se muestra tal cual, sin reformatear acá.
import { state } from "./nucleo.js";

export function formatDuration(ms) {
  if (ms < 1000) return `${Math.round(ms)} ms`;
  if (ms < 60000) return `${(ms / 1000).toFixed(1)} s`;
  // Redondear minutos y segundos por separado permite que el resto de
  // segundos redondee a 60 sin acarrear el minuto extra (ej. 119500ms
  // daba "1 m 60 s"): se redondea el total una sola vez y se deriva de ahí.
  const totalSeconds = Math.round(ms / 1000);
  const minutes = Math.floor(totalSeconds / 60);
  const seconds = totalSeconds % 60;
  return `${minutes} m ${seconds} s`;
}

export function formatMoney(amount, currency) {
  const locale = state.lang === "en" ? "en-US" : "es";
  return new Intl.NumberFormat(locale, { style: "currency", currency }).format(amount);
}

export function formatFileSize(kb) {
  return kb >= 1024 ? `${(kb / 1024).toFixed(1)} MB` : `${kb.toFixed(1)} KB`;
}

export function formatPct(pct) {
  const sign = pct > 0 ? "+" : "";
  return `${sign}${pct.toFixed(0)}%`;
}
