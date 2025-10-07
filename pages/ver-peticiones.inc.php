<div class="content-wrapper" style="margin-left:0 !important; padding:0 15px;">
	<div class="card bg-white shadow-sm mt-4 mb-4">
		<div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
			<h2 class="fw-bold m-0"><i class="fas fa-list-alt me-2"></i>Peticiones de Descarga SAT</h2>
			<div>
			</div>
		</div>
		<div class="card-body">
            <a href="panel?pg=cargar-facturas" class="btn btn-secondary btn-sm"><i class="fas fa-arrow-left"></i> Regresar</a>
			<?php require __DIR__ . '/../core/filtros-solicitudes.php'; ?>

			<div class="table-responsive">
				<table class="table table-bordered table-striped table-hover mb-2" id="tabla-solicitudes" style="width:100%;">
					<thead class="thead-dark text-center">
						<tr>
							<th style="width:5%;">ID</th>
							<th style="width:16%;">Folio SAT</th>
							<th style="width:12%;">RFC</th>
							<th style="width:8%;">Tipo</th>
							<th style="width:18%;">Rango / UUID</th>
							<th style="width:8%;">Paquetes</th>
							<th style="width:10%;">Estado</th>
							<th style="width:11%;">Creada</th>
							<th style="width:12%;">Últ. Verif.</th>
							<th style="width:10%;">Acciones</th>
						</tr>
					</thead>
					<tbody class="text-center" id="tbody-solicitudes">
                        <?php require __DIR__ . '/../core/listar-solicitudes.php'; ?>
					</tbody>
				</table>
			</div>

			<details class="mt-3">
				<summary class="fw-bold">Descripción de estados</summary>
				<div class="mt-2">
					<?php require __DIR__ . '/../core/estados-solicitudes-descripcion.php'; ?>
				</div>
			</details>
		</div>
	</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
	const tbody = document.getElementById('tbody-solicitudes');

	function parseEstadoBadge(estado) {
		const map = {
			pendiente: '<span class="badge bg-secondary"><i class="fas fa-clock me-1"></i>Pendiente</span>',
			aceptada: '<span class="badge bg-info"><i class="fas fa-hourglass-half me-1"></i>Aceptada</span>',
			terminada: '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Terminada</span>',
			rechazada: '<span class="badge bg-danger"><i class="fas fa-times-circle me-1"></i>Rechazada</span>',
			error: '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Error</span>',
			vencida: '<span class="badge bg-warning"><i class="fas fa-hourglass-end me-1"></i>Vencida</span>'
		};
		return map[estado] || '<span class="badge bg-secondary">'+estado+'</span>';
	}

	async function verificar(id) {
		const row = tbody.querySelector('tr[data-id="'+id+'"] .estado-col');
		row.innerHTML = '<span class="badge bg-warning"><i class="fas fa-sync fa-spin"></i> Verificando</span>';
		try {
			const res = await fetch('../core/verificar-descarga.php', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify({ id_solicitud: id, minimal: false })
			});
			const data = await res.json();
			if (!data.success) throw new Error(data.message || 'Fallo verificación');
			row.innerHTML = parseEstadoBadge(data.estado);
			const tr = tbody.querySelector('tr[data-id="'+id+'"]');
			const pkgCell = tr.children[5];
			const descargados = data.paquetes ? data.paquetes.filter(p=>['descargado','procesado'].includes(p.estado)).length : 0;
			const total = data.total_paquetes || (data.paquetes?data.paquetes.length:0);
			pkgCell.innerHTML = '<span class="badge bg-dark">'+descargados+'/'+total+'</span>';
		} catch (e) {
			row.innerHTML = '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Error</span>';
			console.error(e);
		}
	}

	tbody.addEventListener('click', (ev) => {
		const btn = ev.target.closest('.btn-verificar');
		if (btn) {
			const id = btn.closest('tr').dataset.id;
			verificar(id);
		}
	});
});
</script>
