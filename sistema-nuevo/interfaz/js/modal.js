import { el } from "./nucleo.js";
import { t } from "./idioma.js";

// El modal abierto ahora (si hay uno): permite cerrarlo si se abre otro antes de que se haya cerrado, para no
// dejar dos superpuestos (pasa, por ejemplo, si llega el aviso de inactividad con un prompt abierto).
let modalActivo = null;

/**
 * Abre un modal con un mensaje, un título y un campo de texto si se piden, y una fila de botones. Devuelve
 * { eleccion, descartar }: eleccion es una promesa con el valor del botón pulsado (o con alDescartar si el
 * modal se cierra con Escape, con un clic en el fondo, con descartar() o porque se abre otro), y descartar()
 * lo cierra desde afuera. Con un campo de texto, Enter confirma (el primer botón). El foco va al campo o, si no
 * hay, al botón cuyo valor es alDescartar: la opción segura.
 *
 * @param {{ titulo?: string, mensaje: string, conInput?: boolean, alDescartar: unknown,
 *           botones: { texto: string, clase: string, valor: unknown }[] }} opciones
 *        El valor de un botón puede ser una función: se la llama al pulsarlo, con el campo de texto (o null).
 */
export function abrirModal({ titulo = null, mensaje, conInput = false, botones, alDescartar }) {
  modalActivo?.descartar();

  const inputEl = conInput ? el("input", { type: "text" }) : null;
  let resolver;
  const eleccion = new Promise((resolve) => {
    resolver = resolve;
  });

  const cerrar = (valor) => {
    fondo.remove();
    document.removeEventListener("keydown", alTeclear);
    if (modalActivo === modal) modalActivo = null;
    resolver(valor);
  };
  const valorDe = ({ valor }) => (typeof valor === "function" ? valor(inputEl) : valor);
  const descartar = () => cerrar(alDescartar);
  const modal = { eleccion, descartar };

  const alTeclear = (ev) => {
    if (ev.key === "Escape") descartar();
    else if (ev.key === "Enter" && inputEl) cerrar(valorDe(botones[0]));
  };

  const botonesEl = botones.map((boton) => {
    const botonEl = el("button", { class: `modal-btn ${boton.clase}`, text: boton.texto });
    botonEl.addEventListener("click", () => cerrar(valorDe(boton)));
    return botonEl;
  });

  const hijos = [];
  if (titulo) hijos.push(el("h3", { text: titulo }));
  hijos.push(el("p", { text: mensaje }));
  if (inputEl) hijos.push(inputEl);
  hijos.push(el("div", { class: "modal-botones" }, botonesEl));

  const fondo = el("div", { class: "modal-fondo" }, [el("div", { class: "modal-caja" }, hijos)]);
  fondo.addEventListener("click", (ev) => {
    if (ev.target === fondo) descartar();
  });

  document.body.appendChild(fondo);
  document.addEventListener("keydown", alTeclear);
  modalActivo = modal;
  (inputEl ?? botonesEl[botones.findIndex((boton) => boton.valor === alDescartar)] ?? botonesEl[0]).focus();
  return modal;
}

const botonCancelar = (valor) => ({ texto: t("modal_cancelar"), clase: "modal-btn-cancelar", valor });

/**
 * Reemplaza prompt(): el texto ingresado, o null si se cancela (Escape, click
 * afuera o botón cancelar). textoConfirmar es el texto del botón de confirmar
 * (el llamador ya tiene a mano el data-i18n del botón que abrió el modal, ej. "Guardar").
 */
export function modalPrompt(mensaje, textoConfirmar) {
  return abrirModal({
    mensaje,
    conInput: true,
    botones: [{ texto: textoConfirmar, clase: "modal-btn-confirmar", valor: (campo) => campo.value }, botonCancelar(null)],
    alDescartar: null,
  }).eleccion;
}

/**
 * Reemplaza confirm(): true si se confirma, false si se cancela.
 * peligroso=true tiñe el botón de confirmar en rojo (para acciones destructivas, ej. borrar).
 */
export function modalConfirmar(mensaje, textoConfirmar, { peligroso = false } = {}) {
  const claseConfirmar = peligroso ? "modal-btn-peligro" : "modal-btn-confirmar";
  return abrirModal({
    mensaje,
    botones: [{ texto: textoConfirmar, clase: claseConfirmar, valor: true }, botonCancelar(false)],
    alDescartar: false,
  }).eleccion;
}
