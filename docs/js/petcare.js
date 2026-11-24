/* PetCare · interacción de la interfaz (diálogos, confirmaciones, avisos y formularios dinámicos).
   La lógica de negocio vive en el servidor (Laravel); este archivo solo mejora la experiencia. */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

  /* ---------- Diálogos ---------- */
  function abrir(id, origen) {
    const d = document.getElementById(id);
    if (!d) return;
    $$('dialog[open]').forEach((x) => x !== d && x.close());
    if (id === 'dlg-cita' && origen) prepararCita(origen.dataset);
    if (id === 'dlg-rechazar' && origen) {
      $('#form-rechazar').action = origen.dataset.rechazarUrl;
      $('#rz-url').value = origen.dataset.rechazarUrl;
      $('#rechazar-info').textContent = origen.dataset.rechazarInfo || '';
    }
    if (id === 'dlg-rechazar-pago' && origen) {
      $('#form-rpago').action = origen.dataset.rpagoUrl;
      $('#rp-url').value = origen.dataset.rpagoUrl;
      $('#rpago-info').textContent = origen.dataset.rpagoInfo || '';
    }
    if (id === 'dlg-calificar' && origen) {
      $('#form-calificar').action = origen.dataset.calificarUrl;
      $('#cl-url').value = origen.dataset.calificarUrl;
      $('#calificar-info').textContent = origen.dataset.calificarInfo || '';
    }
    if (!d.open) d.showModal();
  }
  document.addEventListener('click', (e) => {
    const o = e.target.closest('[data-open]');
    if (o) { e.preventDefault(); abrir(o.dataset.open, o); return; }
    const c = e.target.closest('[data-close]');
    if (c) { c.closest('dialog')?.close(); return; }
    if (e.target.tagName === 'DIALOG') e.target.close(); // clic en el fondo
  });
  // Si el servidor devolvió errores de un formulario de diálogo, se vuelve a abrir ese diálogo.
  const reabrir = document.body.dataset.reabrir;
  if (reabrir && document.getElementById(reabrir)) abrir(reabrir);

  /* ---------- Confirmación antes de acciones destructivas ---------- */
  let pendiente = null;
  document.addEventListener('submit', (e) => {
    const f = e.target;
    if (!f.dataset.confirm || f.dataset.confirmado) return;
    e.preventDefault();
    pendiente = f;
    $('#dlg-confirm-msg').textContent = f.dataset.confirm;
    $('#dlg-confirm .md-h h2').textContent = f.dataset.confirmTitle || 'Confirmar';
    $('#dlg-confirm-ok').textContent = f.dataset.confirmBtn || 'Confirmar';
    abrir('dlg-confirm');
  });
  $('#dlg-confirm-ok')?.addEventListener('click', () => {
    if (!pendiente) return;
    pendiente.dataset.confirmado = '1';
    pendiente.requestSubmit ? pendiente.requestSubmit() : pendiente.submit();
  });

  /* ---------- Avisos (toast) ---------- */
  $$('#toast .toast').forEach((t) => setTimeout(() => { t.classList.add('hide'); setTimeout(() => t.remove(), 400); }, 4200));

  /* ---------- Varios ---------- */
  $$('[data-autosubmit]').forEach((s) => s.addEventListener('change', () => s.form.submit()));
  $$('[data-print]').forEach((b) => b.addEventListener('click', () => window.print()));
  $$('[data-demo]').forEach((b) => b.addEventListener('click', () => {
    const [em, pw] = b.dataset.demo.split('|');
    $('#em').value = em; $('#pw').value = pw;
  }));

  /* ---------- Contraseña segura: barra de fuerza y requisitos ---------- */
  const REQS = { largo: (v) => v.length >= 8, may: (v) => /[A-ZÁÉÍÓÚÑ]/.test(v), min: (v) => /[a-záéíóúñ]/.test(v), num: (v) => /\d/.test(v), sim: (v) => /[^\p{L}\d\s]/u.test(v) };
  $$('[data-fuerza]').forEach((inp) => {
    const barra = inp.parentElement.querySelector('.fuerza');
    const lista = inp.parentElement.querySelector('.reqs');
    const pintar = () => {
      const v = inp.value;
      let n = 0;
      Object.entries(REQS).forEach(([k, ok]) => {
        const cumple = ok(v);
        if (cumple) n++;
        lista?.querySelector(`[data-r="${k}"]`)?.classList.toggle('ok', cumple);
      });
      if (barra) { barra.dataset.n = v ? n : 0; barra.firstElementChild.style.width = (v ? n * 20 : 0) + '%'; }
    };
    inp.addEventListener('input', pintar);
    pintar();
  });

  /* ---------- Celular: ejemplo según el país y solo dígitos ---------- */
  $$('[data-tel]').forEach((t) => {
    const sel = t.querySelector('select'), num = t.querySelector('input');
    sel.addEventListener('change', () => { num.placeholder = sel.selectedOptions[0].dataset.ejemplo || ''; });
    num.addEventListener('input', () => { num.value = num.value.replace(/[^\d]/g, ''); });
    // Validación según el país: Bolivia 8 dígitos que empiezan con 6 o 7, etc.
    num.pcValidar = (v) => {
      const o = sel.selectedOptions[0];
      if (!o?.dataset.movil) return '';
      return new RegExp('^' + o.dataset.movil + '$').test(v) ? '' : `No es un celular válido de ${o.dataset.pais}. Ejemplo: ${o.dataset.ejemplo}.`;
    };
    sel.addEventListener('change', () => { if (num.value) num.dispatchEvent(new Event('input', { bubbles: true })); });
  });
  $$('input.codigo').forEach((c) => c.addEventListener('input', () => { c.value = c.value.replace(/\D/g, '').slice(0, 6); }));

  /* ---------- Agendar cita: servicios según la veterinaria ---------- */
  const selVet = $('#cita-v'), selSrv = $('#cita-s');
  function serviciosDe(vetId, elegido) {
    if (!selVet || !selSrv) return;
    const mapa = JSON.parse(selVet.dataset.servicios || '{}');
    const lista = mapa[vetId] || [];
    selSrv.innerHTML = lista.map((s) => `<option${s === elegido ? ' selected' : ''}>${s}</option>`).join('')
      + `<option value="Otro…"${elegido === 'Otro…' ? ' selected' : ''}>Otro… (escribir)</option>`;
    selSrv.dispatchEvent(new Event('change'));
  }
  function prepararCita(d) {
    if (!selVet) return;
    if (d.citaMascota) $('#cita-m').value = d.citaMascota;
    if (d.citaVet && selVet.querySelector(`option[value="${d.citaVet}"]`)) selVet.value = d.citaVet;
    serviciosDe(selVet.value, d.citaServicio);
    if (d.citaNota !== undefined) $('#cita-n').value = d.citaNota || '';
    window.petcareCitaRefrescar?.();
  }
  if (selVet) {
    serviciosDe(selVet.value, selSrv.dataset.old);
    selVet.addEventListener('change', () => serviciosDe(selVet.value));
  }

  /* ---------- Cita: plan habilitado de la mascota y estimado de lo que paga el dueño ---------- */
  const fc = $('#fcita');
  if (fc) {
    const planes = JSON.parse(fc.dataset.planes || '{}');
    const est = $('#cita-est');
    const bs = (n) => 'Bs ' + Number(n).toLocaleString('es-BO', { minimumFractionDigits: Number(n) % 1 ? 2 : 0, maximumFractionDigits: 2 });
    let t = null;
    const pintarPlanes = () => {
      const activo = planes[$('#cita-m').value] ?? null;
      $$('.pc-op', fc).forEach((op) => {
        const id = op.dataset.plan === '' ? null : Number(op.dataset.plan);
        const es = id === activo;
        const r = op.querySelector('input');
        r.checked = es;
        op.classList.toggle('on', es);
        op.classList.toggle('off', !es);
        const e = op.querySelector('[data-estado]');
        if (e) e.textContent = es ? 'Tu plan activo' : 'No contratado';
      });
    };
    const estimar = () => {
      clearTimeout(t);
      t = setTimeout(() => {
        const q = new URLSearchParams({ mascota_id: $('#cita-m').value, veterinaria_id: $('#cita-v').value, servicio: $('#cita-s').value });
        fetch(`${fc.dataset.estimar}?${q}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
          .then((r) => r.json()).then((r) => {
            if (!r.ok) { est.innerHTML = ''; return; }
            const notas = (r.notas || []).map((n) => `<li>${n.replace(/</g, '&lt;')}</li>`).join('');
            est.innerHTML = r.precio === null
              ? `<div class="cov-card"><b>Precio a confirmar con la clínica</b>${notas ? `<ul class="cov-n">${notas}</ul>` : ''}</div>`
              : `<div class="cov-card ${r.cubierto > 0 ? 'ok' : ''}"><div class="cov-t"><span>Precio en la clínica <b>${bs(r.precio)}</b></span><span class="pc">${r.plan ? 'Tu plan cubre' : 'Sin plan'} <b>${bs(r.cubierto)}</b></span></div>
                 <div class="te-toca">Te toca pagar <b>${bs(r.pagar)}</b></div><small class="hint">Estimado del servicio. Los medicamentos que se usen se suman en la atención.</small>${notas ? `<ul class="cov-n">${notas}</ul>` : ''}</div>`;
          }).catch(() => { est.innerHTML = ''; });
      }, 200);
    };
    ['cita-m', 'cita-v', 'cita-s'].forEach((id) => $('#' + id).addEventListener('change', () => { pintarPlanes(); estimar(); }));
    pintarPlanes();
    estimar();
    // Al abrir el diálogo con otra mascota o veterinaria, recalcula.
    window.petcareCitaRefrescar = () => { pintarPlanes(); estimar(); };
  }

  /* ---------- Registrar atención: tarifario, productos, cobertura en vivo e historial ---------- */
  const fa = $('#fa');
  if (fa) {
    const precios = JSON.parse(fa.dataset.precios || '{}');
    const prev = JSON.parse(fa.dataset.preventivos || '[]');
    const ap = $('#ap'), as = $('#as'), costo = $('#acs'), aviso = $('#cov'), vacf = $('#vacf'), panel = $('#prev');
    const prods = $('#prods'), catalogo = JSON.parse(prods?.dataset.productos || '[]');
    const esc = (t) => String(t).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const bs = (n) => 'Bs ' + Number(n).toLocaleString('es-BO', { minimumFractionDigits: Number(n) % 1 ? 2 : 0, maximumFractionDigits: 2 });
    let ultimo = null, preset = null, timer = null, n = prods ? prods.children.length : 0;

    function precioSugerido() {
      const p = precios[as.value];
      if (costo.value === '' || costo.value === String(preset)) { costo.value = p ?? ''; }
      preset = p ?? null;
    }
    function filaProducto() {
      const opts = catalogo.map((p) => `<option value="${p.id}">${esc(p.nombre)} · ${bs(p.precio)}</option>`).join('');
      const div = document.createElement('div');
      div.className = 'prod-row';
      div.innerHTML = `<select class="in" name="productos[${n}][id]">${opts}</select><input class="in" name="productos[${n}][cantidad]" inputmode="decimal" value="1" aria-label="Cantidad"><button type="button" class="icb bad" data-quitar aria-label="Quitar"><svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>`;
      n++;
      prods.appendChild(div);
      calcular();
    }
    $('#add-prod')?.addEventListener('click', filaProducto);
    prods?.addEventListener('click', (e) => { if (e.target.closest('[data-quitar]')) { e.target.closest('.prod-row').remove(); calcular(); } });
    prods?.addEventListener('change', calcular);
    prods?.addEventListener('input', calcular);

    function pintar(r) {
      if (!r.ok) { aviso.innerHTML = ''; return; }
      const filas = r.lineas.map((l) => `<tr><td>${esc(l.nombre)}${l.cantidad !== 1 ? ' × ' + l.cantidad : ''}</td><td>${bs(l.subtotal)}</td><td>${l.cubierto ? '<span class="chip ok">Cubierto</span>' : '<span class="chip mut">No</span>'}</td></tr>`).join('');
      const notas = r.notas.map((t) => `<li>${esc(t)}</li>`).join('');
      aviso.innerHTML = `<div class="cov-card ${r.cubierto > 0 ? 'ok' : ''}">
        <div class="cov-h"><b>${r.plan ? esc(r.plan) + ' · ' + r.porcentaje + ' %' : 'Sin plan: atención particular'}</b>${r.disponible !== null ? `<span>Saldo de cobertura: ${bs(r.disponible)}</span>` : ''}</div>
        <table><tbody>${filas}</tbody></table>
        <div class="cov-t"><span>Total <b>${bs(r.total)}</b></span><span class="pc">PetCare cubre <b>${bs(r.cubierto)}</b></span><span class="du">Paga el dueño <b>${bs(r.dueno)}</b></span></div>
        ${notas ? `<ul class="cov-n">${notas}</ul>` : ''}</div>`;
    }
    function calcular() {
      vacf.hidden = !prev.includes(as.value);
      clearTimeout(timer);
      if (!ap.value) { aviso.innerHTML = ''; return; }
      timer = setTimeout(() => {
        const q = new URLSearchParams({ mascota_id: ap.value, servicio: as.value, servicio_otro: $('#aso')?.value || '', costo_servicio: costo.value || 0, fecha: $('#af').value, preexistente: $('#apre').checked ? 1 : 0 });
        $$('.prod-row', prods || document).forEach((row, i) => {
          q.append(`productos[${i}][id]`, row.querySelector('select').value);
          q.append(`productos[${i}][cantidad]`, row.querySelector('input').value || 0);
        });
        fetch(`${fa.dataset.cobertura}?${q}`, { headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' } })
          .then((r) => r.json()).then(pintar).catch(() => { aviso.innerHTML = ''; });
      }, 250);
      if (ap.value !== ultimo) {
        ultimo = ap.value;
        panel.innerHTML = '<p class="sub">Cargando historial…</p>';
        fetch(`${fa.dataset.historial}/${ap.value}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
          .then((r) => (r.ok ? r.text() : Promise.reject()))
          .then((html) => { panel.innerHTML = html; })
          .catch(() => { panel.innerHTML = '<p class="sub">No se pudo cargar el historial. Recarga la página para intentarlo de nuevo.</p>'; });
      }
    }
    as.addEventListener('change', () => { precioSugerido(); calcular(); });
    $('#aso')?.addEventListener('input', calcular);
    [ap, costo, $('#af'), $('#apre')].forEach((el) => el.addEventListener(el.tagName === 'INPUT' && el.type !== 'checkbox' ? 'input' : 'change', calcular));
    if (costo.value === '') precioSugerido(); else preset = precios[as.value] ?? null;
    calcular();
  }


  /* ---------- Pago de planes: monto según modalidad y adicional ---------- */
  const fp = $('#fpago');
  if (fp) {
    const montos = JSON.parse(fp.dataset.montos || '{}');
    const total = $('#sp-total');
    const actualizar = () => {
      if (!total) return;
      const mod = fp.querySelector('[name=modalidad]:checked')?.value || 'mensual';
      const grave = fp.querySelector('[name=cobertura_grave]')?.checked;
      const m = montos[mod]?.[grave ? 'con' : 'sin'];
      if (m !== undefined) total.textContent = 'Bs ' + Number(m).toLocaleString('es-BO', { minimumFractionDigits: m % 1 ? 2 : 0 });
      if (m !== undefined) $$('[data-mp-monto]', fp).forEach((x) => { x.textContent = total.textContent; });
    };
    if (total) { fp.addEventListener('change', actualizar); actualizar(); }
  }

  /* ---------- Formularios de suscripción: muestra el monto según plan y meses ---------- */
  const fs = $('#fsus');
  if (fs) {
    const precios = JSON.parse(fs.dataset.precios || '{}');
    const pinta = () => {
      const p = precios[fs.querySelector('[name=plan_suscripcion]:checked')?.value] || 0;
      const meses = fs.querySelector('[name=meses]:checked')?.value === '12' ? 10 : 1;
      $('#sus-total').textContent = 'Bs ' + (p * meses).toLocaleString('es-BO');
    };
    fs.addEventListener('change', pinta);
    pinta();
  }
})();

