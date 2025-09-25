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

 <script>
  document.getElementById("xmlInput").addEventListener("change", function(e) {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(event) {
        try {
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(event.target.result, "text/xml");

            // ⚠️ Usar NS dinámico para CFDI
            const comprobante = xmlDoc.getElementsByTagNameNS("*", "Comprobante")[0];
            const emisor      = xmlDoc.getElementsByTagNameNS("*", "Emisor")[0];
            const receptor    = xmlDoc.getElementsByTagNameNS("*", "Receptor")[0];
            const timbre      = xmlDoc.getElementsByTagNameNS("*", "TimbreFiscalDigital")[0];

            if (!comprobante || !emisor || !receptor || !timbre) {
                throw new Error("Estructura XML no válida para CFDI");
            }

            const datos = {
                uuid: timbre.getAttribute("UUID"),
                version: comprobante.getAttribute("Version"),
                fecha: comprobante.getAttribute("Fecha"),
                subtotal: comprobante.getAttribute("SubTotal"),
                total: comprobante.getAttribute("Total"),
                moneda: comprobante.getAttribute("Moneda"),
                metodo_pago: comprobante.getAttribute("MetodoPago"),
                forma_pago: comprobante.getAttribute("FormaPago"),
                lugar_expedicion: comprobante.getAttribute("LugarExpedicion"),
                serie: comprobante.getAttribute("Serie"),
                folio: comprobante.getAttribute("Folio"),
                emisor_rfc: emisor.getAttribute("Rfc"),
                emisor_nombre: emisor.getAttribute("Nombre"),
                receptor_rfc: receptor.getAttribute("Rfc"),
                receptor_nombre: receptor.getAttribute("Nombre"),
            };

            // Mostrar en modal
            let preview = "";
            for (const [key, val] of Object.entries(datos)) {
                preview += `<p><b>${key}:</b> ${val || "-"}</p>`;
            }
            document.getElementById("cfdiData").innerHTML = preview;
            document.getElementById("cfdiModal").style.display = "block";

        } catch (err) {
            alert("Error al procesar XML: " + err.message);
            console.error(err);
        }
    };
    reader.readAsText(file);
});

</script>
</body>
<!--end::Body-->

</html>