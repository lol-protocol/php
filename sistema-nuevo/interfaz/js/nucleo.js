// Backend PHP (API). Cambiar si se corre en otro host/puerto.
export const API_BASE = "http://localhost:8000";

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

export const GENDER_LABELS = { M: "Masculino", F: "Femenino", O: "Otro" };

export const state = {
  users: [],
  groups: null,
  selectedUserId: null,
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