/* ---------- Reportes: diario / mensual / anual ---------- */
(() => {
  const f = document.getElementById('fper');
  if (!f) return;
  f.querySelectorAll('input[name=periodo]').forEach((r) => r.addEventListener('change', () => {
    f.querySelectorAll('.seg label').forEach((l) => l.classList.toggle('on', l.contains(r) && r.checked));
    f.querySelectorAll('[data-per]').forEach((i) => { const on = i.dataset.per === r.value; i.hidden = !on; i.disabled = !on; });
  }));
})();

/* ---------- Términos y condiciones en ventana flotante: se aceptan después de leer hasta el final ---------- */
(() => {
  document.querySelectorAll('dialog[id^=dlg-terminos-]').forEach((d) => {
    const sc = d.querySelector('.legal-scroll'), ok = d.querySelector('[data-terminos-ok]'), btn = d.querySelector('[data-terminos-aceptar]');
    const prog = d.querySelector('.legal-prog i'), hint = d.querySelector('[data-terminos-hint]');
    const revisar = () => {
      const max = sc.scrollHeight - sc.clientHeight;
      const pc = max <= 4 ? 100 : Math.min(100, (sc.scrollTop / max) * 100);
      prog.style.width = pc + '%';
      if (pc >= 97 && ok.disabled) { ok.disabled = false; hint.textContent = 'Marca la casilla para aceptar.'; }
    };
    sc.addEventListener('scroll', revisar, { passive: true });
    d.addEventListener('toggle', revisar);
    ok.addEventListener('change', () => { btn.disabled = !ok.checked; });
    btn.addEventListener('click', () => {
      document.querySelectorAll(`[data-terminos="${d.id}"]`).forEach((c) => {
        c.checked = true; c.dataset.leido = '1';
        c.dispatchEvent(new Event('change', { bubbles: true }));
      });
      d.close();
    });
  });
  // La casilla del formulario solo se marca después de leer los términos
  document.querySelectorAll('[data-terminos]').forEach((c) => {
    if (c.checked) c.dataset.leido = '1';
    c.addEventListener('click', (e) => {
      // Al hacer clic el navegador ya cambió «checked»: si todavía no se leyó, se revierte y se abre la ventana
      if (!c.dataset.leido && c.checked) { e.preventDefault(); c.checked = false; document.getElementById(c.dataset.terminos)?.showModal(); }
    });
  });
})();

