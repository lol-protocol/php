import { el } from "./nucleo.js";
import { t } from "./idioma.js";
import { guardarNotaConDebounce } from "./notas.js";

const ESTADO_NOTA = {
  guardando: { text: "⏳", cls: "note-status--pending" },
  guardado: { text: "✓", cls: "note-status--ok" },
  error: { text: "⚠", cls: "note-status--error" },
};

export function buildNoteBlock(item) {
  const textarea = el("textarea", {
    class: "note-textarea",
    rows: "2",
    placeholder: t("note_placeholder"),
  });
  textarea.value = item.note || "";

  const status = el("span", { class: "note-status" });
  let ocultarTimer = null;

  textarea.addEventListener("input", () => {
    clearTimeout(ocultarTimer);
    guardarNotaConDebounce(item.id, textarea.value, (estado) => {
      const { text, cls } = ESTADO_NOTA[estado];
      status.textContent = text;
      status.className = `note-status ${cls}`;
      status.title = t(`note_status_${estado}`);
      if (estado === "guardado") {
        ocultarTimer = setTimeout(() => { status.textContent = ""; status.title = ""; }, 2000);
      }
    });
  });

  return el("div", { class: "note-block" }, [
    el("span", { class: "note-icon", text: "📝" }),
    textarea,
    status,
  ]);
}
