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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src=""></script>
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
      console.error("Respuesta cruda del servidor:", text);
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
      console.error(" Respuesta cruda del servidor (guardar):", text);
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

<script>
document.addEventListener('DOMContentLoaded', function () {
const viewFilesModal = document.getElementById('viewFilesModal');
if (viewFilesModal) {
viewFilesModal.addEventListener('show.bs.modal', function (event) {
// Elemento que activó el modal (la fila de la tabla)
const row = event.relatedTarget;

// Extraer información de los atributos data-*
const pdfPath = row.getAttribute('data-pdf-path');
const xmlPath = row.getAttribute('data-xml-path');
const uuid = row.getAttribute('data-uuid');

// Actualizar el título del modal
const modalTitle = viewFilesModal.querySelector('.modal-title');
modalTitle.textContent = 'Archivos de Factura: ' + uuid;

// Actualizar el visor de PDF
const pdfViewer = document.getElementById('pdf-viewer');
pdfViewer.src = pdfPath;

// Cargar y mostrar el contenido del XML
const xmlViewer = document.getElementById('xml-viewer');
xmlViewer.textContent = 'Cargando XML...';

fetch(xmlPath)
.then(response => {		if (!response.ok) {
		throw new Error('No se pudo cargar el archivo XML. Código de estado: ' + response.status);
		}
		return response.text();
	})
	.then(data => {
                    // Escapar caracteres especiales de HTML para mostrar el XML como texto plano
		const escapedXml = data.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
		xmlViewer.innerHTML = `<code class="language-xml">${escapedXml}</code>`;
})
	.catch(error => {
xmlViewer.textContent = 'Error al cargar el archivo XML. Verifique que la ruta sea correcta: ' + xmlPath;
		console.error('Error en fetch:', error);
});

// Asegurarse de que la pestaña de PDF esté activa al abrir
const pdfTab = document.querySelector('#pdf-tab');
if(pdfTab) {
const tab = new bootstrap.Tab(pdfTab);
	tab.show();
}
});

        // Limpiar el iframe al cerrar el modal para detener la carga
        viewFilesModal.addEventListener('hidden.bs.modal', function () {
            const pdfViewer = document.getElementById('pdf-viewer');
            pdfViewer.src = 'about:blank';
        });
}
});
</script>


<script>
document.addEventListener('DOMContentLoaded', () => {

    const modalSat = new bootstrap.Modal(document.getElementById('modalSAT'));
    const modalDescarga = new bootstrap.Modal(document.getElementById('modalDescarga'));

    // Autenticación con e.firma
    const formAutenticacion = document.getElementById('form-autenticacion-efirma');
    formAutenticacion.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(formAutenticacion);
        
        Swal.fire({
            title: 'Autenticando...',
            text: 'Por favor, espere mientras validamos su e.firma.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            const response = await fetch('core/descarga-sat.php?action=autenticar', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.message);
            }

            Swal.fire('¡Éxito!', `Autenticado correctamente para el RFC: ${result.rfc}`, 'success');
            modalSat.hide();
            modalDescarga.show();

        } catch (error) {
            Swal.fire('Error', error.message, 'error');
        }
    });


    // Descarga de CFDI desde el SAT
    const formDescarga = document.getElementById('form-descarga-sat');
    formDescarga.addEventListener('submit', async (e) => {
        e.preventDefault();

        const formData = new FormData(formDescarga);
        const data = Object.fromEntries(formData.entries());

        if (!data.fecha_inicio || !data.fecha_fin) {
            Swal.fire('Atención', 'Debe seleccionar una fecha de inicio y fin.', 'warning');
            return;
        }

        try {
            //Solicitar la descarga
            Swal.fire({
                title: 'Enviando Solicitud',
                text: 'Conectando con el SAT para solicitar la descarga...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const solicitarResponse = await fetch('core/descarga-sat.php?action=solicitar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const solicitarResult = await solicitarResponse.json();
            if (!solicitarResult.success) throw new Error(solicitarResult.message);
            
            const requestId = solicitarResult.requestId;

            // Verificar el estado periódicamente 
            await verificarSolicitud(requestId);

        } catch (error) {
            Swal.fire('Error en la solicitud', error.message, 'error');
        }
    });

    async function verificarSolicitud(requestId) {
        Swal.update({
            title: 'Solicitud Aceptada',
            text: `Verificando estado... (ID: ${requestId.substring(0, 15)}...)`
        });

        const interval = setInterval(async () => {
            try {
                const verificarResponse = await fetch('core/descarga-sat.php?action=verificar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ requestId })
                });
                const result = await verificarResponse.json();

                if (!result.success) {
                    throw new Error(result.message);
                }

                if (result.status === 3 && result.packageIds.length > 0) {
                    clearInterval(interval);
                    Swal.close();
                    // Descargar paquetes
                    await descargarPaquetes(result.packageIds);
                } else if (result.status === 5) { 
                    throw new Error('La solicitud fue rechazada o contiene un error.');
                }
                
            } catch (error) {
                clearInterval(interval);
                Swal.fire('Error de Verificación', error.message, 'error');
            }
        }, 15000); // Verificar cada 15 segundos
    }

    async function descargarPaquetes(packageIds) {
        Swal.fire({
            title: 'Descargando Paquetes',
            text: `Se encontraron ${packageIds.length} paquetes. Descargando 1 de ${packageIds.length}...`,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        for (let i = 0; i < packageIds.length; i++) {
            const packageId = packageIds[i];
            Swal.update({
                text: `Descargando paquete ${i + 1} de ${packageIds.length}...`
            });
            try {
                const descargarResponse = await fetch('core/descarga-sat.php?action=descargar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ packageId })
                });
                const result = await descargarResponse.json();
                if (!result.success) {
                    console.warn(`Error al descargar el paquete ${packageId}: ${result.message}`);
                }
            } catch (error) {
                console.warn(`Error de red al descargar el paquete ${packageId}: ${error.message}`);
            }
        }

        Swal.fire({
            icon: 'success',
            title: '¡Descarga Completada!',
            text: 'Todos los paquetes han sido descargados al servidor. Ahora deben ser procesados.',
            confirmButtonText: 'Excelente'
        }).then(() => {
            modalDescarga.hide();
            location.reload();
        });
    }
});
</script>

 </body>


