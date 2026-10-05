import { t, tieneTraduccion } from "./idioma.js";

/**
 * El texto de un error de la API en el idioma de la interfaz. El backend manda `error` (en español,
 * para quien lee la respuesta a mano) y un `codigo` estable (api_error() en ayudantes.php). Si la
 * interfaz tiene la traducción `err_<codigo>` se usa esa; si no (un código nuevo sin traducir, o un
 * servidor viejo que no manda código) se muestra el texto del servidor, y sin nada de eso, `fallback`.
 */
export function mensajeDeError(data, fallback) {
  const clave = data?.codigo ? `err_${data.codigo}` : null;
  if (clave && tieneTraduccion(clave)) return t(clave);
  return data?.error || fallback;
}
