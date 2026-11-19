/*
 * El panel de los ajustes de la MilkDrop: elegir uno, editar cada parámetro en vivo,
 * guardar los propios, borrarlos, exportarlos a un JSON e importar los de otra persona, y
 * fijarlos para que no cambien solos al empezar cada sección. Solo navegador.
 *
 * PanelVisual.crear(app, vis) recibe lo que necesita de la aplicación ($, avisar,
 * descargar) y el visualizador.
 */
(function (raiz) {
  'use strict';

  const VP = raiz.VisualPuro;
  const Al = raiz.Almacen;

  function crear(app, vis) {
    const $ = app.$;
    const caja = $('panel-visual');
    const sel = $('preset');
    const controles = $('preset-controles');
    vis.presetsUsuario = Al.cargarPresets().map(VP.validar).filter(Boolean);

    const sliders = {};
    for (const k of VP.CLAVES_NUMERICAS) {
      const lim = VP.LIMITES[k];
      const lab = document.createElement('label');
      lab.textContent = lim.etiqueta + ' ';
      const v = document.createElement('span');
      const inp = document.createElement('input');
      inp.type = 'range';
      inp.min = lim.min;
      inp.max = lim.max;
      inp.step = lim.paso;
      inp.id = 'preset-' + k;
      inp.addEventListener('input', function () {
        vis.editarPreset(k, inp.value);
        v.textContent = Number(inp.value).toFixed(3);
      });
      lab.append(v, inp);
      controles.append(lab);
      sliders[k] = { inp: inp, v: v };
    }
    const labOnda = document.createElement('label');
    labOnda.textContent = 'Forma de la onda ';
    const selOnda = document.createElement('select');
    selOnda.id = 'preset-onda';
    VP.FORMAS_ONDA.forEach(function (n, i) {
      const o = document.createElement('option');
      o.value = i;
      o.textContent = n;
      selOnda.append(o);
    });
    selOnda.addEventListener('change', function () { vis.editarPreset('onda', selOnda.value); });
    labOnda.append(selOnda);
    const labCal = document.createElement('label');
    const chkCal = document.createElement('input');
    chkCal.type = 'checkbox';
    chkCal.id = 'preset-caleido';
    chkCal.addEventListener('change', function () { vis.editarPreset('caleido', chkCal.checked ? 1 : 0); });
    labCal.append(chkCal, ' espejo (caleidoscopio)');
    controles.append(labOnda, labCal);

    function mostrarValores() {
      const p = vis.presetEnUso();
      for (const k of VP.CLAVES_NUMERICAS) {
        sliders[k].inp.value = p[k];
        sliders[k].v.textContent = Number(p[k]).toFixed(3);
      }
      selOnda.value = String(p.onda);
      chkCal.checked = p.caleido > 0.5;
      sel.value = p.nombre;
    }

    function llenarLista() {
      sel.textContent = '';
      const grupo = function (titulo, lista) {
        if (!lista.length) return;
        const g = document.createElement('optgroup');
        g.label = titulo;
        for (const p of lista) {
          const o = document.createElement('option');
          o.value = p.nombre;
          o.textContent = p.nombre;
          g.append(o);
        }
        sel.append(g);
      };
      grupo('De fábrica', VP.PRESETS);
      grupo('Míos', vis.presetsUsuario);
      mostrarValores();
    }

    sel.addEventListener('change', function () {
      const p = vis.listaPresets().find(function (x) { return x.nombre === sel.value; });
      if (p) {
        vis.ponerPreset(p, vis.fijo);
        mostrarValores();
      }
    });
    $('preset-fijo').addEventListener('change', function (e) { vis.fijo = e.target.checked; });

    function guardarPropios() {
      Al.guardarPresets(vis.presetsUsuario);
    }

    $('preset-guardar').addEventListener('click', function () {
      const nombre = (raiz.prompt('Nombre del ajuste:', vis.presetEnUso().nombre) || '').trim();
      if (!nombre) return;
      const p = VP.validar(Object.assign(vis.presetEnUso(), { nombre: nombre }));
      if (!p) return;
      // si ya hay uno propio con ese nombre, se reemplaza; los de fábrica no se pisan
      if (VP.PRESETS.some(function (x) { return x.nombre === p.nombre; })) p.nombre += ' (mío)';
      vis.presetsUsuario = vis.presetsUsuario.filter(function (x) { return x.nombre !== p.nombre; }).concat([p]);
      guardarPropios();
      vis.ponerPreset(p, vis.fijo);
      llenarLista();
      app.avisar('Ajuste «' + p.nombre + '» guardado.');
    });

    $('preset-borrar').addEventListener('click', function () {
      const p = vis.presetEnUso();
      if (!vis.presetsUsuario.some(function (x) { return x.nombre === p.nombre; })) {
        app.avisar('Los ajustes de fábrica no se borran.');
        return;
      }
      vis.presetsUsuario = vis.presetsUsuario.filter(function (x) { return x.nombre !== p.nombre; });
      guardarPropios();
      vis.ponerPreset(VP.PRESETS[0], vis.fijo);
      llenarLista();
    });

    $('preset-exportar').addEventListener('click', function () {
      const lista = vis.presetsUsuario.length ? vis.presetsUsuario : [vis.presetEnUso()];
      app.descargar(new Blob([VP.serializar(lista)], { type: 'application/json' }), 'musica-infinita-ajustes.json');
    });

    $('preset-importar').addEventListener('change', async function (e) {
      const f = e.target.files[0];
      e.target.value = '';
      if (!f) return;
      try {
        const r = VP.importar(await f.text());
        for (const p of r.presets) {
          if (VP.PRESETS.some(function (x) { return x.nombre === p.nombre; })) p.nombre += ' (mío)';
          vis.presetsUsuario = vis.presetsUsuario.filter(function (x) { return x.nombre !== p.nombre; }).concat([p]);
        }
        guardarPropios();
        llenarLista();
        app.avisar(r.presets.length + ' ajustes importados' + (r.rechazados ? ', ' + r.rechazados + ' descartados por inválidos' : '') + '.');
      } catch (err) {
        app.avisar('No se pudo importar: ' + err.message);
      }
    });

    // los cambios automáticos (al empezar cada sección) se reflejan en el panel
    vis.alCambiarPreset = mostrarValores;
    llenarLista();
    caja.hidden = false;
    return { actualizar: llenarLista };
  }

  raiz.PanelVisual = { crear: crear };
})(window);