/* ---------- Forma de pago: tarjeta con marca (simulación), QR con comprobante o WhatsApp ---------- */
(() => {
  const MARCAS = {
    visa: { nombre: 'Visa', largo: 16, cvv: 3, grupos: [4, 4, 4, 4], re: /^4/ },
    mastercard: { nombre: 'Mastercard', largo: 16, cvv: 3, grupos: [4, 4, 4, 4], re: /^(5[1-5]|2[2-7])/ },
    amex: { nombre: 'American Express', largo: 15, cvv: 4, grupos: [4, 6, 5], re: /^3[47]/ },
  };
  document.querySelectorAll('[data-mp]').forEach((mp) => {
    const form = mp.closest('form');
    const card = mp.querySelector('[data-tcard]');
    const num = mp.querySelector('[name=tarjeta_numero]'), nom = mp.querySelector('[name=tarjeta_nombre]');
    const ven = mp.querySelector('[name=tarjeta_vence]'), cvv = mp.querySelector('[name=tarjeta_cvv]');
    const marca = () => mp.querySelector('[name=tarjeta_marca]:checked')?.value || 'visa';
    const metodo = () => mp.querySelector('[name=metodo]:checked')?.value || 'tarjeta';
    const formato = (d, m) => { let out = [], i = 0; for (const g of MARCAS[m].grupos) { if (i >= d.length) break; out.push(d.slice(i, i + g)); i += g; } return out.join(' '); };
    const mascara = (m) => MARCAS[m].grupos.map((g) => '•'.repeat(g)).join(' ');

    const pintarMetodo = () => {
      const met = metodo();
      mp.querySelectorAll('.mp-tab').forEach((t) => t.classList.toggle('on', t.querySelector('input').checked));
      mp.querySelectorAll('[data-panel]').forEach((p) => {
        p.hidden = p.dataset.panel !== met;
        // Los campos de los paneles ocultos no se validan ni se envían
        p.querySelectorAll('input,select,textarea').forEach((i) => { i.disabled = p.hidden; });
      });
      form?.querySelectorAll('[data-btn-pago] [data-txt]').forEach((x) => { x.hidden = x.dataset.txt !== met; });
      const b = form?.querySelector('[data-btn-pago]');
      if (b) b.classList.toggle('wa-btn', met === 'whatsapp');
    };
    const pintarTarjeta = () => {
      const m = marca(), d = (num.value || '').replace(/\D/g, '');
      card.className = 'tcard mk-' + m + (card.classList.contains('flip') ? ' flip' : '');
      mp.querySelector('[data-tc-marca]').textContent = MARCAS[m].nombre;
      mp.querySelector('[data-tc-num]').textContent = d ? formato(d, m).padEnd(mascara(m).length, ' ') : mascara(m);
      mp.querySelector('[data-tc-nom]').textContent = (nom.value || 'NOMBRE APELLIDO').toUpperCase().slice(0, 26);
      mp.querySelector('[data-tc-ven]').textContent = ven.value || 'MM/AA';
      mp.querySelector('[data-tc-cvv]').textContent = cvv.value ? '•'.repeat(cvv.value.length) : '•'.repeat(MARCAS[m].cvv);
      num.maxLength = MARCAS[m].largo + MARCAS[m].grupos.length - 1;
      cvv.maxLength = MARCAS[m].cvv;
      cvv.placeholder = MARCAS[m].cvv === 4 ? '1234' : '123';
      mp.querySelectorAll('.mp-marca').forEach((l) => l.classList.toggle('on', l.querySelector('input').checked));
    };
    mp.addEventListener('change', (e) => {
      if (e.target.name === 'metodo') pintarMetodo();
      if (e.target.name === 'tarjeta_marca') { num.value = formato(num.value.replace(/\D/g, ''), marca()); pintarTarjeta(); }
    });
    num?.addEventListener('input', () => {
      let d = num.value.replace(/\D/g, '');
      // Detecta la marca por los primeros dígitos
      const det = Object.keys(MARCAS).find((k) => MARCAS[k].re.test(d));
      if (det && det !== marca()) { mp.querySelector(`[name=tarjeta_marca][value=${det}]`).checked = true; }
      d = d.slice(0, MARCAS[marca()].largo);
      num.value = formato(d, marca());
      pintarTarjeta();
    });
    nom?.addEventListener('input', () => { nom.value = nom.value.replace(/[^\p{L}\s.'-]/gu, ''); pintarTarjeta(); });
    ven?.addEventListener('input', () => { const v = ven.value.replace(/\D/g, '').slice(0, 4); ven.value = v.length > 2 ? v.slice(0, 2) + '/' + v.slice(2) : v; pintarTarjeta(); });
    cvv?.addEventListener('input', () => { cvv.value = cvv.value.replace(/\D/g, '').slice(0, MARCAS[marca()].cvv); pintarTarjeta(); });
    // Validaciones propias de la tarjeta (marca, largo, Luhn, vencimiento, CVV)
    const luhn = (d) => { let s = 0; for (let i = 0; i < d.length; i++) { let x = +d[d.length - 1 - i]; if (i % 2) { x *= 2; if (x > 9) x -= 9; } s += x; } return s % 10 === 0; };
    if (num) num.pcValidar = (v) => {
      const d = v.replace(/\D/g, ''), m = MARCAS[marca()];
      if (!m.re.test(d)) return `El número no corresponde a una tarjeta ${m.nombre}. ${m.nombre} empieza con ${({ visa: '4', mastercard: '51–55 o 22–27', amex: '34 o 37' })[marca()]}.`;
      if (d.length !== m.largo) return `Una tarjeta ${m.nombre} tiene ${m.largo} dígitos (escribiste ${d.length}).`;
      if (!luhn(d)) return 'El número de tarjeta no es válido: revisa que esté bien escrito.';
      return '';
    };
    if (ven) ven.pcValidar = (v) => {
      const mm = /^(\d{2})\/(\d{2})$/.exec(v);
      if (!mm) return 'Escribe el vencimiento como MM/AA, por ejemplo 08/28.';
      if (+mm[1] < 1 || +mm[1] > 12) return 'El mes del vencimiento debe estar entre 01 y 12.';
      const fin = new Date(2000 + +mm[2], +mm[1], 0, 23, 59), hoy = new Date();
      if (fin < hoy) return 'La tarjeta está vencida.';
      if (+mm[2] > (hoy.getFullYear() % 100) + 15) return 'Revisa el año del vencimiento.';
      return '';
    };
    if (cvv) cvv.pcValidar = (v) => v.length === MARCAS[marca()].cvv ? '' : `El CVV de ${MARCAS[marca()].nombre} tiene ${MARCAS[marca()].cvv} dígitos.`;
    cvv?.addEventListener('focus', () => card.classList.add('flip'));
    cvv?.addEventListener('blur', () => card.classList.remove('flip'));

    // Comprobante: arrastrar y soltar con vista previa
    const file = mp.querySelector('[name=comprobante]'), drop = mp.querySelector('.drop');
    if (file && drop) {
      const mostrar = () => {
        const f = file.files[0], img = drop.querySelector('[data-drop-img]'), txt = drop.querySelector('[data-drop-txt]');
        txt.dataset.orig ??= txt.innerHTML;
        if (!f) { drop.classList.remove('lleno'); txt.innerHTML = txt.dataset.orig; img.hidden = true; return; }
        drop.classList.add('lleno');
        txt.innerHTML = `<b>${f.name.replace(/</g, '')}</b><small>${(f.size / 1024 / 1024).toFixed(2)} MB · toca para cambiarlo</small>`;
        if (f.type.startsWith('image/')) { img.src = URL.createObjectURL(f); img.hidden = false; } else { img.hidden = true; }
      };
      file.addEventListener('change', mostrar);
      ['dragover', 'dragenter'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.classList.add('sobre'); }));
      ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, () => drop.classList.remove('sobre')));
      drop.addEventListener('drop', (e) => { e.preventDefault(); if (e.dataTransfer.files.length) { file.files = e.dataTransfer.files; file.dispatchEvent(new Event('change', { bubbles: true })); } });
    }
    pintarMetodo();
    if (card) pintarTarjeta();
  });
})();

