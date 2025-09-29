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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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

<script>
const xmlInput = document.getElementById('xmlFile');
const zipInput = document.getElementById('zipFile');
const cfdiModalEl = document.getElementById('cfdiModal');
const cfdiModal = new bootstrap.Modal(cfdiModalEl);
const cfdiReviewBody = document.getElementById('cfdiReviewBody');
const cfdiParseErrors = document.getElementById('cfdiParseErrors');
const manualUploadButtons = document.querySelectorAll('button[data-bs-target="#cfdiModal"]');

async function enviarArchivosParse() {
    const fd = new FormData();
    if (xmlInput && xmlInput.files.length > 0) {
        for(const file of xmlInput.files) fd.append('xmlFile[]', file);
    }
    if (zipInput && zipInput.files.length > 0) {
        for(const file of zipInput.files) fd.append('zipFile[]', file);
    }
    
    if (fd.entries().next().done) {
        Swal.fire('Atención', 'Debes seleccionar al menos un archivo XML o ZIP.', 'warning');
        return;
    }

    // Mostrar el modal y el spinner
    cfdiReviewBody.innerHTML = '<tr><td colspan="11" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></td></tr>';
    cfdiParseErrors.innerText = '';
    cfdiModal.show();

    try {
        const res = await fetch(`${BASE_URL}core/cargar-xml.php`, {
            method: 'POST',
            body: fd
        });

        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error(" El servidor no devolvió un JSON válido. Respuesta cruda:", text);
            Swal.fire("Error del Servidor", "La respuesta no es un JSON. Revisa la consola (F12) y la pestaña Network para ver el error de PHP.", "error");
            return;
        }

        cfdiReviewBody.innerHTML = '';
        if (data.parsed && data.parsed.length > 0) {
        }
    } catch (err) {
        console.error(err);
        Swal.fire('Error de Conexión', 'No se pudo contactar al servidor: ' + err.message, 'error');
    }
}

manualUploadButtons.forEach(btn => {
    btn.addEventListener('click', (e) => {
        e.preventDefault();
        enviarArchivosParse();
    });
});


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

//script para cargar archivos xml y php

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Variable para almacenar los datos temporalmente
    let parsedCfdiData = [];

    // Función para enviar los archivos 
    async function enviarArchivosParse() {
        const form = document.getElementById('form-manual');
        const zipFileInput = document.getElementById('zipFile');
        const xmlFileInput = document.getElementById('xmlFile');
        
        const formData = new FormData();
        
        if (xmlFileInput.files.length > 0) {
            for(const file of xmlFileInput.files) {
        formData.append('xmlFile[]', file);
            }
        }
        if (zipFileInput.files.length > 0) {
            for(const file of zipFileInput.files) {
                formData.append('zipFile[]', file);
            }
        }

        if (formData.entries().next().done) {
            Swal.fire('Atención', 'Debes seleccionar al menos un archivo XML o ZIP.', 'warning');
            return;
        }

        const cfdiModal = new bootstrap.Modal(document.getElementById('cfdiModal'));
        const reviewBody = document.getElementById('cfdiReviewBody');
        const errorsDiv = document.getElementById('cfdiParseErrors');
        
        reviewBody.innerHTML = '<tr><td colspan="8" class="text-center"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></td></tr>';
        errorsDiv.style.display = 'none';
        errorsDiv.innerHTML = '';
        cfdiModal.show();
        
        try {
            const response = await fetch('../../app-m/core/cargar-xml.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`Error del servidor: ${response.statusText}`);
            }

            const result = await response.json();
            parsedCfdiData = result.parsed || []; // Guardar datos en la variable global
            reviewBody.innerHTML = ''; // Limpiar el spinner

            if (result.errors && result.errors.length > 0) {
                errorsDiv.innerHTML = '<strong>Se encontraron errores:</strong><ul>' + result.errors.map(e => `<li>${e}</li>`).join('') + '</ul>';
                errorsDiv.style.display = 'block';
            }
            
            if (parsedCfdiData.length > 0) {
                parsedCfdiData.forEach((cfdi, index) => {
                    const row = `
                        <tr>
                            <td><input type="checkbox" class="cfdi-checkbox" data-index="${index}"></td>
                            <td>${index + 1}</td>
                            <td>${cfdi.uuid.substring(0, 8)}...</td>
                            <td>${cfdi.fecha}</td>
                            <td>${cfdi.emisor_rfc}</td>
                            <td>${cfdi.receptor_rfc}</td>
                            <td>$${parseFloat(cfdi.subtotal).toFixed(2)}</td>
                            <td>$${parseFloat(cfdi.total).toFixed(2)}</td>
                        </tr>
                    `;
                    reviewBody.insertAdjacentHTML('beforeend', row);
                });
            } else {
                reviewBody.innerHTML = '<tr><td colspan="8" class="text-center">No se encontraron CFDI válidos en los archivos.</td></tr>';
            }

        } catch (error) {
            reviewBody.innerHTML = `<tr><td colspan="8" class="text-center text-danger">Error al procesar los archivos: ${error.message}</td></tr>`;
        }
    }
    
    // Asignar la función al botón correcto
    document.querySelector('button[onclick="enviarArchivosParse()"]').addEventListener('click', enviarArchivosParse);

    // Guardar los CFDI seleccionados
    document.getElementById('guardarCfdiBtn').addEventListener('click', async function() {
        const selectedItems = [];
        document.querySelectorAll('.cfdi-checkbox:checked').forEach(checkbox => {
            const index = checkbox.getAttribute('data-index');
            selectedItems.push(parsedCfdiData[index]);
        });

        if (selectedItems.length === 0) {
            Swal.fire('Atención', 'No has seleccionado ninguna factura para guardar.', 'warning');
            return;
        }

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Guardando...';

        try {
            const response = await fetch('../../app-m/core/guardar-facturas.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ items: selectedItems })
            });

            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Éxito!',
                    text: `Se guardaron ${result.inserted.length} facturas correctamente.`,
                    confirmButtonText: 'Excelente'
                }).then(() => {
                    // Recargar la página para ver la tabla actualizada
                    window.location.reload();
                });
            } else {
                let errorHtml = 'Ocurrió un problema al guardar las facturas.';
                if (result.errors && result.errors.length > 0) {
                    errorHtml += '<ul>' + result.errors.map(e => `<li>UUID ${e.uuid ? e.uuid.substring(0,8) : 'N/A'}: ${e.error}</li>`).join('') + '</ul>';
                }
                Swal.fire('Error', errorHtml, 'error');
            }

        } catch (error) {
            Swal.fire('Error de Conexión', `No se pudo conectar con el servidor: ${error.message}`, 'error');
        } finally {
            this.disabled = false;
            this.innerHTML = '<i class="fas fa-save"></i> Guardar Seleccionados';
        }
    });

    // Funcionalidad para el checkbox 
    document.getElementById('selectAllCfdi').addEventListener('change', function() {
        document.querySelectorAll('.cfdi-checkbox').forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });

</script>

</html>

