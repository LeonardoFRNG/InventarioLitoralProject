const EquiposService = {
    listar(filtros = {}) {
        const params = new URLSearchParams({ action: 'list', ...filtros });
        return Api.get(`EquiposController.php?${params.toString()}`);
    },
    obtener(id) {
        return Api.get(`EquiposController.php?action=get&id=${id}`);
    },
    crear(datos) {
        return Api.post('EquiposController.php?action=create', datos);
    },
    actualizar(datos) {
        return Api.put('EquiposController.php?action=update', datos);
    },
    eliminar(id) {
        return Api.delete(`EquiposController.php?action=delete&id=${id}`);
    },
};

const CatalogoService = {
    listar(tabla) {
        return Api.get(`CatalogoController.php?tabla=${tabla}&action=list`);
    },
    crear(tabla, datos) {
        return Api.post(`CatalogoController.php?tabla=${tabla}&action=create`, datos);
    },
    actualizar(tabla, datos) {
        return Api.put(`CatalogoController.php?tabla=${tabla}&action=update`, datos);
    },
    eliminar(tabla, id) {
        return Api.delete(`CatalogoController.php?tabla=${tabla}&action=delete&id=${id}`);
    },
};

const UsuariosService = {
    listar(busqueda = '') {
        return Api.get(`UsuariosController.php?action=list&busqueda=${encodeURIComponent(busqueda)}`);
    },
    simple() {
        return Api.get('UsuariosController.php?action=simple');
    },
    roles() {
        return Api.get('UsuariosController.php?action=roles');
    },
    crear(datos) {
        return Api.post('UsuariosController.php?action=create', datos);
    },
    actualizar(datos) {
        return Api.put('UsuariosController.php?action=update', datos);
    },
    eliminar(id) {
        return Api.delete(`UsuariosController.php?action=delete&id=${id}`);
    },
};

const AsignacionesService = {
    listar(equipoId = null) {
        const q = equipoId ? `&equipo_id=${equipoId}` : '';
        return Api.get(`AsignacionesController.php?action=list${q}`);
    },
    crear(datos) {
        return Api.post('AsignacionesController.php?action=create', datos);
    },
    devolver(id) {
        return Api.put('AsignacionesController.php?action=devolver', { id });
    },
};

const MantenimientosService = {
    listar(equipoId = null) {
        const q = equipoId ? `&equipo_id=${equipoId}` : '';
        return Api.get(`MantenimientosController.php?action=list${q}`);
    },
    crear(datos) {
        return Api.post('MantenimientosController.php?action=create', datos);
    },
};