/* ---------- Validación campo por campo en todos los formularios ----------
   Lee los atributos del HTML (required, type, minlength, maxlength, min, max, pattern, accept, data-max-mb)
   y muestra un mensaje específico debajo de cada casilla, en el momento y al enviar.
   Mensajes propios con data-msg (obligatorio), data-msg-pattern, data-msg-min, data-msg-max, data-msg-formato.
   También coloca debajo de cada casilla los errores que devuelve el servidor (window.PC_ERR). */
(() => {
  const NO = 'button,submit,reset,hidden,image';
  const campos = (f) => Array.from(f.elements).filter((el) => el.name && !NO.includes(el.type) && !el.closest('[hidden]') && !el.disabled);
  const limpiar = (t) => (t || '').replace(/\*|\(opcional[^)]*\)|\(obligatori[^)]*\)/gi, '').replace(/\s+/g, ' ').replace(/[:：]\s*$/, '').trim();
  const fecha = (v) => { const [y, m, d] = v.split('-'); return d ? `${d}/${m}/${y}` : v; };

  function etiqueta(el) {
    if (el.dataset.label) return el.dataset.label;
    let l = el.id && document.querySelector(`label[for="${CSS.escape(el.id)}"]`);
    if (!l) { const c = el.closest('.f > div, .fr > div, [data-campo], .acepto-box') || el.parentElement; l = c?.querySelector(':scope > label:not(.cb):not(.opc), :scope > .lbl'); }
    let t = limpiar(l ? (l.cloneNode(true).querySelector('.hint')?.remove(), l.textContent) : el.placeholder || el.name);
    return t ? t.charAt(0).toLowerCase() + t.slice(1) : 'este campo';
  }
  const Mayus = (s) => s.charAt(0).toUpperCase() + s.slice(1);

  function contenedor(el) {
    if (el.id) { const dz = document.querySelector(`label.drop[for="${CSS.escape(el.id)}"]`); if (dz) return dz; }
    const w = el.closest('.vet-ops, [data-campo], .tel, .drop, .estrellas-in, .planes-cita, .opcs, .cbs, .chips-sel, .acepto-box, .mp-marcas, .mp-tabs, .seg, .bs-in');
    if (w) return w;
    if (el.type === 'checkbox' || el.type === 'radio') return el.closest('label') || el.parentElement;
    return el;
  }

  function mensaje(el) {
    const v = el.type === 'file' ? '' : (el.value || '').trim();
    const ds = el.dataset, lab = etiqueta(el);
    const grupo = (el.type === 'checkbox' || el.type === 'radio') && el.form ? Array.from(el.form.querySelectorAll(`[name="${CSS.escape(el.name)}"]`)).filter((x) => !x.disabled) : null;
    // Obligatorio
    if (el.required || ds.requerido !== undefined) {
      if (el.type === 'radio' && !grupo.some((x) => x.checked)) return ds.msg || `Selecciona ${lab}.`;
      if (el.type === 'checkbox') {
        if (el.name.endsWith('[]') && !grupo.some((x) => x.checked)) return ds.msg || `Marca al menos una opción de ${lab}.`;
        if (!el.name.endsWith('[]') && !el.checked) return ds.msg || 'Debes marcar esta casilla para continuar.';
      }
      if (el.type === 'file' && !el.files.length) return ds.msg || `Sube ${lab}.`;
      if (!['radio', 'checkbox', 'file'].includes(el.type) && !v) return ds.msg || (el.tagName === 'SELECT' ? `Selecciona ${lab}.` : `Escribe ${lab}.`);
    }
    if (el.type === 'file' && el.files.length) {
      const f = el.files[0], mb = parseFloat(ds.maxMb || '5');
      if (f.size > mb * 1024 * 1024) return `El archivo pesa ${(f.size / 1048576).toFixed(1)} MB: el máximo es ${mb} MB.`;
      const acc = (el.accept || '').split(',').map((a) => a.trim()).filter(Boolean);
      if (acc.length && !acc.some((a) => a.endsWith('/*') ? f.type.startsWith(a.slice(0, -1)) : (a.startsWith('.') ? f.name.toLowerCase().endsWith(a) : f.type === a)))
        return ds.msgFormato || `Formato no permitido. Sube ${acc.map((a) => a.replace(/^(image|application)\//, '').replace('jpeg', 'JPG').toUpperCase()).filter((x, i, a) => a.indexOf(x) === i).join(', ')}.`;
    }
    if (!v) return '';
    if (ds.segura !== undefined) {
      const falta = [[/.{8,}/, '8 caracteres'], [/[A-ZÁÉÍÓÚÑ]/, 'una mayúscula'], [/[a-záéíóúñ]/, 'una minúscula'], [/\d/, 'un número'], [/[^\w\sáéíóúñÁÉÍÓÚÑ]/, 'un símbolo (!@#$…)']]
        .filter(([re]) => !re.test(v)).map(([, t]) => t);
      if (falta.length) return 'A la contraseña le falta: ' + falta.join(', ') + '.';
    }
    if (el.type === 'email' && !/^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(v)) return ds.msgFormato || 'Escribe un correo válido, por ejemplo nombre@gmail.com.';
    if (el.minLength > 0 && v.length < el.minLength) return ds.msgMin || `${Mayus(lab)} debe tener al menos ${el.minLength} caracteres.`;
    if (el.maxLength > 0 && v.length > el.maxLength) return ds.msgMax || `${Mayus(lab)} admite como máximo ${el.maxLength} caracteres.`;
    if (el.pattern && !new RegExp(`^(?:${el.pattern})$`, 'u').test(v)) return ds.msgPattern || `${Mayus(lab)} no tiene el formato correcto.`;
    if (el.type === 'number' || ds.numero !== undefined) {
      const n = Number(v.replace(',', '.'));
      if (Number.isNaN(n)) return `${Mayus(lab)} debe ser un número.`;
      if (el.min !== '' && n < Number(el.min)) return ds.msgMin || `${Mayus(lab)} debe ser como mínimo ${el.min}.`;
      if (el.max !== '' && n > Number(el.max)) return ds.msgMax || `${Mayus(lab)} debe ser como máximo ${el.max}.`;
    }
    if (el.type === 'date') {
      if (el.min && v < el.min) return ds.msgMin || `La fecha no puede ser anterior al ${fecha(el.min)}.`;
      if (el.max && v > el.max) return ds.msgMax || `La fecha no puede ser posterior al ${fecha(el.max)}.`;
    }
    if (el.type === 'date' && ds.noDomingo !== undefined && new Date(v + 'T12:00').getDay() === 0) return 'Las veterinarias no atienden los domingos: elige otro día.';
    if (el.type === 'month' && el.max && v > el.max) return ds.msgMax || 'Ese mes todavía no llegó.';
    if (ds.igual && el.form) { const o = el.form.querySelector(`[name="${ds.igual}"]`); if (o && o.value !== el.value) return ds.msgIgual || 'Las contraseñas no coinciden.'; }
    if (typeof el.pcValidar === 'function') return el.pcValidar(v) || '';
    return '';
  }

  function pintar(el, msg, servidor = false) {
    const box = contenedor(el);
    const grupo = (el.type === 'radio' || el.type === 'checkbox') && el.name.endsWith('[]') || el.type === 'radio';
    const id = 'fe-' + (el.form?.dataset.pcid || '') + '-' + el.name.replace(/[^\w-]/g, '_');
    let fe = document.getElementById(id);
    const marcas = grupo && el.form ? Array.from(el.form.querySelectorAll(`[name="${CSS.escape(el.name)}"]`)) : [el];
    marcas.forEach((x) => { x.classList.toggle('is-bad', !!msg); x.setAttribute('aria-invalid', msg ? 'true' : 'false'); if (msg) x.setAttribute('aria-describedby', id); });
    box.classList?.toggle('is-bad-box', !!msg && box !== el);
    if (!msg) { fe?.remove(); return; }
    if (!fe) {
      fe = document.createElement('div'); fe.className = 'fe'; fe.id = id; fe.setAttribute('role', 'alert');
      const ancla = box.nextElementSibling?.classList.contains('hint') && box.tagName !== 'DIV' ? box.nextElementSibling : box;
      ancla.after(fe);
    }
    fe.classList.toggle('srv', servidor);
    fe.innerHTML = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.5"/></svg><span></span>';
    fe.querySelector('span').textContent = msg;
  }

  function validar(el) { const m = mensaje(el); pintar(el, m); return !m; }

  let n = 0;
  document.querySelectorAll('form').forEach((f) => {
    if (f.method.toLowerCase() === 'get' && !f.querySelector('[required]')) return;
    f.dataset.pcid = ++n;
    f.noValidate = true;
    // Asterisco en los obligatorios
    campos(f).forEach((el) => {
      if (!el.required) return;
      const lab = (el.id && f.querySelector(`label[for="${CSS.escape(el.id)}"]`)) || (el.closest('.f > div, .fr > div, [data-campo]')?.querySelector(':scope > label:not(.cb):not(.opc)'));
      lab?.classList.add('req');
    });
    f.addEventListener('focusout', (e) => { const el = e.target; if (el.name && el.form === f && (el.value || el.dataset.tocado)) { el.dataset.tocado = '1'; validar(el); } });
    f.addEventListener('input', (e) => { const el = e.target; if (el.dataset.tocado || el.classList.contains('is-bad')) validar(el); });
    f.addEventListener('change', (e) => { const el = e.target; if (['checkbox', 'radio', 'file'].includes(el.type) || el.tagName === 'SELECT') { el.dataset.tocado = '1'; validar(el); } });
    f.addEventListener('submit', (e) => {
      const vistos = new Set(); let primero = null;
      campos(f).forEach((el) => {
        const k = (el.type === 'radio' || el.name.endsWith('[]')) ? el.name : el;
        if (vistos.has(k)) return; vistos.add(k);
        el.dataset.tocado = '1';
        if (!validar(el) && !primero) primero = el;
      });
      if (primero) {
        e.preventDefault(); e.stopImmediatePropagation();
        const vis = primero.type === 'file' && primero.closest('.drop') ? primero.closest('.drop') : (primero.offsetParent ? primero : contenedor(primero));
        vis.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => primero.focus({ preventScroll: true }), 250);
        const resumen = f.querySelector('.fe-resumen') || Object.assign(document.createElement('div'), { className: 'note bad fe-resumen', role: 'alert' });
        const tot = f.querySelectorAll('.fe').length;
        resumen.textContent = tot === 1 ? 'Falta corregir 1 dato: revisa la casilla marcada en rojo.' : `Faltan corregir ${tot} datos: revisa las casillas marcadas en rojo.`;
        if (!resumen.isConnected) { const b = f.querySelector('.md-f') || f.querySelector('[type=submit]'); b ? b.before(resumen) : f.prepend(resumen); }
      } else f.querySelector('.fe-resumen')?.remove();
    }, true);
  });
  // Al corregir, el resumen se actualiza
  document.addEventListener('input', (e) => { const f = e.target.form, r = f?.querySelector('.fe-resumen'); if (r && !f.querySelector('.fe')) r.remove(); });

  // Errores del servidor debajo de cada casilla
  const E = window.PC_ERR;
  if (E && E.bags) {
    Object.entries(E.bags).forEach(([bag, errs]) => Object.entries(errs).forEach(([campo, msgs]) => {
      const base = campo.split('.')[0], nombres = [campo, base, base + '[]', campo.replace(/\.(\w+)/g, '[$1]')];
      let cand = [];
      nombres.forEach((nm) => document.querySelectorAll(`[name="${CSS.escape(nm)}"]`).forEach((x) => cand.push(x)));
      cand = cand.filter((x) => x.type !== 'hidden' || x.dataset.mostrarError !== undefined);
      if (!cand.length) return;
      const pref = cand.find((x) => E.form && x.form?.querySelector(`input[name=_form][value="${CSS.escape(E.form)}"]`))
        || cand.find((x) => x.form?.dataset.bag === bag) || cand.find((x) => !x.closest('dialog')) || cand[0];
      pintar(pref, msgs[0], true);
      // El mensaje ya está junto a su casilla: se quita del resumen
      document.querySelectorAll(`.err-resumen li[data-err="${CSS.escape(campo)}"]`).forEach((li) => li.remove());
    }));
    document.querySelectorAll('.err-resumen').forEach((r) => {
      if (!r.querySelector('li')) r.querySelector('ul')?.remove();          // todo quedó junto a su casilla
      else if (!document.querySelector('.fe.srv')) r.querySelector('[data-err-titulo]')?.remove(); // errores generales
    });
  }
})();

