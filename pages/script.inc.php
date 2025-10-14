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
    // IDs: xmlFile, zipFile, cfdiModal (modal), cfdiReviewBody, cfdiParseErrors
    const xmlInput = document.getElementById('xmlFile');
    const zipInput = document.getElementById('zipFile');
    const cfdiModalEl = document.getElementById('cfdiModal');
    let cfdiModal = null;
    if (cfdiModalEl) {
      cfdiModal = new bootstrap.Modal(cfdiModalEl);
    }
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

        if (cfdiModal) {
          cfdiModal.show();
        }

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

    // También agregar evento al botón de carga ZIP
    const btnUploadZip = document.querySelector('form#form-manual button[data-bs-target="#cfdiModal"]');
    if (btnUploadZip) {
      btnUploadZip.addEventListener('click', (e) => {
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

    //------------------------------------------------------------------------------------------------------
    // descargar facturas desde sat 
    document.addEventListener('DOMContentLoaded', () => {
      const modalSat = new bootstrap.Modal(document.getElementById('modalSAT'));
      const modalDescarga = new bootstrap.Modal(document.getElementById('modalDescarga'));
      const formAutenticacion = document.getElementById('form-autenticacion-efirma');
      const formDescarga = document.getElementById('form-descarga-sat');
      const rfcInput = formDescarga.querySelector('input[name="rfc"]');
      let autenticadoRFC = null;

      // ---- AUTENTICAR EFIRMA ----
      formAutenticacion.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(formAutenticacion);

        Swal.fire({
          title: 'Autenticando...',
          text: 'Por favor, espere mientras validamos su e.firma.',
          allowOutsideClick: false,
          didOpen: () => Swal.showLoading()
        });

        try {
          const response = await fetch('core/autenticar-sat.php', {
            method: 'POST',
            body: formData
          });

          const result = await response.json();
          if (!result.success) {
            throw new Error(result.message);
          }

          autenticadoRFC = result.rfc;
          rfcInput.value = autenticadoRFC; // Asignamos el RFC al campo del formulario de descarga

          Swal.fire('¡Éxito!', `Autenticado correctamente para el RFC: ${autenticadoRFC}`, 'success');

          modalSat.hide();
          modalDescarga.show();
        } catch (error) {
          Swal.fire('Error', error.message, 'error');
        }
      });

      // ---- SOLICITAR DESCARGA ----
      formDescarga.addEventListener('submit', async (e) => {
        e.preventDefault();

        // --- INICIO DE LA CORRECCIÓN CLAVE ---
        // Construimos el objeto de datos manualmente para asegurar que los valores son correctos y limpios.
        const data = {
          tipo_descarga: formDescarga.querySelector('select[name="tipo_descarga"]').value.trim(),
          rfc: formDescarga.querySelector('input[name="rfc"]').value.trim(),
          fecha_inicio: formDescarga.querySelector('input[name="fecha_inicio"]').value,
          fecha_fin: formDescarga.querySelector('input[name="fecha_fin"]').value
        };
        // --- FIN DE LA CORRECCIÓN CLAVE ---

        // Validaciones de fechas
        if (!data.fecha_inicio || !data.fecha_fin) {
          Swal.fire('Atención', 'Debe seleccionar una fecha de inicio y fin.', 'warning');
          return;
        }

        const fechaInicio = new Date(data.fecha_inicio);
        const fechaFin = new Date(data.fecha_fin);

        if (fechaInicio >= fechaFin) {
          Swal.fire('Rango inválido', 'La fecha de inicio debe ser menor que la fecha final.', 'warning');
          return;
        }

        const diffTime = Math.abs(fechaFin - fechaInicio);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays > 31) {
          Swal.fire({
            icon: 'warning',
            title: 'Rango muy amplio',
            html: `El rango de <strong>${diffDays} días</strong> es demasiado amplio.<br>Use rangos de máximo 31 días.`,
            confirmButtonText: 'Entendido'
          });
          return;
        }

        // **IMPORTANTE**: Prueba con una fecha pasada o la fecha de hoy.
        // El SAT no permite fechas futuras.
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0); // Poner la hora a cero para comparar solo el día
        if (fechaInicio > hoy || fechaFin > hoy) {
          Swal.fire('Fecha inválida', 'Las fechas no pueden ser futuras.', 'warning');
          return;
        }


        // Envío de la solicitud
        try {
          Swal.fire({
            title: 'Enviando Solicitud...',
            html: `Conectando con el SAT...<br><small>Rango: ${data.fecha_inicio} al ${data.fecha_fin}</small>`,
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
          });

          console.log('Enviando los siguientes datos:', data); // Para depuración

          const response = await fetch('core/solicitar-descarga.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
          });

          const result = await response.json();

          if (!result.success) {
            throw new Error(result.message);
          }

          Swal.fire({
            icon: 'success',
            title: 'Solicitud enviada correctamente',
            html: `
                <p>El SAT ha recibido la solicitud.</p>
                <p><b>El SAT puede tardar en procesarla.</b></p>
                <p>La solicitud se registró en la lista y se actualizará automáticamente cuando haya cambios.</p>
                `,
            confirmButtonText: 'Entendido'
          }).then(() => {
            modalDescarga.hide();
            if (typeof cargarSolicitudes === 'function') {
              cargarSolicitudes();
            } else {
              location.reload();
            }
          });

        } catch (error) {
          Swal.fire('Error', error.message, 'error');
        }
      });
    });

    //contador de dias
    document.addEventListener('DOMContentLoaded', function() {
      const fechaInicio = document.getElementById('fecha_inicio');
      const fechaFin = document.getElementById('fecha_fin');
      const diasRango = document.getElementById('dias-rango');

      function actualizarDiasRango() {
        const inicio = fechaInicio.value;
        const fin = fechaFin.value;
        if (inicio && fin) {
          const dateInicio = new Date(inicio);
          const dateFin = new Date(fin);
          if (dateFin >= dateInicio) {
            const diffDays = Math.ceil((dateFin - dateInicio) / (1000 * 60 * 60 * 24)) + 1;
            diasRango.textContent = `${diffDays} día${diffDays === 1 ? '' : 's'} seleccionados`;
          } else {
            diasRango.textContent = 'Rango inválido';
          }
        } else {
          diasRango.textContent = '0 días seleccionados';
        }
      }

      fechaInicio.addEventListener('change', actualizarDiasRango);
      fechaFin.addEventListener('change', actualizarDiasRango);
    });
  </script>
