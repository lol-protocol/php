import { fetchJson, postJson } from "./sesion.js";

export async function cargarConfigAlertas() {
  try {
    const config = await fetchJson("/api/alerts-config");
    document.getElementById("config-alerta-ip_pais").checked = config.alertas.ip_pais;
    document.getElementById("config-alerta-cambio_pais").checked = config.alertas.cambio_pais;
    document.getElementById("config-umbral").value = config.umbral;
    document.getElementById("config-umbral-valor").textContent = config.umbral;
  } catch (err) {
    console.error("Error cargando configuración de alertas:", err);
  }
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

  try {
    await postJson("/api/alerts-config", { alertas, umbral });
    if (onGuardado) await onGuardado();
  } catch (err) {
    console.error("Error guardando configuración de alertas:", err);
  }
}
