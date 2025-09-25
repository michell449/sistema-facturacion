<!--begin::Third Party Plugin(OverlayScrollbars)-->
<script
src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"
  crossorigin="anonymous"></script>
<!--end::Third Party Plugin(OverlayScrollbars)--><!--begin::Required Plugin(popperjs for Bootstrap 5)-->
<script
  src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
  crossorigin="anonymous"></script>
<!--end::Required Plugin(popperjs for Bootstrap 5)--><!--begin::Required Plugin(Bootstrap 5)-->
<script
  src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"
crossorigin="anonymous"></script>
<!--end::Required Plugin(Bootstrap 5)--><!--begin::Required Plugin(AdminLTE)-->
<script src="js/adminlte.js"></script>
<!--end::Required Plugin(AdminLTE)--><!--begin::OverlayScrollbars Configure-->
<script>
  const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
  const Default = {
    scrollbarTheme: 'os-theme-light',
    scrollbarAutoHide: 'leave',
    scrollbarClickScroll: true,
  };
  document.addEventListener('DOMContentLoaded', function() {
    const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
if (sidebarWrapper && OverlayScrollbarsGlobal?.OverlayScrollbars !== undefined) {
    OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
  scrollbars: {
theme: Default.scrollbarTheme,
        autoHide: Default.scrollbarAutoHide,
          clickScroll: Default.scrollbarClickScroll,
        },
      });
    }
  });
</script>
<!--end::OverlayScrollbars Configure-->
<!--end::Script-->
<!-- Agrega en tu cargar-facturas.inc.php: un div donde se mostrará la tabla en el modal -->
<!-- Dentro del modal #cfdiModal coloca este tbody -->

<script>
// IDs: xmlFile, zipFile, cfdiModal (modal), cfdiReviewBody, cfdiParseErrors
const xmlInput = document.getElementById('xmlFile');
const zipInput = document.getElementById('zipFile');
const cfdiModalEl = document.getElementById('cfdiModal');
const cfdiModal = new bootstrap.Modal(cfdiModalEl);
const cfdiReviewBody = document.getElementById('cfdiReviewBody');
const cfdiParseErrors = document.getElementById('cfdiParseErrors');

async function enviarArchivosParse() {
  const fd = new FormData();
  if (xmlInput && xmlInput.files.length > 0) {
    fd.append('xmlFile', xmlInput.files[0]);
  }
  if (zipInput && zipInput.files.length > 0) {
    fd.append('zipFile', zipInput.files[0]);
  }
  if (!fd.has('xmlFile') && !fd.has('zipFile')) {
    alert('Selecciona un XML o un ZIP primero.');
    return;
  }

  try {
    const res = await fetch('core/cargar-xml.php', {
      method: 'POST',
      body: fd
    });

    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error("⚠️ Respuesta cruda del servidor:", text);
      alert("El servidor devolvió un error. Revisa la consola (F12).");
      return;
    }

    if (!data.success && (!data.parsed || data.parsed.length === 0)) {
      cfdiParseErrors.innerText = data.message || JSON.stringify(data.errors || []);
      return;
    }

    // limpiar tabla
    cfdiReviewBody.innerHTML = '';
    cfdiParseErrors.innerText = '';

    // poblar filas
    data.parsed.forEach((item, idx) => {
      const tr = document.createElement('tr');
      const chk = document.createElement('input');
      chk.type = 'checkbox';
      chk.checked = true;
      chk.dataset.tmp = item._tmp_file;
      chk.dataset.index = idx;

      tr.innerHTML = `
        <td></td>
        <td>${idx+1}</td>
        <td>${item.uuid || ''}</td>
        <td>${item.fecha || ''}</td>
        <td>${item.emisor_rfc || ''}</td>
        <td>${item.receptor_rfc || ''}</td>
        <td>${item.subtotal || ''}</td>
        <td>${item.total || ''}</td>
        <td>${item.serie || ''}</td>
        <td>${item.folio || ''}</td>
        <td>${item.uuid ? '<span class="badge bg-success">UUID OK</span>' : '<span class="badge bg-warning">UUID faltante</span>'}</td>
      `;
      tr.children[0].appendChild(chk);
      tr.dataset.item = JSON.stringify(item);
      cfdiReviewBody.appendChild(tr);
    });

    if (data.errors && data.errors.length) {
      cfdiParseErrors.innerText = data.errors.join(' | ');
    }

    cfdiModal.show();

  } catch (err) {
    console.error(err);
    alert('Error al enviar archivos: ' + err.message);
  }
}

const btnOpenModal = document.querySelector('button[data-bs-target="#cfdiModal"]');
if (btnOpenModal) {
  btnOpenModal.addEventListener('click', (e) => {
    e.preventDefault();
    enviarArchivosParse();
  });
}

// Confirmar guardado
const btnConfirm = document.createElement('button');
btnConfirm.className = 'btn btn-primary';
btnConfirm.innerText = 'Registrar facturas';
btnConfirm.addEventListener('click', async () => {
  const rows = Array.from(cfdiReviewBody.querySelectorAll('tr'));
  const items = [];
  for (const r of rows) {
    const chk = r.querySelector('input[type=checkbox]');
    if (chk && chk.checked) {
      const obj = JSON.parse(r.dataset.item);
      obj._tmp_file = chk.dataset.tmp;
      items.push(obj);
    }
  }

  if (items.length === 0) {
    alert('Selecciona al menos una factura para registrar.');
    return;
  }

  const uuidRegex = /^[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{4}-[0-9A-Fa-f]{12}$/;
  for (const it of items) {
    if (!it.uuid || !uuidRegex.test(it.uuid)) {
      if (!confirm(`La factura con UUID "${it.uuid || '(vacío)'}" no tiene formato válido. ¿Deseas continuar?`)) {
        return;
      } else {
        break;
      }
    }
  }

  try {
    const res = await fetch('core/guardar-facturas.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({items})
    });

    const text = await res.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch (e) {
      console.error("⚠️ Respuesta cruda del servidor (guardar):", text);
      alert("El servidor devolvió un error al guardar. Revisa la consola.");
      return;
    }

    if (data.success) {
      alert('Facturas guardadas: ' + (data.inserted || []).length);
      location.reload();
    } else {
      alert('Error guardando: ' + JSON.stringify(data.errors || data.message));
    }
  } catch (err) {
    console.error(err);
    alert('Error al guardar: ' + err.message);
  }
});

const modalFooter = cfdiModalEl.querySelector('.modal-footer');
if (modalFooter) modalFooter.appendChild(btnConfirm);
</script>


</body>
<!--end::Body-->
</html>

