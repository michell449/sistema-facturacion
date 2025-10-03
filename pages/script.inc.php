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

    // Script cargar cfdi desde sat - VERSIÓN MEJORADA
    document.addEventListener('DOMContentLoaded', () => {
      const modalSat = new bootstrap.Modal(document.getElementById('modalSAT'));
      const modalDescarga = new bootstrap.Modal(document.getElementById('modalDescarga'));
      let verificationInterval = null;
      let currentRequestId = null;

      // Autenticación con e.firma (sin cambios)
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
          const response = await fetch('core/cargar-cfdi-sat.php?action=autenticar', {
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

      // Descarga de CFDI desde el SAT - MEJORADO
      const formDescarga = document.getElementById('form-descarga-sat');
      formDescarga.addEventListener('submit', async (e) => {
        e.preventDefault();

        // Detener cualquier verificación previa
        if (verificationInterval) {
          clearInterval(verificationInterval);
          verificationInterval = null;
        }

        const formData = new FormData(formDescarga);
        const data = Object.fromEntries(formData.entries());

        const fechaInicio = new Date(data.fecha_inicio);
        const fechaFin = new Date(data.fecha_fin);

        console.log("Datos enviados:", data);

        if (fechaInicio >= fechaFin) {
          Swal.fire('Rango de fechas inválido', 'La fecha de inicio debe ser menor que la fecha final.', 'warning');
          return;
        }

        if (!data.fecha_inicio || !data.fecha_fin) {
          Swal.fire('Atención', 'Debe seleccionar una fecha de inicio y fin.', 'warning');
          return;
        }

        // Validar rango máximo de 31 días
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
          // Solicitar la descarga
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
          console.log("Respuesta de 'solicitar':", solicitarResult);

          if (!solicitarResult.success) {
            throw new Error(solicitarResult.message);
          }

          currentRequestId = solicitarResult.requestId;
          console.log("Request ID recibido del SAT:", currentRequestId);

          if (!currentRequestId || currentRequestId.length < 10) {
            throw new Error("No se recibió un ID de solicitud válido del SAT.");
          }

          // Iniciar verificación mejorada
          await verificarSolicitudMejorado(currentRequestId, diffDays);

        } catch (error) {
          Swal.fire('Error en la solicitud', error.message, 'error');
        }
      });

      // Función de verificación MEJORADA
      async function verificarSolicitudMejorado(requestId, diffDays) {
        console.log("Iniciando verificación mejorada con requestId:", requestId);

        let attempts = 0;
        const maxAttempts = 30; // Máximo 15 minutos (30 intentos * 30 segundos)
        const checkInterval = 30000; // 30 segundos
        let startTime = Date.now();

        Swal.fire({
          title: 'Verificando Estado SAT',
          html: `⌛ Consulta en proceso...<br>
                   <small>ID: ${requestId.substring(0, 20)}...</small><br>
                   <small>Rango: ${diffDays} días | Intento: 1/${maxAttempts}</small>`,
          allowOutsideClick: false,
          showConfirmButton: true,
          confirmButtonText: 'Cancelar Consulta',
          showCancelButton: false,
          didOpen: () => {
            Swal.showLoading();
          }
        }).then((result) => {
          if (result.isConfirmed) {
            // Usuario canceló manualmente
            if (verificationInterval) {
              clearInterval(verificationInterval);
              verificationInterval = null;
            }
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
                           <strong>Solución:</strong><br>
                           • Intente con un rango de fechas más pequeño<br>
                           • Espere unos minutos e intente verificar nuevamente<br>
                           • El SAT puede estar congestionado`,
              confirmButtonText: 'Entendido'
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
            console.log(`Intento ${attempts}/${maxAttempts}:`, result);

            if (!result.success) {
              throw new Error(result.message);
            }

            // Actualizar interfaz con información detallada
            Swal.update({
              html: `⌛ ${result.message}<br>
                           <small>ID: ${requestId.substring(0, 20)}...</small><br>
                           <small>Rango: ${diffDays} días | Intento: ${attempts}/${maxAttempts}</small><br>
                           <small>Tiempo transcurrido: ${elapsedMinutes} min</small><br>
                           <small>Estado: ${result.status_code} - ${result.message}</small>`
            });

            // Lógica mejorada para determinar si continuar
            if (result.is_finished) {
              clearInterval(verificationInterval);

              if (result.has_packages && result.packageIds.length > 0) {
                Swal.close();
                await descargarPaquetes(result.packageIds);
              } else {
                Swal.fire({
                  icon: 'info',
                  title: 'Consulta Finalizada',
                  html: `El SAT completó la consulta pero <strong>no se encontraron CFDI</strong> en el período seleccionado.<br><br>
                                   <strong>Posibles causas:</strong><br>
                                   • No hay CFDI en el rango de fechas<br>
                                   • Los CFDI pueden estar cancelados<br>
                                   • El RFC no emitió/recibió CFDI en ese período`,
                  confirmButtonText: 'Entendido'
                });
              }
              return;
            }

            // Si ha pasado mucho tiempo y sigue en proceso, sugerir cancelar
            if (elapsedMinutes > 10 && attempts > 15) {
              Swal.update({
                html: `⚠️ El SAT está tardando más de lo usual<br>
                               <small>ID: ${requestId.substring(0, 20)}...</small><br>
                               <small>Tiempo transcurrido: ${elapsedMinutes} min</small><br><br>
                               <strong>Recomendación:</strong> Cancele e intente con un rango más pequeño`,
                showConfirmButton: true,
                confirmButtonText: 'Cancelar Consulta'
              });
            }

          } catch (error) {
            console.error("Error en verificación:", error);
            clearInterval(verificationInterval);

            Swal.fire({
              icon: 'error',
              title: 'Error de Verificación',
              html: `No se pudo verificar el estado con el SAT.<br><br>
                           <strong>Error:</strong> ${error.message}<br><br>
                           <strong>Solución:</strong><br>
                           • Verifique su conexión a internet<br>
                           • Espere unos minutos e intente nuevamente<br>
                           • El servicio del SAT puede estar temporalmente no disponible`,
              confirmButtonText: 'Entendido'
            });
          }
        };

        // Realizar primera verificación inmediatamente
        await performVerification();

        // Configurar intervalo para verificaciones posteriores
        verificationInterval = setInterval(performVerification, checkInterval);

        // Timeout global de respaldo
        setTimeout(() => {
          if (verificationInterval) {
            clearInterval(verificationInterval);
            verificationInterval = null;

            if (Swal.isVisible()) {
              Swal.fire({
                icon: 'warning',
                title: 'Consulta Excedió el Tiempo Límite',
                html: `La consulta ha superado el tiempo máximo de espera.<br><br>
                               <strong>Recomendaciones:</strong><br>
                               • Divida el rango de fechas en períodos más pequeños<br>
                               • Intente nuevamente en unos minutos<br>
                               • Consulte períodos de máximo 15 días para mejor rendimiento`,
                confirmButtonText: 'Entendido'
              });
            }
          }
        }, maxAttempts * checkInterval + 60000);
      }

      // Función para cancelar solicitud
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

      // Función descargarPaquetes (sin cambios mayores)
      async function descargarPaquetes(packageIds) {
        console.log("Iniciando descarga de paquetes:", packageIds);

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
              console.log(`Paquete ${packageId} descargado exitosamente`);
            } else {
              downloadResults.failed.push({
                packageId,
                error: result.message
              });
              console.warn(`Error al descargar ${packageId}:`, result.message);
            }

          } catch (error) {
            downloadResults.failed.push({
              packageId,
              error: error.message
            });
            console.error(`Error de red al descargar ${packageId}:`, error);
          }
        }

        // Mostrar resumen de descargas
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
          // Recargar la página para mostrar los nuevos archivos
          location.reload();
        });
      }

      // Limpiar intervalos cuando se cierre el modal
      modalDescarga.addEventListener('hidden.bs.modal', () => {
        if (verificationInterval) {
          clearInterval(verificationInterval);
          verificationInterval = null;
        }
        currentRequestId = null;
      });
    });

    // El resto de tu script (XML a PDF, etc.) permanece igual...
  </script>
<?php endif; ?>

</body>

</html>