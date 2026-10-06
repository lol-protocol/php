import { test } from "node:test";
import assert from "node:assert/strict";
import "./navegador-falso.mjs"; // simula localStorage y window.location, que los módulos de interfaz/js/ leen al importarse

/** Un nodo de DOM mínimo: hijos, clases, texto, listeners que se pueden disparar a mano, remove() y focus(). */
class NodoFalso {
  constructor(etiqueta) {
    this.etiqueta = etiqueta;
    this.children = [];
    this.className = "";
    this.textContent = "";
    this.value = "";
    this.removido = false;
    this.escuchas = {};
  }
  setAttribute() {}
  appendChild(hijo) {
    this.children.push(hijo);
    return hijo;
  }
  addEventListener(tipo, fn) {
    (this.escuchas[tipo] ??= []).push(fn);
  }
  disparar(tipo, evento = {}) {
    (this.escuchas[tipo] ?? []).forEach((fn) => fn({ target: this, ...evento }));
  }
  remove() {
    this.removido = true;
  }
  focus() {
    document.activeElement = this;
  }
  /** Todos los descendientes que cumplen el criterio, en orden. */
  buscar(criterio) {
    return this.children.flatMap((hijo) => [...(criterio(hijo) ? [hijo] : []), ...hijo.buscar(criterio)]);
  }
}

const deTexto = (texto) => (nodo) => nodo.textContent === texto;

function montarDocumento() {
  const teclas = new Set();
  globalThis.document = {
    body: new NodoFalso("body"),
    activeElement: null,
    createElement: (etiqueta) => new NodoFalso(etiqueta),
    addEventListener: (tipo, fn) => tipo === "keydown" && teclas.add(fn),
    removeEventListener: (tipo, fn) => tipo === "keydown" && teclas.delete(fn),
    teclear: (key) => [...teclas].forEach((fn) => fn({ key })),
    teclasEscuchadas: () => teclas.size,
  };
  return document;
}

const { abrirModal, modalPrompt, modalConfirmar } = await import("../../interfaz/js/modal.js");

const BOTONES = [
  { texto: "Seguir", clase: "modal-btn-confirmar", valor: "seguir" },
  { texto: "Salir", clase: "modal-btn-peligro", valor: "salir" },
];
const abrir = (opciones = {}) => abrirModal({ mensaje: "¿Qué hacemos?", botones: BOTONES, alDescartar: "seguir", ...opciones });
const fondoAbierto = () => document.body.children.at(-1);
const boton = (texto) => fondoAbierto().buscar(deTexto(texto))[0];

test("abrirModal dibuja el título, el mensaje y los botones en orden, y deja el foco en la opción segura", () => {
  montarDocumento();
  abrir({ titulo: "Sesión por expirar" });
  const fondo = fondoAbierto();
  assert.equal(fondo.className, "modal-fondo");
  assert.equal(fondo.buscar((n) => n.etiqueta === "h3")[0].textContent, "Sesión por expirar");
  assert.equal(fondo.buscar((n) => n.etiqueta === "p")[0].textContent, "¿Qué hacemos?");
  assert.deepEqual(fondo.buscar((n) => n.etiqueta === "button").map((b) => [b.textContent, b.className]), [
    ["Seguir", "modal-btn modal-btn-confirmar"],
    ["Salir", "modal-btn modal-btn-peligro"],
  ]);
  assert.equal(document.activeElement, boton("Seguir"), "el foco va al botón que equivale a descartar");
});

test("el foco va a la opción segura aunque no sea el primer botón (en un confirmar, el de cancelar)", () => {
  montarDocumento();
  abrir({ botones: [BOTONES[1], BOTONES[0]], alDescartar: "seguir" });
  assert.equal(document.activeElement, boton("Seguir"));
  document.teclear("Escape");

  modalConfirmar("¿Borrar?", "Borrar", { peligroso: true });
  const cancelar = fondoAbierto().buscar((n) => n.className === "modal-btn modal-btn-cancelar")[0];
  assert.equal(document.activeElement, cancelar, "pulsar Enter por reflejo no confirma un borrado");
  document.teclear("Escape");
});