/* ---------- Ventanas flotantes: se pueden mover (arrastrando el título) y agrandar ---------- */
(() => {
  const ancho = () => window.matchMedia('(min-width: 700px)').matches;
  document.querySelectorAll('dialog.md').forEach((d) => {
    const h = d.querySelector('.md-h');
    if (!h) return;
    // Botón maximizar / restaurar
    const max = document.createElement('button');
    max.type = 'button'; max.className = 'icb md-max'; max.setAttribute('aria-label', 'Agrandar ventana'); max.title = 'Agrandar / restaurar';
    max.innerHTML = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/></svg>';
    h.querySelector('[data-close]')?.before(max);
    const alternar = () => { d.classList.toggle('max'); d.style.translate = ''; max.setAttribute('aria-label', d.classList.contains('max') ? 'Restaurar ventana' : 'Agrandar ventana'); };
    max.addEventListener('click', alternar);
    h.addEventListener('dblclick', (e) => { if (!e.target.closest('button')) alternar(); });
    // Arrastrar
    let x0 = 0, y0 = 0, dx = 0, dy = 0, mov = false;
    h.addEventListener('pointerdown', (e) => {
      if (!ancho() || e.button !== 0 || e.target.closest('button,a,input') || d.classList.contains('max')) return;
      mov = true; h.setPointerCapture(e.pointerId); d.classList.add('moviendo');
      const t = (d.style.translate || '0px 0px').split(' ').map(parseFloat); dx = t[0] || 0; dy = t[1] || 0; x0 = e.clientX; y0 = e.clientY;
    });
    h.addEventListener('pointermove', (e) => {
      if (!mov) return;
      const r = d.getBoundingClientRect();
      let nx = dx + e.clientX - x0, ny = dy + e.clientY - y0;
      // Que el título no salga de la pantalla
      const offX = r.left - (parseFloat((d.style.translate || '0').split(' ')[0]) || 0), offY = r.top - (parseFloat((d.style.translate || '0 0').split(' ')[1]) || 0);
      nx = Math.min(Math.max(nx, -offX - r.width + 120), window.innerWidth - offX - 120);
      ny = Math.min(Math.max(ny, -offY), window.innerHeight - offY - 60);
      d.style.translate = `${nx}px ${ny}px`;
    });
    const fin = () => { mov = false; d.classList.remove('moviendo'); };
    h.addEventListener('pointerup', fin); h.addEventListener('pointercancel', fin);
    d.addEventListener('close', () => { d.style.translate = ''; d.classList.remove('max'); });
  });
})();

