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

				<div class="d-flex justify-content-end mb-2">
					<button id="btn-verificar-manual" class="btn btn-primary">
						Verificar Solicitud SAT
					</button>
				</div>
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
			return map[estado] || '<span class="badge bg-secondary">' + estado + '</span>';
		}

		async function verificar(id) {
			const row = tbody.querySelector('tr[data-id="' + id + '"] .estado-col');
			row.innerHTML = '<span class="badge bg-warning"><i class="fas fa-sync fa-spin"></i> Verificando</span>';
			try {
				const res = await fetch('../core/verificar-descarga.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json'
					},
					body: JSON.stringify({
						id_solicitud: id,
						minimal: false
					})
				});
				const data = await res.json();
				if (!data.success) throw new Error(data.message || 'Fallo verificación');
				row.innerHTML = parseEstadoBadge(data.estado);
				const tr = tbody.querySelector('tr[data-id="' + id + '"]');
				const pkgCell = tr.children[5];
				const descargados = data.paquetes ? data.paquetes.filter(p => ['descargado', 'procesado'].includes(p.estado)).length : 0;
				const total = data.total_paquetes || (data.paquetes ? data.paquetes.length : 0);
				pkgCell.innerHTML = '<span class="badge bg-dark">' + descargados + '/' + total + '</span>';
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
	document.addEventListener('click', async (e) => {
		const btn = e.target.closest('.btn-verificar');
		if (!btn) return;

		const tr = btn.closest('tr');
		const idSolicitud = tr.dataset.id;

		Swal.fire({
			title: 'Verificando solicitud...',
			html: `Consultando el estado en el SAT.<br>Esto puede tardar algunos segundos.`,
			allowOutsideClick: false,
			didOpen: () => Swal.showLoading()
		});

		try {
			const res = await fetch('core/verificar-descarga.php', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json'
				},
				body: JSON.stringify({
					id_solicitud: idSolicitud
				})
			});
			const data = await res.json();

			if (data.success) {
				Swal.fire({
					icon: 'success',
					title: 'Verificación completada',
					html: data.message || 'La solicitud ha sido actualizada correctamente.'
				}).then(() => {
					if (typeof cargarSolicitudes === 'function') {
						cargarSolicitudes();
					} else {
						location.reload();
					}
				});
			} else {
				Swal.fire({
					icon: 'info',
					title: 'Sin cambios',
					text: data.message || 'El SAT aún no ha respondido. Intenta más tarde.'
				});
			}
		} catch (err) {
			console.error(err);
			Swal.fire('Error', 'No se pudo conectar al servidor. Intenta de nuevo más tarde.', 'error');
		}
	});

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
			return map[estado] || '<span class="badge bg-secondary">' + estado + '</span>';
		}

		function actualizarFila(idSolicitud, data) {
			const tr = tbody.querySelector(`tr[data-id='${idSolicitud}']`);
			if (!tr) return;

			// Actualizar estado
			const estadoCol = tr.querySelector('.estado-col');
			estadoCol.innerHTML = parseEstadoBadge(data.estado);

			// Actualizar paquetes
			const pkgCell = tr.children[5];
			const descargados = data.paquetes ? data.paquetes.filter(p => ['descargado', 'procesado'].includes(p.estado)).length : 0;
			const total = data.total_paquetes || (data.paquetes ? data.paquetes.length : 0);
			pkgCell.innerHTML = `<span class="badge bg-dark">${descargados}/${total}</span>`;
		}

		async function verificarSolicitud(idSolicitud, requestId) {
			Swal.fire({
				title: 'Verificando solicitud...',
				html: `Consultando el estado en el SAT.<br>Esto puede tardar algunos segundos.`,
				allowOutsideClick: false,
				didOpen: () => Swal.showLoading()
			});

			try {
				const res = await fetch('core/verificar-descarga.php', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json'
					},
					body: JSON.stringify({
						id_solicitud: idSolicitud,
						requestId
					})
				});

				const data = await res.json();

				if (data.success) {
					Swal.fire({
						icon: 'success',
						title: 'Verificación completada',
						html: data.message || 'La solicitud ha sido actualizada correctamente.'
					});

					// Actualizar la fila en la tabla si existe
					actualizarFila(idSolicitud, data);

				} else {
					Swal.fire({
						icon: 'info',
						title: 'Sin cambios',
						text: data.message || 'El SAT aún no ha respondido. Intenta más tarde.'
					});
				}
			} catch (err) {
				console.error(err);
				Swal.fire('Error', 'No se pudo conectar al servidor. Intenta de nuevo más tarde.', 'error');
			}
		}

		// Botón general para verificar manualmente
		const btnGeneral = document.getElementById('btn-verificar-manual');
		if (btnGeneral) {
			btnGeneral.addEventListener('click', () => {
				Swal.fire({
					title: 'Ingrese los datos de la solicitud',
					html: `
                    <input type="text" id="swal-requestId" class="swal2-input" placeholder="Request ID">  `,
					confirmButtonText: 'Verificar',
					showCancelButton: true,
					preConfirm: () => {
						const requestId = document.getElementById('swal-requestId').value.trim();
						if (!requestId) {
							Swal.showValidationMessage('Debe ingresar ID de solicitud');
							return false;
						}
						return {requestId};
					}
				}).then((result) => {
					if (result.isConfirmed) {
						const {requestId
						} = result.value;
						verificarSolicitud(requestId);
					}
				});
			});
		}
		document.addEventListener('DOMContentLoaded', () => {
			const btnVerificarManual = document.getElementById('btn-verificar-manual');

			if (!btnVerificarManual) return;

			btnVerificarManual.addEventListener('click', async () => {
				const {
					value: requestId
				} = await Swal.fire({
					title: 'Verificar solicitud SAT',
					input: 'text',
					inputLabel: 'Ingresa el Request ID de la solicitud',
					inputPlaceholder: 'Ejemplo: a1cabf79-2dc3-40ab-b671-4bcb881d477b',
					showCancelButton: true,
					confirmButtonText: 'Verificar',
					cancelButtonText: 'Cancelar',
					inputValidator: (value) => {
						if (!value) return 'Debes ingresar un Request ID válido';
					}
				});

				if (!requestId) return;

				Swal.fire({
					title: 'Verificando con el SAT...',
					html: 'Consultando el estado de la solicitud.<br>Por favor espera unos segundos.',
					allowOutsideClick: false,
					didOpen: () => Swal.showLoading()
				});

				try {
					const res = await fetch('core/verificar-descarga.php', {
						method: 'POST',
						headers: {
							'Content-Type': 'application/json'
						},
						body: JSON.stringify({
							requestId: requestId
						}) // 👈 ahora se envía el requestId
					});

					const data = await res.json();
					console.log('Respuesta SAT:', data);

					if (!data.success) {
						Swal.fire({
							icon: 'info',
							title: 'No se pudo verificar',
							text: data.message || 'El SAT no ha respondido todavía o el Request ID es incorrecto.'
						});
						return;
					}

					// Muestra el estado recibido del SAT
					let estado = data.estado || 'Desconocido';
					let mensaje = data.status_message || 'Sin mensaje del SAT';

					let icon = 'info';
					if (estado === 'terminada') icon = 'success';
					else if (estado === 'rechazada' || estado === 'error') icon = 'error';
					else if (estado === 'aceptada') icon = 'warning';
					else if (estado === 'pendiente') icon = 'question';

					Swal.fire({
						icon: icon,
						title: `Estado: ${estado.toUpperCase()}`,
						html: `<b>${mensaje}</b><br><br>
                <small>Request ID:</small><br><code>${requestId}</code>`,
						confirmButtonText: 'Aceptar'
					});

					// 🔄 Refresca tabla si existe función o recarga página
					if (typeof cargarSolicitudes === 'function') {
						cargarSolicitudes();
					} else {
						setTimeout(() => location.reload(), 2000);
					}

				} catch (err) {
					console.error(err);
					Swal.fire({
						icon: 'error',
						title: 'Error',
						text: 'No se pudo conectar con el servidor. Intenta de nuevo más tarde.'
					});
				}
			});
		});
	});
</script>