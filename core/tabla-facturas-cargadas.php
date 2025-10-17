<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../core/class/db.php';

// Usar PDO para la conexión
$db = new Database();
$conn = $db->getConnection();

$result = $conn->query("SELECT * FROM facturas ORDER BY fecha DESC LIMIT 10");
?>
<div class="content-wrapper" style="margin-left:0 !important; padding:0 15px;">
    <div class="card bg-white shadow-sm mt-4 mb-4">
        <!-- Encabezado -->
        <div class="card-header bg-primary text-white p-3">
            <h2 class="fw-bold m-0">Facturas cargadas</h2>
        </div>

<div class="card-body">
    <table id="miTabla" class="table table-striped table-hover text-center align-middle">
</div>
<!-- DataTables JS y configuración -->
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
$(document).ready(function() {
    $('#miTabla').DataTable({
        "lengthMenu": [5, 10, 25],
        "language": {
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "search": "Buscar:",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            },
            "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
            "infoEmpty": "No hay registros disponibles",
            "zeroRecords": "No se encontraron resultados"
        }
    });
});
</script>
        <thead class="table-info">
            <tr>
                <th>UUID</th>
                <th>Serie</th>
                <th>Folio Fiscal</th>
                <th>RFC Emisor</th>
                <th>RFC Receptor</th>
                <th>Razón Social</th>
                <th>Fecha Emisión</th>
                <th>Uso CFDI</th>
                <th>Subtotal</th>
                <th>Total</th>
                <th>Forma de Pago</th>
                <th>Metodo de Pago</th>
                <th>Accciones</th>
            </tr>
        </thead>
        <tbody id="facturas-cargadas">
            <?php while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
                $pdfPath = 'uploads/pdf/' . $row['pdf_file'];
                $xmlPath = 'uploads/xml/' . $row['xml_file'];
            ?>
            <tr>
                <td><?= $row['uuid'] ?></td>
                <td><?= $row['serie'] ?></td>
                <td><?= $row['folio'] ?></td>
                <td><?= $row['emisor_rfc'] ?></td>
                <td><?= $row['receptor_rfc'] ?></td>
                <td><?= $row['emisor_nombre'] ?></td>
                <td><?= $row['fecha'] ?></td>
                <td><?= $row['receptor_uso_cfdi'] ?></td>
                <td><?= number_format($row['subtotal'], 2) ?></td>
                <td><?= number_format($row['total'], 2) ?></td>
                <td><?= $row['forma_pago'] ?></td>
                <td><?= $row['metodo_pago'] ?></td>
                <td>
                    <a href="<?= $pdfPath ?>" class="btn btn-danger btn-sm" download title="Descargar PDF">
                        <i class="bi bi-filetype-pdf"></i>
                    </a>
                    <a href="<?= $xmlPath ?>" class="btn btn-primary btn-sm ms-2" download title="Descargar XML">
                        <i class="bi bi-filetype-xml"></i>
                    </a>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
</div>