/* ---------- Cartilla como libreta física de cartulina: tapa y páginas que se pasan ---------- */
(() => {
  document.querySelectorAll('.libro-win').forEach((w) => {
    const libro = w.querySelector('.libro');
    const pags = Array.from(libro.querySelectorAll('.libro-p'));
    const lbl = w.querySelector('[data-libro-pag]'), puntos = w.querySelector('[data-libro-puntos]'), mesa = w.querySelector('[data-libro-mesa]');
    const nombres = pags.map((p) => p.classList.contains('tapa') ? 'Tapa' : (p.dataset.nombre || ''));
    puntos.innerHTML = pags.map((_, k) => `<i data-k="${k}"></i>`).join('');
    let i = 0;
    // En pantallas anchas la libreta se ve abierta (dos páginas); la tapa, sola.
    const doble = () => window.matchMedia('(min-width: 980px)').matches;
    const vista = () => {
      if (!doble() || i === 0) return [i];
      const a = i % 2 ? i : i - 1; return [a, a + 1].filter((k) => k < pags.length);
    };
    const pintar = (dir) => {
      const v = vista();
      pags.forEach((p, k) => {
        const on = v.includes(k);
        p.classList.toggle('on', on);
        p.classList.toggle('izq', on && v.length === 2 && k === v[0]);
        p.classList.toggle('der', on && v.length === 2 && k === v[1]);
        if (on && dir) { p.classList.remove('pasa-sig', 'pasa-ant'); void p.offsetWidth; p.classList.add(dir > 0 ? 'pasa-sig' : 'pasa-ant'); }
      });
      libro.classList.toggle('cerrado', i === 0);
      libro.classList.toggle('doble', v.length === 2);
      lbl.textContent = v.map((k) => nombres[k]).join(' · ') + ` · página ${Math.min(...v) + 1} de ${pags.length}`;
      puntos.querySelectorAll('i').forEach((d, k) => d.classList.toggle('on', v.includes(k)));
      w.querySelector('[data-libro=prev]').disabled = i === 0;
      w.querySelector('[data-libro=next]').disabled = Math.max(...v) >= pags.length - 1;
    };
    const ir = (dir) => {
      const v = vista();
      const n = dir > 0 ? Math.max(...v) + 1 : (doble() && Math.min(...v) > 1 ? Math.min(...v) - 2 : Math.min(...v) - 1);
      if (n < 0 || n >= pags.length) return;
      i = Math.max(0, n); pintar(dir);
    };
    w.addEventListener('click', (e) => {
      const b = e.target.closest('[data-libro]'); if (b && w.contains(b)) { ({ prev: () => ir(-1), next: () => ir(1) })[b.dataset.libro]?.(); return; }
      const d = e.target.closest('.libro-puntos i'); if (d) { const k = +d.dataset.k; i = k; pintar(k > i ? 1 : -1); }
    });
    w.addEventListener('keydown', (e) => { if (e.key === 'ArrowRight') { ir(1); e.preventDefault(); } if (e.key === 'ArrowLeft') { ir(-1); e.preventDefault(); } });
    libro.addEventListener('click', (e) => { if (e.target.closest('.tapa.on')) ir(1); });
    let sx = null;
    mesa.addEventListener('touchstart', (e) => { sx = e.touches[0].clientX; }, { passive: true });
    mesa.addEventListener('touchend', (e) => { if (sx === null) return; const d = e.changedTouches[0].clientX - sx; if (Math.abs(d) > 45) ir(d < 0 ? 1 : -1); sx = null; });
    window.addEventListener('resize', () => pintar());
    w.addEventListener('close', () => { i = 0; pintar(); });
    pintar();
  });
})();

/* ---------- Horario de atención: vista previa y reglas (día final ≥ inicial, cierre > apertura) ---------- */
(() => {
  const DIAS = ['lun', 'mar', 'mie', 'jue', 'vie', 'sab', 'dom'];
  const CORTO = { lun: 'Lun', mar: 'Mar', mie: 'Mié', jue: 'Jue', vie: 'Vie', sab: 'Sáb', dom: 'Dom' };
  const min = (h) => { const [a, b] = h.split(':').map(Number); return a * 60 + b; };
  document.querySelectorAll('[data-horario]').forEach((h) => {
    const dd = h.querySelector('[name=horario_dia_desde]'), dh = h.querySelector('[name=horario_dia_hasta]');
    const hd = h.querySelector('[name=horario_hora_desde]'), hh = h.querySelector('[name=horario_hora_hasta]');
    const h24 = h.querySelector('[name=horario_24]'), vista = h.querySelector('[data-hor-vista]');
    dh.pcValidar = () => DIAS.indexOf(dh.value) < DIAS.indexOf(dd.value) ? 'El día final no puede ser anterior al día inicial (por ejemplo: de lunes a sábado).' : '';
    hh.pcValidar = () => {
      if (min(hh.value) <= min(hd.value)) return 'La hora de cierre debe ser posterior a la de apertura.';
      if (min(hh.value) - min(hd.value) < 120) return 'La clínica debe atender al menos 2 horas al día.';
      return '';
    };
    const pintar = () => {
      h.querySelectorAll('[data-hor-horas]').forEach((x) => { x.hidden = h24.checked; });
      const dias = CORTO[dd.value] + (dd.value !== dh.value ? '–' + CORTO[dh.value] : '');
      vista.textContent = dias + ' · ' + (h24.checked ? '24 horas' : `${hd.value} a ${hh.value}`);
    };
    h.addEventListener('change', (e) => {
      pintar();
      // Revalida la pareja cuando cambia uno de los dos
      if (e.target === dd) dh.dispatchEvent(new Event('input', { bubbles: true }));
      if (e.target === hd) hh.dispatchEvent(new Event('input', { bubbles: true }));
    });
    dh.dataset.tocado = hh.dataset.tocado = '1';
    pintar();
  });
})();