test("abrirModal sin título no dibuja un h3 vacío", () => {
  montarDocumento();
  abrir();
  assert.equal(fondoAbierto().buscar((n) => n.etiqueta === "h3").length, 0);
});

test("el valor de la promesa es el del botón pulsado, y el modal se cierra", async () => {
  montarDocumento();
  const { eleccion } = abrir();
  boton("Salir").disparar("click");
  assert.equal(await eleccion, "salir");
  assert.equal(fondoAbierto().removido, true);
  assert.equal(document.teclasEscuchadas(), 0, "no queda ningún listener de teclado colgado");
});

test("Escape y un clic en el fondo (no dentro del cuadro) devuelven alDescartar; un clic dentro del cuadro no cierra nada", async () => {
  montarDocumento();
  let { eleccion } = abrir({ alDescartar: "seguir" });
  const caja = fondoAbierto().children[0];
  fondoAbierto().disparar("click", { target: caja }); // un clic que subió desde adentro del cuadro
  assert.equal(fondoAbierto().removido, false);
  document.teclear("Escape");
  assert.equal(await eleccion, "seguir");

  ({ eleccion } = abrir({ alDescartar: "nada" }));
  fondoAbierto().disparar("click"); // target: el fondo mismo
  assert.equal(await eleccion, "nada");
});

test("otra tecla no cierra el modal; Enter solo confirma cuando hay un campo de texto", async () => {
  montarDocumento();
  let { eleccion } = abrir();
  document.teclear("Enter");
  document.teclear("a");
  assert.equal(fondoAbierto().removido, false, "sin campo de texto, Enter no decide nada (lo hace el botón con foco)");
  boton("Seguir").disparar("click");
  assert.equal(await eleccion, "seguir");

  const texto = modalPrompt("¿Cómo se llama?", "Guardar");
  fondoAbierto().buscar((n) => n.etiqueta === "input")[0].value = "mi filtro";
  document.teclear("Enter");
  assert.equal(await texto, "mi filtro");
});

test("descartar() cierra el modal desde afuera y la promesa devuelve alDescartar", async () => {
  montarDocumento();
  const { eleccion, descartar } = abrir({ alDescartar: "seguir" });
  descartar();
  assert.equal(await eleccion, "seguir");
  assert.equal(fondoAbierto().removido, true);
});

test("abrir un modal con otro abierto cierra el anterior: nunca quedan dos encimados", async () => {
  montarDocumento();
  const primero = abrir({ alDescartar: "primero-descartado" });
  const fondoPrimero = fondoAbierto();
  const segundo = abrir();
  assert.equal(await primero.eleccion, "primero-descartado");
  assert.equal(fondoPrimero.removido, true);
  assert.equal(fondoAbierto().removido, false);
  segundo.descartar();
});

test("modalConfirmar: true al confirmar, false al cancelar o con Escape; peligroso tiñe el botón de rojo", async () => {
  montarDocumento();
  let respuesta = modalConfirmar("¿Borrar?", "Borrar", { peligroso: true });
  assert.equal(boton("Borrar").className, "modal-btn modal-btn-peligro");
  boton("Borrar").disparar("click");
  assert.equal(await respuesta, true);

  respuesta = modalConfirmar("¿Guardar?", "Guardar");
  assert.equal(boton("Guardar").className, "modal-btn modal-btn-confirmar");
  document.teclear("Escape");
  assert.equal(await respuesta, false);

  respuesta = modalConfirmar("¿Guardar?", "Guardar");
  fondoAbierto().buscar((n) => n.className === "modal-btn modal-btn-cancelar")[0].disparar("click");
  assert.equal(await respuesta, false);
});

test("modalPrompt: el texto escrito al confirmar, null al cancelar o con Escape", async () => {
  montarDocumento();
  let respuesta = modalPrompt("Nombre", "Guardar");
  const campo = fondoAbierto().buscar((n) => n.etiqueta === "input")[0];
  assert.equal(document.activeElement, campo, "el foco va al campo de texto");
  campo.value = "algo";
  boton("Guardar").disparar("click");
  assert.equal(await respuesta, "algo");

  respuesta = modalPrompt("Nombre", "Guardar");
  document.teclear("Escape");
  assert.equal(await respuesta, null);
});