<?php endif; ?>

<?php if (basename($_SERVER['REQUEST_URI'], '.php') === 'ver-peticiones' || (isset($_GET['pg']) && $_GET['pg'] === 'ver-peticiones')): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      console.log('🔧 Inicializando sistema de verificación v2.0...');

      const btnVerificarTodas = document.getElementById('btn-verificar'); 

      if (!btnVerificarTodas) {
        console.error('❌ No se encontró el botón con id="btn-verificar"');
        return;
      }

      console.log('✅ Botón principal encontrado, agregando funcionalidad...');

      // Función para mostrar los badges de estado
      function parseEstadoBadge(estado) {
        const map = {
          'pendiente': '<span class="badge bg-secondary"><i class="fas fa-clock me-1"></i>Pendiente</span>',
          'aceptada': '<span class="badge bg-info"><i class="fas fa-hourglass-half me-1"></i>Aceptada</span>',
          'terminada': '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Terminada</span>',
          'rechazada': '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Rechazada</span>',
          'error': '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Error</span>',
          'vencida': '<span class="badge bg-warning"><i class="fas fa-hourglass-end me-1"></i>Vencida</span>'
        };
        return map[estado] || `<span class="badge bg-dark">${estado}</span>`;
      }

      async function verificarConSAT(idSolicitud = null) {
        const payload = {};
        if (idSolicitud) {
          payload.id_solicitud = parseInt(idSolicitud, 10);
          console.log(`🔄 Verificando estado para solicitud individual: ${idSolicitud}`);
        } else {
          console.log('🔄 Verificando estado para TODAS las solicitudes pendientes...');
        }

        try {
          const response = await fetch('core/actualizar-estado-solicitud.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify(payload)
          });

          if (!response.ok) {
            throw new Error(`Error HTTP: ${response.status} - ${response.statusText}`);
          }

          const data = await response.json();
          console.log('📊 Respuesta del servidor:', data);
          return data;

        } catch (error) {
          console.error('❌ Error en la llamada de verificación:', error);
          return {
            success: false,
            message: error.message
          };
        }
      }

      // Función para actualizar una fila específica en la tabla
      function actualizarFilaTabla(idSolicitud, nuevoEstado) {
        const fila = document.querySelector(`tr[data-id="${idSolicitud}"]`);
        if (!fila) {
          console.warn('⚠️ No se encontró la fila para la solicitud:', idSolicitud);
          return;
        }

        // Actualizar columna de estado
        const estadoCol = fila.querySelector('.estado-col');
        if (estadoCol) {
          estadoCol.innerHTML = parseEstadoBadge(nuevoEstado);
        }

        const ultVerifCell = fila.cells[8];
        if (ultVerifCell) {
          ultVerifCell.textContent = new Date().toLocaleString();
        }

        console.log(`✅ Fila actualizada para la solicitud: ${idSolicitud}`);
      }

      btnVerificarTodas.addEventListener('click', async function() {
        console.log(' Iniciando verificación de todas las solicitudes...');

        const btnOriginalHTML = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<i class="fas fa-sync-alt fa-spin me-1"></i> Verificando...';

        const resultado = await verificarConSAT(); // No se pasa ID para verificar todas

        this.disabled = false;
        this.innerHTML = btnOriginalHTML;

        if (resultado.success) {
          Swal.fire({
            icon: 'success',
            title: 'Verificación Completada',
            text: resultado.message,
          });

          if (resultado.nuevos_estados) {
            for (const id in resultado.nuevos_estados) {
              actualizarFilaTabla(id, resultado.nuevos_estados[id]);
            }
          }
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error en la Verificación',
            text: resultado.message || 'Ocurrió un error desconocido.',
          });
        }
      });


      document.addEventListener('click', async function(e) {
        const btnIndividual = e.target.closest('.btn-verificar-individual'); // Usar una clase específica
        if (!btnIndividual) {
          return;
        }
        e.preventDefault();

        const idSolicitud = btnIndividual.getAttribute('data-id');
        const fila = btnIndividual.closest('tr');
        const estadoCol = fila ? fila.querySelector('.estado-col') : null;
        const estadoOriginalHTML = estadoCol ? estadoCol.innerHTML : '';

        console.log(`🔍 Iniciando verificación individual para: ${idSolicitud}`);

        // Mostrar estado de carga
        if (estadoCol) {
          estadoCol.innerHTML = '<span class="badge bg-warning"><i class="fas fa-sync fa-spin me-1"></i>Verificando...</span>';
        }
        btnIndividual.disabled = true;

        const resultado = await verificarConSAT(idSolicitud);

        btnIndividual.disabled = false;

        if (resultado.success && resultado.nuevos_estados && resultado.nuevos_estados[idSolicitud]) {
          const nuevoEstado = resultado.nuevos_estados[idSolicitud];
          actualizarFilaTabla(idSolicitud, nuevoEstado);
          Swal.fire({
            icon: 'success',
            title: 'Verificación Exitosa',
            text: `El estado de la solicitud #${idSolicitud} es ahora: ${nuevoEstado}`,
            timer: 2500,
            showConfirmButton: false
          });
        } else {
          if (estadoCol) {
            estadoCol.innerHTML = estadoOriginalHTML; // Restaurar en caso de error
          }
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: resultado.message || `No se pudo verificar la solicitud #${idSolicitud}.`,
          });
        }
      });
      // ---- MANEJO DEL BOTÓN DE DESCARGA DE PAQUETES ----
      const btnDescargar = e.target.closest('.btn-descargar-paquetes');
      if (btnDescargar) {
        (async function() {
          const fila = btnDescargar.closest('tr');
          const idSolicitud = fila.getAttribute('data-id');

          btnDescargar.disabled = true;
          const orig = btnDescargar.innerHTML;
          btnDescargar.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>';

          try {
            // Puedes elegir uno de los métodos según tu lógica, aquí se usa el primero (con descargar_paquetes flag)
            const resp = await fetch('core/actualizar-estado-solicitud.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                id_solicitud: parseInt(idSolicitud, 10),
                descargar_paquetes: true
              }) // Añadimos un flag para forzar la descarga
            });
            const data = await resp.json();

            if (data.success) {
              Swal.fire({
                icon: 'success',
                title: 'Descarga completada',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
              }).then(() => location.reload());
            } else {
              Swal.fire('Error', data.message || 'No se pudo descargar paquetes', 'error');
            }
          } catch (err) {
            Swal.fire('Error', 'Error de conexión al servidor', 'error');
          } finally {
            btnDescargar.disabled = false;
            btnDescargar.innerHTML = orig;
          }
        })();
      }

      document.addEventListener('click', async function(e) {
        // ---- PROCESAR PAQUETES ----
        const btnProcesar = e.target.closest('.btn-procesar-paquetes');
        if (btnProcesar) {
          const idSolicitud = btnProcesar.getAttribute('data-id');
          btnProcesar.disabled = true;
          const origHtml = btnProcesar.innerHTML;
          btnProcesar.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

          Swal.fire({
            title: 'Procesando Facturas',
            text: 'Extrayendo, guardando y generando PDFs. Esto puede tardar unos momentos...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
          });

          try {
            const response = await fetch('core/procesar-paquetes.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                id_solicitud: idSolicitud
              })
            });
            const result = await response.json();

            if (result.success) {
              Swal.fire({
                icon: 'success',
                title: '¡Proceso Exitoso!',
                text: result.message,
              }).then(() => {
                window.location.href = 'panel?pg=cargar-facturas';
              });
            } else {
              throw new Error(result.message);
            }
          } catch (error) {
            Swal.fire('Error', error.message, 'error');
          } finally {
            btnProcesar.disabled = false;
            btnProcesar.innerHTML = origHtml;
          }
        }
      });

    });

    console.log('✅ Sistema de verificación y procesamiento v2.2 inicializado correctamente.');
  </script>
<?php endif; ?>


</body>

</html>