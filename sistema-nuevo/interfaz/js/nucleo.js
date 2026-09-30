// Backend PHP (API): mismo host donde se sirve este panel (no hardcodea
// "localhost" -- si el panel se abre desde otra IP/hostname, la API se pide
// ahí también, no en el navegador del cliente). El puerto sí queda fijo: acá
// no hay build step ni variables de entorno del lado del navegador, así que
// si el backend corre en otro puerto hay que cambiar esto a mano.
export const API_BASE = `http://${window.location.hostname}:8000`;

export const ACTION_ICONS = {
  login: "🔐",
  password_reset: "🔑",
  search: "🔎",
  view_product: "👀",
  api_call: "⚙️",
  profile_update: "📝",
  add_to_cart: "🛒",
  checkout_start: "🧾",
  payment: "💳",
  refund: "💸",
  review_submit: "⭐",
  support_ticket: "🆘",
  file_upload: "📎",
  logout: "🚪",
};

export const PER_PAGE = 20;

export const state = {
  users: [],
  groups: null,
  actionTypes: [],
  selectedUserId: null,
  page: 1,
  lang: "es",
  lastTimeline: null,
  lastAlerts: null,
  lastKpis: null,
  username: null,
  csrfToken: null,
};

export const el = (tag, props = {}, children = []) => {
  const node = document.createElement(tag);
  Object.entries(props).forEach(([key, value]) => {
    if (key === "class") {
      node.className = value;
    } else if (key === "text") {
      node.textContent = value;
    } else {
      node.setAttribute(key, value);
    }
  });
  children.forEach((child) => node.appendChild(child));
  return node;
};

export const debounce = (fn, wait) => {
  let timer = null;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), wait);
  };
};

/**
 * Ejecuta fn() capturando cualquier error: lo loguea y devuelve null en vez
 * de propagarlo. Común a los módulos que hacen fetch/postJson contra el
 * backend y sólo necesitan no romper la UI si la llamada falla (filtros,
 * configuración de alertas...). onError, si se pasa, recibe el error antes
 * de loguearlo -- para mostrar feedback visual además de loguear.
 */
export async function intentar(fn, mensajeError, onError = null) {
  try {
    return await fn();
  } catch (err) {
    console.error(mensajeError, err);
    onError?.(err);
    return null;
  }
}
