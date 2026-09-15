import { test } from "node:test";
import assert from "node:assert/strict";

// alertas.js importa idioma.js, que lee localStorage al importarse.
globalThis.localStorage = { getItem: () => null, setItem: () => {} };

/** DOM mínimo, sin dependencias: alcanza con lo que usan el() y renderAlerts(). */
class ElementoFalso {
  constructor() {
    this.children = [];
    this.className = "";
    this.textContent = "";
    this.hidden = false;
  }
  set innerHTML(_valor) {
    this.children = [];
  }
  setAttribute() {}
  addEventListener() {}
  appendChild(hijo) {
    this.children.push(hijo);
    return hijo;
  }
  querySelectorAll(selector) {
    const clase = selector.replace(".", "");
    const encontrados = [];
    const recorrer = (nodo) => {
      if (nodo.className?.split(" ").includes(clase)) encontrados.push(nodo);
      nodo.children?.forEach(recorrer);
    };
    this.children.forEach(recorrer);
    return encontrados;
  }
}

const elementosPorId = new Map();
globalThis.document = {
  getElementById: (id) => {
    if (!elementosPorId.has(id)) elementosPorId.set(id, new ElementoFalso());
    return elementosPorId.get(id);
  },
  createElement: () => new ElementoFalso(),
};

const { renderAlerts } = await import("../../interfaz/js/alertas.js");

test("renderAlerts: un tipo habilitado sin resultados (top: []) no dibuja una sección vacía", () => {
  renderAlerts(
    {
      ip_pais_mismatch: {
        total_mismatches: 3,
        total_users_affected: 1,
        top: [{ user_id: "u001", user_name: "Ana", country: "AR", mismatch_count: 3, last_seen: "2026-01-01" }],
      },
      cambios_pais_imposibles: { total_changes: 0, total_users_affected: 0, top: [] },
    },
    () => {}
  );

  const secciones = elementosPorId.get("alerts-list").querySelectorAll(".alerts-section");
  assert.equal(secciones.length, 1, "solo debe dibujarse la sección con datos reales");
  assert.equal(secciones[0].querySelectorAll(".alerts-item").length, 1);
});

test("renderAlerts: sin ninguna alerta real, el panel entero queda oculto", () => {
  renderAlerts({ ip_pais_mismatch: { total_mismatches: 0, total_users_affected: 0, top: [] } }, () => {});
  assert.equal(elementosPorId.get("alerts-panel").hidden, true);
});
