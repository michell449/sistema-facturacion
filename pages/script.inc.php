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
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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

        // llenar filas
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
          const row = event.relatedTarget;

          // Extraer información de los atributos data
          const pdfPath = row.getAttribute('data-pdf-path');
          const xmlPath = row.getAttribute('data-xml-path');
          const uuid = row.getAttribute('data-uuid');

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

      formDescarga.addEventListener('submit', async (e) => {
        e.preventDefault();

        const data = {
          tipo_descarga: formDescarga.querySelector('select[name="tipo_descarga"]').value.trim(),
          rfc: formDescarga.querySelector('input[name="rfc"]').value.trim(),
          fecha_inicio: formDescarga.querySelector('input[name="fecha_inicio"]').value,
          fecha_fin: formDescarga.querySelector('input[name="fecha_fin"]').value
        };

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

        // El SAT no permite fechas futuras.
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0); // Poner la hora a cero para comparar solo el día
        if (fechaInicio > hoy || fechaFin > hoy) {
          Swal.fire('Fecha inválida', 'Las fechas no pueden ser futuras.', 'warning');
          return;
        }

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
    //------------------------------------------------------------------------------------------------------
    //paginacion
    document.addEventListener("DOMContentLoaded", () => {
      cargarFacturas(1);

      // Delegación de eventos para los botones de paginación
      document.getElementById("facturas-cargadas").addEventListener("click", (e) => {
        if (e.target.matches(".page-link")) {
          e.preventDefault();
          const pagina = e.target.dataset.page;
          if (pagina) cargarFacturas(pagina);
        }
      });
    });

    function cargarFacturas(pagina) {
      fetch(`../../app-m/core/listar-facturas.php?pagina=${pagina}`)
        .then((res) => res.text())
        .then((html) => {
          document.getElementById("facturas-cargadas").innerHTML = html;
        })
        .catch((err) => {
          console.error("Error cargando facturas:", err);
        });
    }
  </script>
<?php endif; ?>

<?php if (basename($_SERVER['REQUEST_URI'], '.php') === 'ver-peticiones' || (isset($_GET['pg']) && $_GET['pg'] === 'ver-peticiones')): ?>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      console.log('🔧 Inicializando sistema de peticiones v3.2 (Unificado y Corregido)...');

      // Usamos el cuerpo de la tabla para delegar los eventos
      const tablaPeticionesBody = document.querySelector('#tbody-solicitudes');

      if (!tablaPeticionesBody) {
        console.error(' No se encontró el cuerpo de la tabla (tbody) con id="tbody-solicitudes". Los botones no funcionarán.');
        return;
      }

      // Un único manejador de eventos para todos los clics en la tabla
      tablaPeticionesBody.addEventListener('click', async function(event) {

        const botonClickeado = event.target.closest('a.btn, button.btn');

        if (!botonClickeado || !botonClickeado.dataset.id) {
          return; // No se hizo clic en un botón de acción con data-id
        }

        event.preventDefault();
        const idSolicitud = botonClickeado.dataset.id;

        // Si el botón ya está deshabilitado o tiene spinner, ignorar
        if (botonClickeado.disabled) {
          return;
        }

        if (botonClickeado.classList.contains('btn-verificar-individual')) {
          const iconoOriginal = botonClickeado.innerHTML;

          botonClickeado.disabled = true;
          botonClickeado.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

          try {
            const response = await fetch('core/verificar-descarga.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                id_solicitud: idSolicitud
              })
            });
            const text = await response.text();
            console.log('Respuesta cruda:', text);
            let result;
            try {
              result = JSON.parse(text);
            } catch (e) {
              console.error('Respuesta no es JSON válido');
              Swal.fire('Error', 'Respuesta no válida del servidor (ver consola).', 'error');
              return;
            }


            if (!response.ok || !result.success) {
              // Manejo de errores HTTP (400, 500) que sí devuelven JSON
              throw new Error(result.message || 'Error al iniciar verificación');
            }

            Swal.fire({
              icon: 'info',
              title: 'Verificación Iniciada',
              text: result.message + ' El estado se actualizará automáticamente.',
              timer: 4000
            });

            botonClickeado.disabled = false;
            botonClickeado.innerHTML = iconoOriginal;

            // Recarga la tabla para ver el estado actualizado
            setTimeout(() => location.reload(), 2000);

          } catch (error) {
            Swal.fire('Error', 'No se pudo iniciar la verificación: ' + error.message, 'error');
            botonClickeado.disabled = false;
            botonClickeado.innerHTML = iconoOriginal;
          }
          return;
        }

        // --- Lógica para DESCARGAR ---
        if (botonClickeado.classList.contains('btn-descargar-paquetes')) {
          botonClickeado.disabled = true;
          botonClickeado.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

          Swal.fire({
            title: 'Descargando Paquetes',
            text: 'Conectando con el SAT, esto puede tardar un momento...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
          });

          try {
            const response = await fetch('core/descargar-paquete-sat.php', { // CORREGIDO: Usar el script de descarga
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                id_solicitud: idSolicitud
              })
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Error en el servidor');

            Swal.fire('¡Éxito!', result.message, 'success').then(() => location.reload());

          } catch (error) {
            Swal.fire('Error', 'No se pudieron descargar los paquetes: ' + error.message, 'error');
            botonClickeado.disabled = false;
            botonClickeado.innerHTML = '<i class="fas fa-download"></i>';
          }
          return;
        }

        // --- Lógica para PROCESAR ---
        if (botonClickeado.classList.contains('btn-procesar-paquetes')) {
          botonClickeado.disabled = true;
          botonClickeado.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

          Swal.fire({
            title: 'Procesando Facturas',
            text: 'Extrayendo, guardando y generando PDFs. Por favor, espere...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
          });

          try {
            const response = await fetch('core/procesar_paquetes.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json'
              },
              body: JSON.stringify({
                id_solicitud: idSolicitud
              })
            });
            const result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.message || 'Error desconocido durante el procesamiento.');

            Swal.fire({
              icon: 'success',
              title: '¡Proceso Completado!',
              text: result.message,
            }).then(() => {
              location.reload();
            });

          } catch (error) {
            Swal.fire('Error', 'No se pudieron procesar los paquetes: ' + error.message, 'error');
            botonClickeado.disabled = false;
            botonClickeado.innerHTML = '<i class="fas fa-cogs"></i>';
          }
          return;
        }
      });

      console.log(' Sistema de peticiones v3.2 listo y unificado.');
    });
    // Usando jQuery
    $(document).ready(function() {
      // Función para reordenar los IDs visuales (primera columna)
      function updateDisplayIds() {
        $('#tbody-solicitudes tr').each(function(index) {
          // El índice es base 0, por lo que el ID a mostrar es index + 1
          $(this).find('td:first').text(index + 1);
        });
      }

      // Delegación de eventos para el botón de eliminar por su clase CSS
      $('#tbody-solicitudes').on('click', '.btn-eliminar-solicitud', function(e) {
        e.preventDefault();
        const idSolicitud = $(this).data('id');
        const row = $(this).closest('tr');

        if (confirm('¿Estás seguro de que deseas eliminar la solicitud Rechazada con ID ' + idSolicitud + '? Esta acción es irreversible y eliminará los archivos de paquete asociados.')) {
          $.ajax({
            url: 'core/eliminar-solicitud.php',
            type: 'POST',
            dataType: 'json',
            data: {
              id: idSolicitud
            },
            beforeSend: function() {
              row.find('button, a').prop('disabled', true).addClass('disabled');
              row.css('opacity', 0.5);
            },
            success: function(response) {
              if (response.success) {
                alert('Éxito: ' + response.message);

                row.remove();

                // Reordenar los IDs
                updateDisplayIds();

              } else {
                alert('Error al eliminar: ' + response.message);
              }
            },
            error: function(xhr, status, error) {
              alert('Error de comunicación con el servidor. Por favor, revisa la consola (F12) para más detalles.');
              console.error('Error AJAX:', status, error, xhr.responseText);
            },
            complete: function() {
              if (row.length && row.parent().length) {
                row.find('button, a').prop('disabled', false).removeClass('disabled');
                row.css('opacity', 1);
              }
            }
          });
        }
      });
    });
  </script>
<?php endif; ?>


</body>

</html>