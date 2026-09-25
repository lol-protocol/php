import { fetchJson, postJson } from "./sesion.js";
import { intentar } from "./nucleo.js";
import { mostrarError } from "./notificaciones.js";
import { t } from "./idioma.js";

let ultimaPeticionConfig = 0;

export async function cargarConfigAlertas() {
  const peticionId = ++ultimaPeticionConfig;
  const config = await intentar(
    () => fetchJson("/api/alerts-config"),
    "Error cargando configuración de alertas:",
    () => mostrarError(t("toast_error_cargar"))
  );
  if (!config || peticionId !== ultimaPeticionConfig) return; // una carga más nueva ya ganó

  document.getElementById("config-alerta-ip_pais").checked = config.alertas.ip_pais;
  document.getElementById("config-alerta-cambio_pais").checked = config.alertas.cambio_pais;
  document.getElementById("config-umbral").value = config.umbral;
  document.getElementById("config-umbral-valor").textContent = config.umbral;
}

export function toggleConfigPanel() {
  const panel = document.getElementById("alerts-config-panel");
  panel.hidden = !panel.hidden;
}

export async function guardarConfigAlertas(onGuardado) {
  const alertas = {
    ip_pais: document.getElementById("config-alerta-ip_pais").checked,
    cambio_pais: document.getElementById("config-alerta-cambio_pais").checked,
  };
  const umbral = parseInt(document.getElementById("config-umbral").value, 10);

  await intentar(async () => {
    await postJson("/api/alerts-config", { alertas, umbral });
    if (onGuardado) await onGuardado();
  }, "Error guardando configuración de alertas:", () => mostrarError(t("toast_error_guardar")));
}
