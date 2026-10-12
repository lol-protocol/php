// Lector de CSV (RFC 4180): campos entre comillas, comas y saltos de línea dentro de
// un campo, y "" como comilla escapada. Devuelve un objeto por fila con la cabecera
// como claves. Lo usa copyright-laws.js; sin DOM, así que lo prueba tests/csv.test.js.
export function parseCSV(text) {
  const rows = [];
  let row = [];
  let field = "";
  let quoted = false;

  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (quoted) {
      if (c === '"' && text[i + 1] === '"') {
        field += '"';
        i++;
      } else if (c === '"') {
        quoted = false;
      } else {
        field += c;
      }
    } else if (c === '"') {
      quoted = true;
    } else if (c === ",") {
      row.push(field);
      field = "";
    } else if (c === "\n" || c === "\r") {
      if (c === "\r" && text[i + 1] === "\n") i++;
      row.push(field);
      field = "";
      rows.push(row);
      row = [];
    } else {
      field += c;
    }
  }
  if (quoted) throw new Error("CSV mal formado: comilla sin cerrar");
  if (field !== "" || row.length > 0) {
    row.push(field);
    rows.push(row);
  }

  const lines = rows.filter((r) => !(r.length === 1 && r[0] === ""));
  if (lines.length === 0) return [];
  const headers = lines[0].map((h) => h.trim());
  return lines.slice(1).map((cols, n) => {
    if (cols.length !== headers.length) {
      throw new Error(
        `CSV mal formado: la fila ${n + 2} tiene ${cols.length} campos y la cabecera ${headers.length}`
      );
    }
    const obj = {};
    headers.forEach((h, i) => (obj[h] = cols[i].trim()));
    return obj;
  });
}