/* ---------- Servicios «Otros»: muestra el campo para escribirlos ---------- */
(() => {
  document.querySelectorAll('[data-servicios-box]').forEach((b) => {
    const cb = b.querySelector('[data-otros]'), campo = b.querySelector('[data-otros-campo]');
    const pintar = () => { campo.hidden = !cb.checked; if (cb.checked && !campo.dataset.visto) { campo.dataset.visto = '1'; campo.querySelector('input')?.focus(); } };
    cb.addEventListener('change', pintar);
    if (!campo.hidden) campo.dataset.visto = '1';
  });
})();

/* ---------- Afiliación: el monto que se cobrará después del mes gratis según el plan elegido ---------- */
(() => {
  const f = document.getElementById('fafil');
  if (!f) return;
  const precios = JSON.parse(f.dataset.precios || '{}');
  const pinta = () => {
    const p = precios[f.querySelector('[name=plan_suscripcion]:checked')?.value];
    if (p) f.querySelectorAll('[data-mp-monto]').forEach((x) => { x.textContent = 'Bs ' + Number(p).toLocaleString('es-BO'); });
  };
  f.addEventListener('change', (e) => { if (e.target.name === 'plan_suscripcion') pinta(); });
  pinta();
})();

/* ---------- Opción «Otro…» en una lista: muestra el campo para escribirlo ---------- */
(() => {
  document.querySelectorAll('select[data-otro]').forEach((s) => {
    const box = document.getElementById(s.dataset.otro);
    if (!box) return;
    const pintar = (foco) => {
      const on = /^Otro/.test(s.value);
      box.hidden = !on;
      box.querySelectorAll('input,select,textarea').forEach((i) => { i.disabled = !on; });
      if (on && foco) box.querySelector('input')?.focus();
    };
    s.addEventListener('change', () => pintar(true));
    pintar(false);
  });
})();

/* ---------- Archivos: miniatura con ✕ para quitarlo, fotos reducidas antes de subir ----------
   Las fotos grandes (celular) se achican en el navegador a 1600 px: suben rápido y nunca superan el límite del servidor. */
(() => {
  const MAX_LADO = 1600, UMBRAL = 1.2 * 1024 * 1024;
  const esc = (t) => String(t).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  const mb = (b) => (b / 1048576 >= 1 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB');
  const X = '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>';
  const PATA = '<svg class="i" viewBox="0 0 24 24" aria-hidden="true"><circle cx="7" cy="9" r="2"/><circle cx="12" cy="6.5" r="2"/><circle cx="17" cy="9" r="2"/><path d="M8 16c0-2.5 1.8-4.5 4-4.5s4 2 4 4.5c0 1.6-1.4 2.5-4 2.5s-4-.9-4-2.5z"/></svg>';

  function reducir(f) {
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) return Promise.resolve(f);
    return new Promise((ok) => {
      const img = new Image(), url = URL.createObjectURL(f);
      img.onload = () => {
        URL.revokeObjectURL(url);
        const k = Math.min(1, MAX_LADO / Math.max(img.width, img.height));
        if (k === 1 && f.size <= UMBRAL) return ok(f);
        const c = document.createElement('canvas');
        c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        const tipo = f.type === 'image/png' ? 'image/webp' : 'image/jpeg';
        c.toBlob((b) => {
          if (!b || b.size >= f.size) return ok(f);
          ok(new File([b], f.name.replace(/\.\w+$/, '') + (tipo === 'image/webp' ? '.webp' : '.jpg'), { type: tipo, lastModified: Date.now() }));
        }, tipo, 0.85);
      };
      img.onerror = () => { URL.revokeObjectURL(url); ok(f); };
      img.src = url;
    });
  }
  const permitido = (el, f) => {
    const acc = (el.accept || '').split(',').map((a) => a.trim()).filter(Boolean);
    return !acc.length || acc.some((a) => a.endsWith('/*') ? f.type.startsWith(a.slice(0, -1)) : (a.startsWith('.') ? f.name.toLowerCase().endsWith(a) : f.type === a));
  };

  document.querySelectorAll('input[type=file]').forEach((el) => {
    const ancla = (el.id && document.querySelector(`label.drop[for="${CSS.escape(el.id)}"]`)) || el;
    const chip = document.createElement('div');
    chip.className = 'file-sel'; chip.hidden = true;
    ancla.after(chip);
    const prev = el.dataset.preview && document.getElementById(el.dataset.preview);
    const prevOriginal = prev ? prev.innerHTML : '';
    const qg = el.dataset.quitarGuardada && document.getElementById(el.dataset.quitarGuardada);

    const pintarGuardada = () => {
      if (!qg) return;
      const quitada = qg.value === '1';
      chip.hidden = false;
      chip.className = 'file-sel guardada' + (quitada ? ' quitada' : '');
      chip.innerHTML = quitada
        ? '<span class="fs-t"><b>Foto quitada</b><small>Se guardará sin foto.</small></span><button type="button" class="btn sm ghost" data-fs-deshacer>Deshacer</button>'
        : '<span class="fs-t"><b>Foto actual</b><small>Puedes cambiarla o quitarla.</small></span><button type="button" class="btn sm ghost bad-t" data-fs-quitar-guardada>' + X + ' Quitar foto</button>';
      if (prev) prev.innerHTML = quitada ? PATA : prevOriginal;
    };
    const pintar = (aviso) => {
      const fs = Array.from(el.files || []);
      if (!fs.length) {
        chip.innerHTML = ''; chip.hidden = true;
        if (prev && (!qg || qg.value !== '1')) prev.innerHTML = prevOriginal;
        if (qg) pintarGuardada();
        if (aviso) { chip.hidden = false; chip.className = 'file-sel error'; chip.innerHTML = `<span class="fs-t"><b>${esc(aviso)}</b><small>El campo quedó vacío: elige otro archivo o continúa sin él.</small></span>`; }
        return;
      }
      chip.className = 'file-sel';
      const total = fs.reduce((s, f) => s + f.size, 0), f = fs[0];
      const img = f.type.startsWith('image/') ? `<img src="${URL.createObjectURL(f)}" alt="">` : '<span class="fs-pdf">PDF</span>';
      chip.innerHTML = `${img}<span class="fs-t"><b>${esc(fs.length > 1 ? fs.length + ' archivos' : f.name)}</b><small>${mb(total)}${el.dataset.reducida ? ' · reducida para subir más rápido' : ''}</small></span>`
        + `<button type="button" class="fs-x" aria-label="Quitar archivo" title="Quitar">${X}</button>`;
      chip.hidden = false;
      if (prev && f.type.startsWith('image/')) prev.innerHTML = `<img src="${URL.createObjectURL(f)}" alt="Vista previa">`;
    };
    const vaciar = (aviso) => {
      el.value = ''; delete el.dataset.reducida;
      el._pcListo = true; el.dispatchEvent(new Event('change', { bubbles: true }));
      pintar(aviso);
    };
    chip.addEventListener('click', (e) => {
      if (e.target.closest('.fs-x')) { e.preventDefault(); vaciar(); el.focus(); }
      if (e.target.closest('[data-fs-quitar-guardada]')) { qg.value = '1'; pintarGuardada(); }
      if (e.target.closest('[data-fs-deshacer]')) { qg.value = '0'; pintarGuardada(); }
    });
    // Se procesa antes que la validación: reduce las fotos y descarta lo que no sirve
    el.addEventListener('change', async (e) => {
      if (el._pcListo) { delete el._pcListo; return; }
      e.stopImmediatePropagation();
      const fs = Array.from(el.files || []);
      if (!fs.length) return vaciar();
      const malo = fs.find((f) => !permitido(el, f));
      if (malo) return vaciar(`«${malo.name}» no es un formato permitido.`);
      const listos = await Promise.all(fs.map(reducir));
      const max = parseFloat(el.dataset.maxMb || '10') * 1048576;
      const pesado = listos.find((f) => f.size > max);
      if (pesado) return vaciar(`«${pesado.name}» pesa ${mb(pesado.size)}: el máximo es ${el.dataset.maxMb || 10} MB.`);
      if (listos.some((f, i) => f !== fs[i])) {
        const dt = new DataTransfer(); listos.forEach((f) => dt.items.add(f)); el.files = dt.files; el.dataset.reducida = '1';
      } else delete el.dataset.reducida;
      if (qg) qg.value = '0';
      pintar();
      el._pcListo = true; el.dispatchEvent(new Event('change', { bubbles: true }));
    }, true);
    if (qg) pintarGuardada();
  });
})();

