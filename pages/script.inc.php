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


<?php if (basename($_SERVER['REQUEST_URI'], '.php') === 'cargar-facturas' || (isset($_GET['pg']) && $_GET['pg'] === 'cargar-facturas')): ?>
  <script>
    //Cargar xml manual
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
        const res = await fetch('core/lista-facturas-cargadas.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            items
          })
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

    async function cargarFacturas() {
  const tbody = document.getElementById('facturas-cargadas');
  if (!tbody) return;

  tbody.innerHTML = '<tr><td colspan="13">Cargando...</td></tr>';

  try {
    const res = await fetch('core/lista-facturas-cargadas.php');
    const data = await res.json();

    tbody.innerHTML = ''; // limpiar

    if (data.success && data.data.length > 0) {
      data.data.forEach(row => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>${row.uuid}</td>
          <td>${row.serie}</td>
          <td>${row.folio}</td>
          <td>${row.emisor_rfc}</td>
          <td>${row.receptor_rfc}</td>
          <td>${row.emisor_nombre}</td>
          <td>${row.fecha}</td>
          <td>${row.receptor_uso_cfdi}</td>
          <td>${row.subtotal}</td>
          <td>${row.total}</td>
          <td>${row.forma_pago}</td>
          <td>${row.metodo_pago}</td>
          <td>
            <a href="uploads/pdf/${row.pdf_file}" class="text-danger" download title="Descargar PDF">
              <i class="fas fa-file-pdf fa-lg"></i> pdf
            </a>
            <a href="uploads/xml/${row.xml_file}" class="text-primary ms-2" download title="Descargar XML">
              <i class="fas fa-file-code fa-lg"></i> xml
            </a>
          </td>
        `;
        tbody.appendChild(tr);
      });
    } else {
      tbody.innerHTML = '<tr><td colspan="13">No se encontraron facturas</td></tr>';
    }
  } catch (err) {
    console.error('Error cargando facturas:', err);
    tbody.innerHTML = '<tr><td colspan="13">Error al cargar las facturas</td></tr>';
  }
}

// Llamar al cargar la página
document.addEventListener('DOMContentLoaded', cargarFacturas);
    //fin para carga manual de xml

    //-----------------------------------------------------------------------------------------------------------------------------------------------------------------

    // Script cargar cfdi desde sat 

    document.addEventListener('DOMContentLoaded', () => {
      const modalSat = new bootstrap.Modal(document.getElementById('modalSAT'));
      const modalDescarga = new bootstrap.Modal(document.getElementById('modalDescarga'));
      let verificationInterval = null;
      let currentRequestId = null;
      let autenticadoRFC = null;

      const formAutenticacion = document.getElementById('form-autenticacion-efirma');
      const formDescarga = document.getElementById('form-descarga-sat');
      const tipoDescargaSelect = document.querySelector('select[name="tipo_descarga"]');
      const rfcInput = document.querySelector('input[name="rfc"]');

      function actualizarRFCPorTipo(tipo) {
        if (!autenticadoRFC) return;

        if (tipo === 'emitidas' || tipo === 'recibidas') {
          rfcInput.value = autenticadoRFC;
        }
      }

      tipoDescargaSelect.addEventListener('change', (e) => {
        actualizarRFCPorTipo(e.target.value);
      });

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
          const response = await fetch('/core/autenticar_sat.php', {
            method: 'POST',
            body: formData
          });

          const result = await response.json();

          if (!result.success) {
            throw new Error(result.message);
          }

          autenticadoRFC = result.rfc; // Guardar RFC autenticado

          Swal.fire('¡Éxito!', `Autenticado correctamente para el RFC: ${autenticadoRFC}`, 'success');

          modalSat.hide();
          modalDescarga.show();

          // Prellenar RFC al mostrar el modal
          const tipoActual = tipoDescargaSelect.value;
          actualizarRFCPorTipo(tipoActual);

        } catch (error) {
          Swal.fire('Error', error.message, 'error');
        }
      });

      formDescarga.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (verificationInterval) {
          clearInterval(verificationInterval);
          verificationInterval = null;
        }

        const formData = new FormData(formDescarga);
        const data = Object.fromEntries(formData.entries());

        const fechaInicio = new Date(data.fecha_inicio);
        const fechaFin = new Date(data.fecha_fin);

        if (fechaInicio >= fechaFin) {
          Swal.fire('Rango de fechas inválido', 'La fecha de inicio debe ser menor que la fecha final.', 'warning');
          return;
        }

        if (!data.fecha_inicio || !data.fecha_fin) {
          Swal.fire('Atención', 'Debe seleccionar una fecha de inicio y fin.', 'warning');
          return;
        }

        const diffTime = Math.abs(fechaFin - fechaInicio);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays > 31) {
          Swal.fire({
            icon: 'warning',
            title: 'Rango muy amplio',
            html: `El rango de <strong>${diffDays} días</strong> es demasiado amplio y puede causar timeouts.<br><br>
               <strong>Recomendación:</strong> Divida la consulta en rangos máximos de 31 días.`,
            confirmButtonText: 'Entendido'
          });
          return;
        }

        try {
          Swal.fire({
            title: 'Enviando Solicitud',
            html: `Conectando con el SAT...<br>
               <small>Rango: ${data.fecha_inicio} al ${data.fecha_fin} (${diffDays} días)</small>`,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
          });

          const solicitarResponse = await fetch('core/cargar-cfdi-sat.php?action=solicitar', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
          });

          const solicitarResult = await solicitarResponse.json();

          if (!solicitarResult.success) {
            throw new Error(solicitarResult.message);
          }

          currentRequestId = solicitarResult.requestId;

          if (!currentRequestId || currentRequestId.length < 10) {
            throw new Error("No se recibió un ID de solicitud válido del SAT.");
          }

          await verificarSolicitud(currentRequestId, diffDays);

        } catch (error) {
          Swal.fire('Error en la solicitud', error.message, 'error');
        }
      });

      async function verificarSolicitud(requestId, diffDays) {
        const estadoSolicitudMap = {
          1: "Aceptada",
          2: "En proceso",
          3: "Terminada",
          4: "Error",
          5: "Rechazada",
          6: "Vencida"
        };

        const codEstatusMap = {
          300: "Usuario No Válido",
          301: "XML Mal Formado",
          302: "Sello Mal Formado",
          303: "Sello no corresponde con RFC",
          304: "Certificado Revocado o Caduco",
          305: "Certificado Inválido",
          5000: "Solicitud recibida con éxito",
          5003: "Tope máximo de elementos",
          5004: "No se encontró la información",
          5011: "Límite de descargas por día"
        };

        let attempts = 0;
        const maxAttempts = 30;
        const checkInterval = 30000;
        let startTime = Date.now();

        Swal.fire({
          title: 'Verificando Estado SAT',
          html: `Consulta en proceso...<br><small>ID: ${requestId.substring(0, 20)}...</small>`,
          allowOutsideClick: false,
          showConfirmButton: true,
          confirmButtonText: 'Cancelar Consulta',
          didOpen: () => Swal.showLoading()
        }).then((result) => {
          if (result.isConfirmed && verificationInterval) {
            clearInterval(verificationInterval);
            cancelarSolicitud(requestId);
          }
        });

        const performVerification = async () => {
          if (attempts >= maxAttempts) {
            clearInterval(verificationInterval);
            Swal.fire({
              icon: 'warning',
              title: 'Tiempo de espera agotado',
              html: `El SAT no respondió en el tiempo esperado.<br><br>
           <strong>Recomendaciones:</strong><br>
           • Intente con un rango más pequeño<br>
           • Espere unos minutos<br>
           • El SAT puede estar saturado`
            });
            return;
          }

          attempts++;
          const elapsedMinutes = Math.floor((Date.now() - startTime) / 60000);

          try {
            const verificarResponse = await fetch('core/cargar-cfdi-sat.php?action=verificar', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                requestId
              })
            });

            const result = await verificarResponse.json();

            if (!result.success) throw new Error(result.message);

            const estadoDescripcion = estadoSolicitudMap[result.estadoSolicitud] || "❓ Estado desconocido";
            const codigoDescripcion = codEstatusMap[result.status_code] || "⚠️ Código desconocido";

            Swal.update({
              html: `
            <strong>Estado:</strong> ${estadoDescripcion}<br>
            <strong>Código SAT:</strong> ${result.status_code || '-'}<br>
            <small>${codigoDescripcion}</small><br><br>
            <small>ID: ${requestId.substring(0, 20)}...</small><br>
            <small>Intento: ${attempts}/${maxAttempts}</small><br>
            <small>Tiempo transcurrido: ${elapsedMinutes} min</small>
          `
            });

            if (result.is_finished) {
              clearInterval(verificationInterval);

              if (result.has_packages && result.packageIds?.length > 0) {
                Swal.close();
                await descargarPaquetes(result.packageIds);
              } else {
                Swal.fire({
                  icon: 'info',
                  title: 'Consulta Finalizada',
                  html: `El SAT completó la consulta pero no se encontraron CFDI.<br><br>
               Estado final: <strong>${estadoDescripcion}</strong><br>
               Detalle: ${codigoDescripcion}`,
                  confirmButtonText: 'Entendido'
                });
              }
            }

          } catch (error) {
            clearInterval(verificationInterval);
            Swal.fire({
              icon: 'error',
              title: 'Error de Verificación',
              html: `No se pudo verificar con el SAT.<br><br><strong>Error:</strong> ${error.message}`,
              confirmButtonText: 'Entendido'
            });
          }
        };

        await performVerification();
        verificationInterval = setInterval(performVerification, checkInterval);
      }

      async function cancelarSolicitud(requestId) {
        try {
          const response = await fetch('core/cargar-cfdi-sat.php?action=cancelar', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              requestId
            })
          });

          const result = await response.json();

          Swal.fire({
            icon: result.success ? 'success' : 'warning',
            title: result.success ? 'Consulta Cancelada' : 'Atención',
            text: result.message,
            confirmButtonText: 'Entendido'
          });

        } catch (error) {
          console.error("Error al cancelar:", error);
          Swal.fire('Error', 'No se pudo cancelar la consulta.', 'error');
        }
      }

      async function descargarPaquetes(packageIds) {
        const downloadResults = {
          success: [],
          failed: []
        };

        for (let i = 0; i < packageIds.length; i++) {
          const packageId = packageIds[i];

          Swal.fire({
            title: `Descargando Paquetes`,
            html: `Procesando paquete <strong>${i + 1} de ${packageIds.length}</strong><br>
               <small>ID: ${packageId.substring(0, 20)}...</small>`,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
          });

          try {
            const descargarResponse = await fetch('core/cargar-cfdi-sat.php?action=descargar', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                packageId
              })
            });

            const result = await descargarResponse.json();

            if (result.success) {
              downloadResults.success.push({
                packageId,
                file: result.file
              });
            } else {
              downloadResults.failed.push({
                packageId,
                error: result.message
              });
            }

          } catch (error) {
            downloadResults.failed.push({
              packageId,
              error: error.message
            });
          }
        }

        let resultMessage = `Se descargaron <strong>${downloadResults.success.length}</strong> paquetes exitosamente.`;

        if (downloadResults.failed.length > 0) {
          resultMessage += `<br><br>Fallaron <strong>${downloadResults.failed.length}</strong> paquetes:`;
          downloadResults.failed.forEach(failed => {
            resultMessage += `<br>• ${failed.packageId}: ${failed.error}`;
          });
        }

        Swal.fire({
          icon: downloadResults.failed.length === 0 ? 'success' : 'warning',
          title: downloadResults.failed.length === 0 ? '¡Descarga Completada!' : 'Descarga Parcial',
          html: resultMessage,
          confirmButtonText: 'Continuar'
        }).then(() => {
          location.reload();
        });
      }

      modalDescarga._element.addEventListener('hidden.bs.modal', () => {
        if (verificationInterval) {
          clearInterval(verificationInterval);
          verificationInterval = null;
        }
        currentRequestId = null;
      });
    });


    //fin carga desdde cfdi

    //-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------


    // convertir xml a pdf
    document.addEventListener('DOMContentLoaded', function() {
      const viewFilesModal = document.getElementById('viewFilesModal');
      if (viewFilesModal) {
        viewFilesModal.addEventListener('show.bs.modal', function(event) {
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
            .then(response => {
              if (!response.ok) {
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
          if (pdfTab) {
            const tab = new bootstrap.Tab(pdfTab);
            tab.show();
          }
        });

        // Limpiar el iframe al cerrar el modal para detener la carga
        viewFilesModal.addEventListener('hidden.bs.modal', function() {
          const pdfViewer = document.getElementById('pdf-viewer');
          pdfViewer.src = 'about:blank';
        });
      }
    });
    //fin de convertir xml a pdf
  </script>
<?php endif; ?>

</body>

</html>