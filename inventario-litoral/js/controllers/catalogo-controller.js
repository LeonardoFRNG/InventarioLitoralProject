/**
 * Lógica reutilizable para páginas de catálogo simple (categorías, ubicaciones).
 * Recibe la configuración específica de cada página y monta el CRUD completo.
 */
async function iniciarPaginaCatalogo({ tabla, tituloSingular, tituloPlural, paginaSidebar, campos }) {
    const usuario = await AuthService.requireSession();
    if (!usuario) return;

    document.getElementById('sidebar-container').outerHTML = renderSidebar(paginaSidebar, usuario);
    activarLogout();
    AuthService.aplicarVisibilidadPorRol(usuario);

    const esAdmin = usuario.rol === 'Administrador';
    document.getElementById('titulo-pagina').textContent = tituloPlural;
    if (!esAdmin) document.getElementById('btn-nuevo-catalogo').style.display = 'none';

    async function cargar() {
        const contenedor = document.getElementById('tabla-catalogo');
        contenedor.innerHTML = `<div class="flex justify-center py-8"><span class="spinner" style="border-top-color:#a78bfa;"></span></div>`;
        try {
            const res = await CatalogoService.listar(tabla);
            pintar(res.data);
        } catch (err) {
            contenedor.innerHTML = `<div class="empty-state">${Ui.escapeHtml(err.message)}</div>`;
        }
    }

    function pintar(items) {
        const contenedor = document.getElementById('tabla-catalogo');
        if (items.length === 0) {
            contenedor.innerHTML = `<div class="empty-state">No hay ${tituloPlural.toLowerCase()} registradas todavía.</div>`;
            return;
        }
        contenedor.innerHTML = `
            <table class="data-table">
                <thead><tr>${campos.map(c => `<th>${c.label}</th>`).join('')}<th></th></tr></thead>
                <tbody>
                    ${items.map(item => `
                        <tr>
                            ${campos.map(c => `<td>${Ui.escapeHtml(item[c.key] || '—')}</td>`).join('')}
                            <td class="text-right whitespace-nowrap">
                                ${esAdmin ? `
                                <button class="icon-btn" title="Editar" onclick='abrirModal(${JSON.stringify(item)})'>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </button>
                                <button class="icon-btn" title="Eliminar" onclick="eliminar(${item.id}, '${Ui.escapeHtml(item.nombre)}')">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                </button>` : ''}
                            </td>
                        </tr>`).join('')}
                </tbody>
            </table>`;
    }

    window.abrirModal = function (item = null) {
        const html = `
            <div class="modal-overlay">
                <div class="modal-box" style="max-width:440px;">
                    <h3 class="text-lg font-semibold mb-4">${item ? `Editar ${tituloSingular.toLowerCase()}` : `Nueva ${tituloSingular.toLowerCase()}`}</h3>
                    <form id="form-catalogo" novalidate>
                        ${campos.map(c => `
                            <div class="mb-3">
                                <label class="field-label">${c.label}</label>
                                <input id="campo-${c.key}" class="dark-input" value="${item ? Ui.escapeHtml(item[c.key] || '') : ''}">
                                ${c.required ? `<p class="text-xs mt-1" style="color:var(--color-danger)" id="campo-${c.key}-error"></p>` : ''}
                            </div>`).join('')}
                        <p class="text-sm mt-1" style="color:var(--color-danger)" id="form-catalogo-error"></p>
                        <div class="flex justify-end gap-2 mt-5">
                            <button type="button" class="btn-ghost" onclick="cerrarModalCatalogo()">Cancelar</button>
                            <button type="submit" class="btn-primary" id="btn-guardar-catalogo">${item ? 'Guardar cambios' : 'Crear'}</button>
                        </div>
                    </form>
                </div>
            </div>`;
        document.getElementById('modal-container').innerHTML = html;

        document.getElementById('form-catalogo').addEventListener('submit', async (e) => {
            e.preventDefault();
            document.getElementById('form-catalogo-error').textContent = '';

            const reglas = {};
            campos.filter(c => c.required).forEach(c => { reglas[`campo-${c.key}`] = [Validators.requerido]; });
            if (!Validators.validarFormulario(reglas)) return;

            const payload = { id: item?.id };
            campos.forEach(c => { payload[c.key] = document.getElementById(`campo-${c.key}`).value.trim(); });

            const btn = document.getElementById('btn-guardar-catalogo');
            Ui.setLoading(btn, true);
            try {
                if (item) {
                    await CatalogoService.actualizar(tabla, payload);
                    Ui.toast(`${tituloSingular} actualizada correctamente.`);
                } else {
                    await CatalogoService.crear(tabla, payload);
                    Ui.toast(`${tituloSingular} creada correctamente.`);
                }
                cerrarModalCatalogo();
                cargar();
            } catch (err) {
                document.getElementById('form-catalogo-error').textContent =
                    err.errors && err.errors.length ? err.errors.join(' ') : err.message;
            } finally {
                Ui.setLoading(btn, false, item ? 'Guardar cambios' : 'Crear');
            }
        });
    };

    window.cerrarModalCatalogo = () => { document.getElementById('modal-container').innerHTML = ''; };

    window.eliminar = async function (id, nombre) {
        const ok = await Ui.confirmar(`¿Eliminar "${nombre}"?`, 'Esta acción no se puede deshacer.');
        if (!ok) return;
        try {
            const res = await CatalogoService.eliminar(tabla, id);
            Ui.toast(res.message);
            cargar();
        } catch (err) {
            Ui.toast(err.message, 'error');
        }
    };

    document.getElementById('btn-nuevo-catalogo').addEventListener('click', () => abrirModal());
    cargar();
}
