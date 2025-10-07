<?php
      require_once __DIR__ . '/../core/class/db.php';
      require_once __DIR__ . '/../config.php';
      echo '<div class="card-body">';
      echo '<table class="table table-hover text-center align-middle">';
      echo '<thead class="table-info">';
      echo '<tr>';
      echo '<th>UUID</th>';
      echo '<th>Serie</th>';
      echo '<th>Folio Fiscal</th>';
      echo '<th>RFC Emisor</th>';
      echo '<th>RFC Receptor</th>';
      echo '<th>Razón Social</th>';
      echo '<th>Fecha Emisión</th>';
      echo '<th>Uso CFDI</th>';
      echo '<th>Subtotal</th>';
      echo '<th>Total</th>';
      echo '<th>Forma de Pago</th>';
      echo '<th>Método de Pago</th>';
      echo '<th>Archivos</th>';
      echo '</tr>';
      echo '</thead>';
      echo '<tbody id="facturas-cargadas">';
      try {
        $db = (new Database())->getConnection();
        $stmt = $db->query("SELECT uuid, serie, folio, emisor_rfc, receptor_rfc, emisor_nombre, fecha, receptor_uso_cfdi, subtotal, total, forma_pago, metodo_pago, pdf_file, xml_file FROM facturas ORDER BY fecha DESC LIMIT 10");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
          $pdfPath = 'uploads/pdf/' . htmlspecialchars($row['pdf_file'] ?? '', ENT_QUOTES, 'UTF-8');
          $xmlPath = 'uploads/xml/' . htmlspecialchars($row['xml_file'] ?? '', ENT_QUOTES, 'UTF-8');
          echo '<tr>';
          echo '<td>' . htmlspecialchars($row['uuid']) . '</td>';
          echo '<td>' . htmlspecialchars($row['serie']) . '</td>';
          echo '<td>' . htmlspecialchars($row['folio']) . '</td>';
          echo '<td>' . htmlspecialchars($row['emisor_rfc']) . '</td>';
          echo '<td>' . htmlspecialchars($row['receptor_rfc']) . '</td>';
          echo '<td>' . htmlspecialchars($row['emisor_nombre']) . '</td>';
          echo '<td>' . htmlspecialchars($row['fecha']) . '</td>';
          echo '<td>' . htmlspecialchars($row['receptor_uso_cfdi']) . '</td>';
          echo '<td>' . number_format((float)$row['subtotal'], 2) . '</td>';
          echo '<td>' . number_format((float)$row['total'], 2) . '</td>';
          echo '<td>' . htmlspecialchars($row['forma_pago']) . '</td>';
          echo '<td>' . htmlspecialchars($row['metodo_pago']) . '</td>';
          echo '<td>';
          if (!empty($row['pdf_file'])) {
            echo '<a href="' . $pdfPath . '" class="text-danger" download title="Descargar PDF"><i class="fas fa-file-pdf fa-lg"></i></a>';
          }
          if (!empty($row['xml_file'])) {
            echo '<a href="' . $xmlPath . '" class="text-primary ms-2" download title="Descargar XML"><i class="fas fa-file-code fa-lg"></i></a>';
          }
          echo '</td>';
          echo '</tr>';
        }
      } catch (Throwable $e) {
        echo '<tr><td colspan="13" class="text-danger">Error al cargar facturas: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
      }
      echo '</tbody>';
      echo '</table>';
      echo '</div>';
      ?>