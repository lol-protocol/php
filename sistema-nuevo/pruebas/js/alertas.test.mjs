import { test } from "node:test";
import assert from "node:assert/strict";
import "./navegador-falso.mjs"; // simula localStorage y window.location, que los módulos de interfaz/js/ leen al importarse

/** DOM mínimo, sin dependencias: alcanza con lo que usan el() y renderAlerts(). */
class ElementoFalso {
  constructor() {
    this.children = [];
    this.className = "";
    this.textContent = "";
    this.hidden = false;
    this.atributos = {};
    this.escuchas = {};
  }
  set innerHTML(_valor) {
    this.children = [];
  }
  setAttribute(nombre, valor) {
    this.atributos[nombre] = valor;
  }
  addEventListener(tipo, fn) {
    (this.escuchas[tipo] ??= []).push(fn);
  }
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
      ip_pais: {
        total_events: 3,
        total_users_affected: 1,
        top: [{ user_id: "u001", user_name: "Ana", event_count: 3, last_seen: "2026-01-01", country: "AR" }],
      },
      cambio_pais: { total_events: 0, total_users_affected: 0, top: [] },
    },
    () => {}
  );

  const secciones = elementosPorId.get("alerts-list").querySelectorAll(".alerts-section");
  assert.equal(secciones.length, 1, "solo debe dibujarse la sección con datos reales");
  assert.equal(secciones[0].querySelectorAll(".alerts-item").length, 1);
});

test("renderAlerts: sin ninguna alerta real, el panel entero queda oculto", () => {
  renderAlerts({ ip_pais: { total_events: 0, total_users_affected: 0, top: [] } }, () => {});
  assert.equal(elementosPorId.get("alerts-panel").hidden, true);
});

/** Los textos de los botones de una alerta ("Nombre (país) · cantidad") y a quién llevan al hacer clic, en el orden en que se dibujan. */
function botonesDibujados(datos, onSelectUser = () => {}) {
  renderAlerts(datos, onSelectUser);
  return elementosPorId.get("alerts-list").querySelectorAll(".alerts-button");
}

test("renderAlerts: las dos alertas se dibujan igual, cada una con su país: el declarado y el de donde venía", () => {
  const botones = botonesDibujados({
    ip_pais: {
      total_events: 14,
      total_users_affected: 1,
      top: [{ user_id: "u010", user_name: "Olivia", event_count: 14, last_seen: "2026-02-02", country: "BD" }],
    },
    cambio_pais: {
      total_events: 3,
      total_users_affected: 1,
      top: [{ user_id: "u020", user_name: "Ethan", event_count: 3, last_seen: "2026-03-03", previous_country: "ET", current_country: "AR" }],
    },
  });
  assert.deepEqual(botones.map((b) => b.textContent), ["Olivia (BD) · 14", "Ethan (ET) · 3"], "el cambio de país muestra el país de donde venía");
  assert.ok(botones[0].atributos.title.includes("2026-02-02") && botones[1].atributos.title.includes("2026-03-03"), "el tooltip dice la última vez");
});

test("renderAlerts: cada sección lleva el título de su tipo, en el orden en que se dibujan", () => {
  botonesDibujados({
    ip_pais: { total_events: 1, total_users_affected: 1, top: [{ user_id: "u1", user_name: "A", event_count: 1, last_seen: "x", country: "BD" }] },
    cambio_pais: { total_events: 1, total_users_affected: 1, top: [{ user_id: "u2", user_name: "B", event_count: 1, last_seen: "x", previous_country: "ET", current_country: "AR" }] },
  });
  const titulos = elementosPorId.get("alerts-list").querySelectorAll(".alerts-type-title").map((h) => h.textContent);
  assert.deepEqual(titulos, ["⚠ IP fuera del país declarado", "⚠ Cambios de país imposibles"]);
});

test("renderAlerts: el clic en una alerta lleva al usuario de esa fila, de cualquiera de los dos tipos", () => {
  const elegidos = [];
  const botones = botonesDibujados(
    {
      ip_pais: { total_events: 1, total_users_affected: 1, top: [{ user_id: "u010", user_name: "A", event_count: 1, last_seen: "x", country: "BD" }] },
      cambio_pais: { total_events: 1, total_users_affected: 1, top: [{ user_id: "u020", user_name: "B", event_count: 1, last_seen: "x", previous_country: "ET", current_country: "AR" }] },
    },
    (id) => elegidos.push(id)
  );
  botones.forEach((b) => b.escuchas.click[0]());
  assert.deepEqual(elegidos, ["u010", "u020"]);
});

test("renderAlerts: el panel se muestra si hay eventos en cualquiera de los dos tipos y se oculta si no hay en ninguno", () => {
  renderAlerts({ cambio_pais: { total_events: 2, total_users_affected: 1, top: [{ user_id: "u1", user_name: "N", event_count: 2, last_seen: "x", previous_country: "AR", current_country: "BR" }] } }, () => {});
  assert.equal(elementosPorId.get("alerts-panel").hidden, false);
  renderAlerts({ ip_pais: { total_events: 0, total_users_affected: 0, top: [] }, cambio_pais: { total_events: 0, total_users_affected: 0, top: [] } }, () => {});
  assert.equal(elementosPorId.get("alerts-panel").hidden, true);
});
