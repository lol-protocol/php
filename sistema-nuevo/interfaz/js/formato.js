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

// El porcentaje tal como se muestra: entero, con la mitad redondeada hacia afuera. Number() normaliza
// el "-0" de toFixed (un -0.3 daba "-0%"): -0 se imprime como "0".
const redondearPct = (pct) => Number(pct.toFixed(0));

export function formatPct(pct) {
  const entero = redondearPct(pct);
  return `${entero > 0 ? "+" : ""}${entero}%`;
}

// Un delta dentro de esta franja (inclusive, en ambos sentidos) cuenta como "en el promedio":
// ni verde ni rojo.
const DELTA_PROMEDIO_PCT = 10;

/**
 * Cómo se clasifica un delta % contra el promedio del universo: "none" sin comparación (null),
 * "avg" dentro de ±10%, y si no "good" o "bad" según convenga que el valor sea menor (duración
 * y monto, que es el caso de hoy) o mayor. Se mide sobre el porcentaje que se muestra (ver
 * formatPct): un badge que dice "+10%" tiene que ser "en el promedio", no rojo.
 */
export function classifyDelta(deltaPct, betterWhenLower = true) {
  if (deltaPct === null) return "none";
  const mostrado = redondearPct(deltaPct);
  if (Math.abs(mostrado) <= DELTA_PROMEDIO_PCT) return "avg";
  return (betterWhenLower ? mostrado < 0 : mostrado > 0) ? "good" : "bad";
}
