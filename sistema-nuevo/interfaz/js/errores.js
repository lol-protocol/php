import { t, tieneTraduccion } from "./idioma.js";

/**
 * El texto de un error de la API en el idioma de la interfaz. El backend manda `error` (en español,
 * para quien lee la respuesta a mano) y un `codigo` estable (api_error() en ayudantes.php). Si la
 * interfaz tiene la traducción `err_<codigo>` se usa esa; si no (un código nuevo sin traducir, o un
 * servidor viejo que no manda código) se muestra el texto del servidor, y sin nada de eso, `fallback`.
 */
/**
 * Si el error es de una función que el servidor tiene apagada con los interruptores (api/interruptores.php): no es un
 * fallo de carga, la sección simplemente no existe ahora. `interruptores_ilegibles` no entra: eso sí es un fallo.
 */
export function esFuncionApagada(err) {
  return err?.codigo === "funcion_apagada" || err?.codigo === "funcion_no_disponible";
}

export function mensajeDeError(data, fallback) {
  const clave = data?.codigo ? `err_${data.codigo}` : null;
  if (clave && tieneTraduccion(clave)) return t(clave);
  return data?.error || fallback;
}