/* ---------- Tablas dentro de ventanas: buscador e impresión ---------- */
(() => {
  document.querySelectorAll('[data-filtra]').forEach((inp) => inp.addEventListener('input', () => {
    const q = inp.value.trim().toLowerCase();
    document.querySelectorAll(inp.dataset.filtra + ' tbody tr').forEach((tr) => { tr.hidden = q && !tr.textContent.toLowerCase().includes(q); });
  }));
  document.querySelectorAll('[data-imprimir-dlg]').forEach((b) => b.addEventListener('click', () => {
    document.body.classList.add('imprime-dlg'); document.getElementById(b.dataset.imprimirDlg)?.classList.add('a-imprimir');
    window.print();
    setTimeout(() => { document.body.classList.remove('imprime-dlg'); document.querySelectorAll('.a-imprimir').forEach((d) => d.classList.remove('a-imprimir')); }, 300);
  }));
})();

/* ---------- Formulario de plan: vista previa de la tarjeta en vivo ---------- */
(() => {
  const bs = (n) => 'Bs ' + Number(n || 0).toLocaleString('es-BO', { maximumFractionDigits: 2 });
  document.querySelectorAll('[data-plan-form]').forEach((f) => {
    const card = f.querySelector('[data-pv-card]'), o = (k) => card.querySelector(`[data-pv-o="${k}"]`);
    const val = (n) => f.querySelector(`[name="${n}"]`)?.value || '';
    const pintar = () => {
      o('nombre').textContent = val('nombre') || 'Nombre del plan';
      o('descripcion').textContent = val('descripcion');
      o('pct').textContent = val('porcentaje') || '0';
      o('tm').textContent = bs(val('tope_mensual')); o('ta').textContent = bs(val('tope_anual'));
      o('lim').textContent = val('limite_atenciones') ? val('limite_atenciones') + ' al año' : 'Sin límite';
      o('carencia').textContent = (+val('carencia_dias') ? 'el día ' + (+val('carencia_dias') + 1) : 'el primer día');
      o('precio').textContent = bs(String(val('precio')).replace(',', '.'));
      o('beneficios').innerHTML = val('beneficios').split(/\n/).map((t) => t.trim()).filter(Boolean)
        .map((t) => `<li><svg class="i" viewBox="0 0 24 24"><path d="m5 12 5 5 9-10"/></svg><span>${t.replace(/</g, '&lt;')}</span></li>`).join('');
      const c = f.querySelector('[name=color]:checked'); card.style.setProperty('--plan', c?.dataset.hex || '#5B3FD6');
      card.querySelectorAll('[data-ic]').forEach((s) => { s.hidden = s.dataset.ic !== val('icono'); });
      const p = +String(val('precio')).replace(',', '.'), a = +String(val('precio_anual')).replace(',', '.');
      const ah = f.querySelector('[data-pv-ahorro]');
      if (ah) ah.textContent = p ? (a ? `Ahorro de ${bs(p * 12 - a)} frente a 12 meses.` : `Sin precio anual: se cobra ${bs(p * 10)} (2 meses gratis).`) : '';
      f.querySelectorAll('.color-op').forEach((l) => l.classList.toggle('on', l.querySelector('input').checked));
    };
    f.addEventListener('input', pintar); f.addEventListener('change', pintar); pintar();
  });
})();

/* ---------- Plan: veterinaria del contrato y datos del contrato en vivo ---------- */
(() => {
  const f = document.getElementById('fpago');
  if (!f || !f.dataset.contratos) return;
  const C = JSON.parse(f.dataset.contratos || '{}');
  const selM = f.querySelector('[name=mascota_id]'), avisoM = f.querySelector('[data-mascota-aviso]'), fija = f.querySelector('[data-vet-fija]');
  const set = (k, v) => document.querySelectorAll(`[data-ct="${k}"]`).forEach((x) => { x.textContent = v; });
  const pintar = () => {
    const c = C[selM.value] || {};
    // Contrato vigente: la veterinaria queda fija
    f.querySelectorAll('[name=veterinaria_id]').forEach((r) => {
      r.disabled = !!c.vet && String(r.value) !== String(c.vet);
      if (c.vet && String(r.value) === String(c.vet)) r.checked = true;
      r.closest('.vet-op').classList.toggle('off', r.disabled);
    });
    fija.hidden = !c.vet;
    if (c.vet) fija.querySelector('span').textContent = `Su contrato actual sigue hasta el ${c.hasta}: el plan se renueva en la misma veterinaria.`;
    const msg = c.motivo || (c.otroPlan ? `Tiene un contrato con el ${c.otroPlan} hasta que cumpla su permanencia: solo puede renovar ese plan.` : '');
    avisoM.hidden = !msg; avisoM.querySelector('span').textContent = msg;
    f.querySelector('[data-btn-pago]').disabled = !!msg;
    const v = f.querySelector('[name=veterinaria_id]:checked');
    set('veterinaria', v ? v.dataset.nombre : 'la veterinaria que elijas'); set('direccion_vet', v ? ` (${v.dataset.dir})` : '');
    set('mascota', selM.selectedOptions[0]?.textContent.split(' · ')[0] || ''); set('especie', c.especie || '');
    const mod = f.querySelector('[name=modalidad]:checked')?.value || 'mensual';
    set('modalidad', mod); set('precio', (document.getElementById('sp-total')?.textContent || '') + (mod === 'anual' ? ' al año' : ' al mes'));
    set('grave', f.querySelector('[name=cobertura_grave]')?.checked ? ' + Cobertura Grave' : '');
    set('ci', f.querySelector('[name=documento]')?.value || '(tu CI o NIT)');
  };
  f.addEventListener('change', () => setTimeout(pintar)); f.addEventListener('input', () => setTimeout(pintar));
  pintar();
})();

/* ---------- Tienda: total del pedido según entrega y descuento del plan; envío de la clínica ---------- */
(() => {
  const f = document.getElementById('fpago');
  if (f && f.dataset.pedido) {
    const P = JSON.parse(f.dataset.pedido), bs = (n) => 'Bs ' + Number(n).toLocaleString('es-BO', { minimumFractionDigits: n % 1 ? 2 : 0, maximumFractionDigits: 2 });
    const pintar = () => {
      const dom = f.querySelector('[name=entrega]:checked')?.value === 'domicilio';
      const box = f.querySelector('[data-domicilio]');
      if (box) { box.hidden = !dom; box.querySelectorAll('input').forEach((i) => { i.disabled = !dom; }); }
      const m = f.querySelector('[name=mascota_id]')?.value, d = (m && P.descuentos[m]) || { monto: 0, nota: null };
      const nota = f.querySelector('[data-desc-nota]');
      if (nota) { nota.hidden = !d.nota; nota.querySelector('span').textContent = d.nota || ''; }
      const envio = dom ? P.envio : 0, total = Math.max(0, P.subtotal - d.monto + envio);
      f.querySelector('[data-fila-desc]').hidden = !d.monto; f.querySelector('[data-desc]').textContent = '− ' + bs(d.monto);
      f.querySelector('[data-fila-envio]').hidden = !dom; f.querySelector('[data-envio-monto]').textContent = envio ? bs(envio) : 'Gratis';
      f.querySelector('[data-total]').textContent = bs(total);
      f.querySelectorAll('[data-mp-monto]').forEach((x) => { x.textContent = bs(total); });
    };
    f.addEventListener('change', pintar); pintar();
  }
  const ev = document.querySelector('input[data-envio]');
  if (ev) {
    const c = document.querySelector('[data-envio-campos]');
    const p = () => { c.hidden = !ev.checked; c.querySelectorAll('input').forEach((i) => { i.disabled = !ev.checked; }); };
    ev.addEventListener('change', p); p();
  }
})();
