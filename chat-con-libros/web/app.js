'use strict';

// Todo el texto que viene del servidor se inserta con textContent: nada se interpreta como HTML.
const MAX_TURNOS = 3;
const API = 'api.php';

const selLibro = document.getElementById('libro');
const casillaIa = document.getElementById('usar-ia');
const notaIa = document.getElementById('nota-ia');
const conversacion = document.getElementById('conversacion');
const errorCaja = document.getElementById('error');
const formulario = document.getElementById('formulario');
const entrada = document.getElementById('pregunta');
const boton = document.getElementById('enviar');

let historial = [];

function mostrarError(texto) {
  errorCaja.textContent = texto;
  errorCaja.hidden = !texto;
}

function elemento(etiqueta, clase, texto) {
  const e = document.createElement(etiqueta);
  if (clase) e.className = clase;
  if (texto !== undefined) e.textContent = texto;
  return e;
}

function agregarUsuario(texto) {
  const caja = elemento('div', 'msg usuario');
  caja.appendChild(elemento('p', 'texto', texto));
  conversacion.appendChild(caja);
  caja.scrollIntoView({ block: 'end' });
}

const ETIQUETAS = {
  reglas: 'Respuesta por reglas',
  busqueda: 'Pasajes del libro, sin IA',
  ia: 'Redactada con IA a partir de pasajes del libro',
};

function agregarLibro(r) {
  const caja = elemento('div', 'msg libro' + (r.aviso ? ' aviso-caja' : ''));
  if (r.aviso) caja.appendChild(elemento('p', 'aviso', r.aviso));
  caja.appendChild(elemento('p', 'texto', r.texto));
  if (Array.isArray(r.citas) && r.citas.length > 0) {
    const detalles = elemento('details');
    detalles.appendChild(elemento('summary', '', 'Pasajes usados (' + r.citas.length + ')'));
    for (const c of r.citas) {
      const cita = elemento('blockquote');
      cita.appendChild(elemento('span', 'cap', c.capitulo));
      cita.appendChild(document.createTextNode(c.texto));
      detalles.appendChild(cita);
    }
    caja.appendChild(detalles);
  }
  caja.appendChild(elemento('span', 'etiqueta', ETIQUETAS[r.modo] || ''));
  conversacion.appendChild(caja);
  caja.scrollIntoView({ block: 'end' });
}

function reiniciar() {
  historial = [];
  conversacion.replaceChildren();
  mostrarError('');
  const opcion = selLibro.selectedOptions[0];
  const titulo = opcion ? opcion.dataset.titulo : 'el libro';
  agregarLibro({
    modo: 'reglas',
    texto: 'Hola. Estás hablando con «' + titulo + '». Pregúntame lo que quieras sobre el libro: te respondo con sus pasajes.',
    citas: [],
  });
}

async function cargar() {
  try {
    const r = await fetch(API + '?accion=libros');
    const datos = await r.json();
    if (!r.ok) throw new Error(datos.error || 'No pude cargar los libros.');
    for (const l of datos.libros) {
      const o = document.createElement('option');
      o.value = l.id;
      o.textContent = l.titulo + ' — ' + l.autor;
      o.dataset.titulo = l.titulo;
      selLibro.appendChild(o);
    }
    if (datos.libros.length === 0) {
      mostrarError('No hay libros: pon un .txt en ejemplos/ o en mis-libros/.');
      boton.disabled = true;
    }
    if (datos.ia.disponible) {
      notaIa.textContent = 'Al usarla se envían tu pregunta, los últimos turnos y los pasajes encontrados a la API de Anthropic (no el libro entero).';
    } else {
      casillaIa.disabled = true;
      notaIa.textContent = 'No disponible. ' + (datos.ia.motivo || '');
    }
    reiniciar();
  } catch (e) {
    mostrarError(e.message || 'No pude cargar los libros.');
    boton.disabled = true;
  }
}

formulario.addEventListener('submit', async (ev) => {
  ev.preventDefault();
  const pregunta = entrada.value.trim();
  if (!pregunta) return;
  mostrarError('');
  agregarUsuario(pregunta);
  entrada.value = '';
  boton.disabled = true;
  boton.textContent = 'Buscando…';
  try {
    const r = await fetch(API + '?accion=preguntar', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        libro: selLibro.value,
        pregunta,
        modo: casillaIa.checked ? 'ia' : 'sin-ia',
        historial,
      }),
    });
    const datos = await r.json();
    if (!r.ok) throw new Error(datos.error || 'Algo salió mal.');
    agregarLibro(datos);
    if (datos.modo !== 'reglas') {
      historial.push({ pregunta, respuesta: datos.texto });
      historial = historial.slice(-MAX_TURNOS);
    }
  } catch (e) {
    mostrarError(e.message || 'Algo salió mal.');
  } finally {
    boton.disabled = false;
    boton.textContent = 'Preguntar';
    entrada.focus();
  }
});

selLibro.addEventListener('change', reiniciar);
cargar();
