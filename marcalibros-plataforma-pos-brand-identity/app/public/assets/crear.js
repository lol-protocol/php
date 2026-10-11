// Pantalla de recorte. Sin JavaScript el formulario sigue funcionando: se recorta el centro.
//
// La cuenta del recorte es la misma que hace el servidor (php/src/ImageNormalizer.php),
// para que la franja que se ve aquí sea la que se guarda.
(() => {
  'use strict';

  const form = document.querySelector('form.formulario');
  if (!form) return;

  const RATIO = Number(form.dataset.ratio);
  const MAX_BYTES = Number(form.dataset.maxBytes);
  const MAX_MB = Number(form.dataset.maxMb);
  const MIN_WIDTH = Number(form.dataset.minWidth);
  const MAX_ZOOM = Number(form.dataset.maxZoom);

  const $ = (id) => document.getElementById(id);
  const fileInput = $('imagen');
  const sourceRadios = Array.from(form.querySelectorAll('input[name="fuente"]'));
  const landscapeRadios = Array.from(form.querySelectorAll('input[name="paisaje"]'));
  const optionBlocks = Array.from(form.querySelectorAll('[data-opcion]'));
  const section = $('encuadre');
  const noImage = $('sin-imagen');
  const stage = $('lienzo');
  const stageImg = $('origen');
  const frame = $('marco');
  const resultBox = $('resultado');
  const resultImg = $('resultado-img');
  const zoomInput = $('zoom');
  const xInput = $('x');
  const yInput = $('y');
  const localError = $('error-local');
  const cropNotice = $('aviso-recorte');
  const submit = $('crear');

  let blobUrl = null;
  let size = null; // {w, h} de la imagen elegida; null si no hay imagen lista
  let lastZoom = Number(zoomInput.value) || 1;

  const clamp = (n, lo, hi) => Math.min(hi, Math.max(lo, n));
  const number = (input, fallback) => {
    const n = Number(input.value);
    return Number.isFinite(n) ? n : fallback;
  };

  /** Igual que ImageNormalizer::cropBox: franja 7:1 en píxeles de la imagen original. */
  function cropBox(sw, sh, zoom, ox, oy) {
    let bw;
    if (sw >= RATIO * sh) {
      bw = RATIO * sh;
    } else {
      bw = sw;
    }
    const w = Math.max(1, Math.min(sw, Math.round(bw / zoom)));
    const h = Math.max(1, Math.min(sh, Math.round(w / RATIO)));
    return { w, h, x: Math.round((sw - w) * ox), y: Math.round((sh - h) * oy) };
  }

  const currentBox = (zoom = number(zoomInput, 1)) =>
    cropBox(size.w, size.h, zoom, number(xInput, 0.5), number(yInput, 0.5));

  function showError(message) {
    localError.textContent = message;
    localError.hidden = message === '';
  }

  function render() {
    if (!size) return;
    const box = currentBox();
    const pct = (n) => `${n * 100}%`;

    frame.style.left = pct(box.x / size.w);
    frame.style.top = pct(box.y / size.h);
    frame.style.width = pct(box.w / size.w);
    frame.style.height = pct(box.h / size.h);

    // La franja de resultado: la imagen entera escalada para que el recorte llene el ancho.
    resultImg.style.width = pct(size.w / box.w);
    resultImg.style.transform = `translate(${-(box.x / size.w) * 100}%, ${-(box.y / size.h) * 100}%)`;

    // Si el recorte ocupa todo el ancho (o todo el alto), mover en esa dirección no hace nada.
    xInput.disabled = size.w - box.w <= 0;
    yInput.disabled = size.h - box.h <= 0;

    if (box.w < MIN_WIDTH) {
      cropNotice.textContent = `La zona elegida mide ${box.w} px de ancho y hacen falta al menos ${MIN_WIDTH} para que no salga borrosa. Baja el acercamiento o elige una imagen más grande.`;
      cropNotice.hidden = false;
    } else {
      cropNotice.hidden = true;
    }
  }

  function resetCrop() {
    zoomInput.value = '1';
    xInput.value = '0.5';
    yInput.value = '0.5';
    lastZoom = 1;
  }

  function clearImage() {
    size = null;
    section.hidden = true;
    noImage.hidden = false;
    stageImg.removeAttribute('src');
    resultImg.removeAttribute('src');
    if (blobUrl) {
      URL.revokeObjectURL(blobUrl);
      blobUrl = null;
    }
  }

  function loadImage(url, isBlob) {
    clearImage();
    if (isBlob) blobUrl = url;
    const probe = new Image();
    probe.onload = () => {
      if (stageImg.dataset.pending !== url) return; // se eligió otra imagen mientras cargaba
      size = { w: probe.naturalWidth, h: probe.naturalHeight };
      stageImg.src = url;
      resultImg.src = url;
      resetCrop();
      section.hidden = false;
      noImage.hidden = true;
      render();
    };
    probe.onerror = () => {
      if (stageImg.dataset.pending !== url) return;
      showError('Tu navegador no puede mostrar esta imagen, así que no podrás mover el marco. Si sigues, se usará el centro de la imagen.');
    };
    stageImg.dataset.pending = url;
    probe.src = url;
  }

  // ------------------------------------------------------------ elegir la imagen

  const currentSource = () => (sourceRadios.find((r) => r.checked) || {}).value || 'subida';

  function applySource() {
    const source = currentSource();
    for (const block of optionBlocks) block.hidden = block.dataset.opcion !== source;
    fileInput.disabled = source !== 'subida';
    for (const radio of landscapeRadios) radio.disabled = source !== 'paisaje';
    showError('');
    if (source === 'paisaje') {
      loadSelectedLandscape();
    } else if (fileInput.files && fileInput.files[0]) {
      loadUpload();
    } else {
      clearImage();
    }
  }

  function loadSelectedLandscape() {
    const chosen = landscapeRadios.find((r) => r.checked) || landscapeRadios[0];
    if (!chosen) return;
    chosen.checked = true;
    const thumb = chosen.parentElement.querySelector('img[data-full]');
    if (thumb) loadImage(thumb.dataset.full, false);
  }

  function loadUpload() {
    showError('');
    const file = fileInput.files && fileInput.files[0];
    if (!file) {
      clearImage();
      return;
    }
    if (file.size > MAX_BYTES) {
      fileInput.value = '';
      clearImage();
      showError(`La imagen pesa ${(file.size / 1048576).toFixed(1).replace('.', ',')} MB y el máximo es ${MAX_MB} MB.`);
      return;
    }
    if (file.type !== '' && !/^image\/(jpeg|png|webp)$/.test(file.type)) {
      fileInput.value = '';
      clearImage();
      showError('Ese archivo no es una imagen JPG, PNG o WebP.');
      return;
    }
    loadImage(URL.createObjectURL(file), true);
  }

  for (const radio of sourceRadios) radio.addEventListener('change', applySource);
  for (const radio of landscapeRadios) radio.addEventListener('change', loadSelectedLandscape);
  fileInput.addEventListener('change', loadUpload);

  // ------------------------------------------------------------------- el marco

  function setOffsets(ox, oy) {
    xInput.value = clamp(ox, 0, 1).toFixed(3);
    yInput.value = clamp(oy, 0, 1).toFixed(3);
  }

  /** Mueve el marco para que su esquina quede en (x, y), en píxeles de la imagen original. */
  function moveTo(x, y, box) {
    const freeX = size.w - box.w;
    const freeY = size.h - box.h;
    setOffsets(freeX > 0 ? x / freeX : number(xInput, 0.5), freeY > 0 ? y / freeY : number(yInput, 0.5));
    render();
  }

  let drag = null;

  function startDrag(event, box) {
    drag = {
      id: event.pointerId,
      startX: event.clientX,
      startY: event.clientY,
      boxX: box.x,
      boxY: box.y,
      box,
      scale: stage.getBoundingClientRect().width / size.w,
    };
    frame.setPointerCapture(event.pointerId);
    frame.classList.add('arrastrando');
  }

  frame.addEventListener('pointerdown', (event) => {
    if (!size || event.button > 0) return;
    event.preventDefault();
    startDrag(event, currentBox());
    frame.focus({ preventScroll: true });
  });

  stage.addEventListener('pointerdown', (event) => {
    // Un toque fuera del marco lo centra ahí, y sigue como un arrastre.
    if (!size || event.target === frame || event.button > 0) return;
    event.preventDefault();
    const rect = stage.getBoundingClientRect();
    const scale = rect.width / size.w;
    const box = currentBox();
    const x = clamp((event.clientX - rect.left) / scale - box.w / 2, 0, size.w - box.w);
    const y = clamp((event.clientY - rect.top) / scale - box.h / 2, 0, size.h - box.h);
    moveTo(x, y, box);
    startDrag(event, currentBox());
  });

  frame.addEventListener('pointermove', (event) => {
    if (!drag || event.pointerId !== drag.id) return;
    const { box } = drag;
    const x = clamp(drag.boxX + (event.clientX - drag.startX) / drag.scale, 0, size.w - box.w);
    const y = clamp(drag.boxY + (event.clientY - drag.startY) / drag.scale, 0, size.h - box.h);
    moveTo(x, y, box);
  });

  const endDrag = (event) => {
    if (!drag || event.pointerId !== drag.id) return;
    drag = null;
    frame.classList.remove('arrastrando');
  };
  frame.addEventListener('pointerup', endDrag);
  frame.addEventListener('pointercancel', endDrag);

  frame.addEventListener('keydown', (event) => {
    if (!size) return;
    const step = event.shiftKey ? 0.05 : 0.01;
    const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
    const move = moves[event.key];
    if (!move) return;
    event.preventDefault();
    setOffsets(number(xInput, 0.5) + move[0], number(yInput, 0.5) + move[1]);
    render();
  });

  xInput.addEventListener('input', render);
  yInput.addEventListener('input', render);

  // Al acercar o alejar, el centro del marco se queda donde estaba.
  zoomInput.addEventListener('input', () => {
    if (!size) return;
    const next = clamp(number(zoomInput, 1), 1, MAX_ZOOM);
    const before = cropBox(size.w, size.h, lastZoom, number(xInput, 0.5), number(yInput, 0.5));
    const after = cropBox(size.w, size.h, next, 0.5, 0.5);
    const cx = before.x + before.w / 2;
    const cy = before.y + before.h / 2;
    const freeX = size.w - after.w;
    const freeY = size.h - after.h;
    setOffsets(freeX > 0 ? (cx - after.w / 2) / freeX : 0.5, freeY > 0 ? (cy - after.h / 2) / freeY : 0.5);
    lastZoom = next;
    render();
  });

  // --------------------------------------------------------------------- enviar

  form.addEventListener('submit', (event) => {
    if (currentSource() === 'subida' && !(fileInput.files && fileInput.files[0])) {
      event.preventDefault();
      showError('Elige una imagen para subir.');
      fileInput.focus();
      return;
    }
    if (size && currentBox().w < MIN_WIDTH) {
      // El servidor también lo rechazaría, pero entonces habría que volver a elegir el archivo.
      event.preventDefault();
      cropNotice.hidden = false;
      zoomInput.focus();
      return;
    }
    submit.disabled = true;
    submit.textContent = 'Creando…';
  });

  // Al volver con el botón "atrás" el botón no debe quedar bloqueado.
  window.addEventListener('pageshow', () => {
    submit.disabled = false;
    submit.textContent = 'Crear marcalibro';
  });

  const serverError = $('error-servidor');
  if (serverError) {
    // La subida no se puede reponer sola: la persona debe elegir el archivo otra vez.
    serverError.setAttribute('tabindex', '-1');
    serverError.focus({ preventScroll: false });
  }

  section.hidden = true;
  noImage.hidden = false;
  applySource();
})();
