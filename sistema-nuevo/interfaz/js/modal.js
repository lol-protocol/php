import { el } from "./nucleo.js";
import { t } from "./idioma.js";

// Instancia abierta actual (si hay una): permite cerrarla si se abre otra
// antes de que la anterior se haya cerrado (no debería pasar con los usos
// actuales, pero evita dejar dos modales superpuestos si pasara).
let modalActivo = null;

function abrirModal({ mensaje, conInput, textoConfirmar, claseConfirmar }) {
  modalActivo?.cancelar();

  return new Promise((resolve) => {
    const inputEl = conInput ? el("input", { type: "text" }) : null;

    const cerrar = (valor) => {
      fondo.remove();
      document.removeEventListener("keydown", alTeclear);
      modalActivo = null;
      resolve(valor);
    };
    const confirmar = () => cerrar(conInput ? inputEl.value : true);
    const cancelar = () => cerrar(conInput ? null : false);

    const alTeclear = (ev) => {
      if (ev.key === "Escape") cancelar();
      else if (ev.key === "Enter" && conInput) confirmar();
    };

    const btnConfirmar = el("button", { class: `modal-btn ${claseConfirmar}`, text: textoConfirmar });
    const btnCancelar = el("button", { class: "modal-btn modal-btn-cancelar", text: t("modal_cancelar") });
    btnConfirmar.addEventListener("click", confirmar);
    btnCancelar.addEventListener("click", cancelar);

    const hijos = [el("p", { text: mensaje })];
    if (inputEl) hijos.push(inputEl);
    hijos.push(el("div", { class: "modal-botones" }, [btnConfirmar, btnCancelar]));

    const fondo = el("div", { class: "modal-fondo" }, [el("div", { class: "modal-caja" }, hijos)]);
    fondo.addEventListener("click", (ev) => {
      if (ev.target === fondo) cancelar();
    });

    document.body.appendChild(fondo);
    document.addEventListener("keydown", alTeclear);
    modalActivo = { cancelar };
    (inputEl ?? btnCancelar).focus();
  });
}

/**
 * Reemplaza prompt(): el texto ingresado, o null si se cancela (Escape, click
 * afuera o botón cancelar). textoConfirmar es el texto del botón de confirmar
 * (el llamador ya tiene a mano el data-i18n del botón que abrió el modal, ej. "Guardar").
 */
export function modalPrompt(mensaje, textoConfirmar) {
  return abrirModal({ mensaje, conInput: true, textoConfirmar, claseConfirmar: "modal-btn-confirmar" });
}

/**
 * Reemplaza confirm(): true si se confirma, false si se cancela.
 * peligroso=true tiñe el botón de confirmar en rojo (para acciones destructivas, ej. borrar).
 */
export function modalConfirmar(mensaje, textoConfirmar, { peligroso = false } = {}) {
  const claseConfirmar = peligroso ? "modal-btn-peligro" : "modal-btn-confirmar";
  return abrirModal({ mensaje, conInput: false, textoConfirmar, claseConfirmar });
}
