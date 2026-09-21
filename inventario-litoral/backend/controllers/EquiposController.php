<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middlewares/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/Request.php';

$user   = Auth::requireAuth();
$pdo    = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = Request::query('action', 'list');

/**
 * Registra un evento en el historial de trazabilidad del equipo.
 * Prepared statement obligatorio: nunca se concatenan valores.
 */
function registrarHistorial(PDO $pdo, int $equipoId, ?int $usuarioId, string $accion,
                             ?int $estadoAnteriorId, ?int $estadoNuevoId, string $detalle): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO historial_movimientos
            (equipo_id, usuario_id, accion, estado_anterior_id, estado_nuevo_id, detalle)
         VALUES (:equipo_id, :usuario_id, :accion, :estado_anterior_id, :estado_nuevo_id, :detalle)'
    );
    $stmt->execute([
        ':equipo_id'          => $equipoId,
        ':usuario_id'         => $usuarioId,
        ':accion'             => $accion,
        ':estado_anterior_id' => $estadoAnteriorId,
        ':estado_nuevo_id'    => $estadoNuevoId,
        ':detalle'            => $detalle,
    ]);
}

// =====================================================================
// LISTAR (con búsqueda, filtros, orden y paginación)
// =====================================================================
if ($method === 'GET' && $action === 'list') {
    $busqueda   = trim(Request::query('busqueda', ''));
    $estadoId   = Request::query('estado_id');
    $categoriaId= Request::query('categoria_id');
    $ubicacionId= Request::query('ubicacion_id');
    $tipoId     = Request::query('tipo_id');
    $pagina     = max(1, (int)Request::query('pagina', 1));
    $porPagina  = min(100, max(1, (int)Request::query('por_pagina', 10)));
    $offset     = ($pagina - 1) * $porPagina;

    $where  = ['e.eliminado = 0'];
    $params = [];

    if ($busqueda !== '') {
        $where[] = '(e.codigo_inventario LIKE :busqueda1 OR e.nombre LIKE :busqueda2 OR e.serial LIKE :busqueda3)';
        $params[':busqueda1'] = "%$busqueda%";
        $params[':busqueda2'] = "%$busqueda%";
        $params[':busqueda3'] = "%$busqueda%";
    }
    if ($estadoId)   { $where[] = 'e.estado_id = :estado_id';       $params[':estado_id']    = $estadoId; }
    if ($categoriaId){ $where[] = 'e.categoria_id = :categoria_id'; $params[':categoria_id'] = $categoriaId; }
    if ($ubicacionId){ $where[] = 'e.ubicacion_id = :ubicacion_id'; $params[':ubicacion_id'] = $ubicacionId; }
    if ($tipoId)     { $where[] = 'e.tipo_id = :tipo_id';           $params[':tipo_id']      = $tipoId; }

    $whereSql = implode(' AND ', $where);

    // Conteo total para la paginación
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) AS total FROM equipos e WHERE $whereSql");
    $stmtTotal->execute($params);
    $total = (int)$stmtTotal->fetch()['total'];

    $sql = "SELECT e.id, e.codigo_inventario, e.nombre, e.serial, e.caracteristicas,
                   e.fecha_registro,
                   t.nombre AS tipo, c.nombre AS categoria, u.nombre AS ubicacion,
                   es.nombre AS estado, es.color AS estado_color,
                   res.nombre AS responsable
            FROM equipos e
            INNER JOIN tipos_equipo t ON t.id = e.tipo_id
            LEFT JOIN categorias c    ON c.id = e.categoria_id
            LEFT JOIN ubicaciones u   ON u.id = e.ubicacion_id
            INNER JOIN estados es     ON es.id = e.estado_id
            LEFT JOIN usuarios res    ON res.id = e.responsable_id
            WHERE $whereSql
            ORDER BY e.fecha_registro DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    Response::success([
        'equipos'    => $stmt->fetchAll(),
        'total'      => $total,
        'pagina'     => $pagina,
        'por_pagina' => $porPagina,
        'total_paginas' => (int)ceil($total / $porPagina),
    ]);
}

// =====================================================================
// OBTENER UNO (con historial y mantenimientos asociados)
// =====================================================================
if ($method === 'GET' && $action === 'get') {
    $id = (int)Request::query('id', 0);

    $stmt = $pdo->prepare(
        'SELECT e.*, t.nombre AS tipo, c.nombre AS categoria, u.nombre AS ubicacion,
                es.nombre AS estado, res.nombre AS responsable
         FROM equipos e
         INNER JOIN tipos_equipo t ON t.id = e.tipo_id
         LEFT JOIN categorias c ON c.id = e.categoria_id
         LEFT JOIN ubicaciones u ON u.id = e.ubicacion_id
         INNER JOIN estados es ON es.id = e.estado_id
         LEFT JOIN usuarios res ON res.id = e.responsable_id
         WHERE e.id = :id AND e.eliminado = 0'
    );
    $stmt->execute([':id' => $id]);
    $equipo = $stmt->fetch();

    if (!$equipo) {
        Response::notFound('El equipo solicitado no existe o fue dado de baja.');
    }

    $stmtHist = $pdo->prepare(
        'SELECT h.*, us.nombre AS usuario_nombre, ea.nombre AS estado_anterior, en.nombre AS estado_nuevo
         FROM historial_movimientos h
         LEFT JOIN usuarios us ON us.id = h.usuario_id
         LEFT JOIN estados ea ON ea.id = h.estado_anterior_id
         LEFT JOIN estados en ON en.id = h.estado_nuevo_id
         WHERE h.equipo_id = :id ORDER BY h.fecha DESC'
    );
    $stmtHist->execute([':id' => $id]);
    $equipo['historial'] = $stmtHist->fetchAll();

    Response::success($equipo);
}

// =====================================================================
// CREAR (solo Administrador y Técnico)
// =====================================================================
if ($method === 'POST' && $action === 'create') {
    Auth::requireRole([Auth::ROL_ADMIN, Auth::ROL_TECNICO]);
    $body = Request::json();

    $codigo   = trim($body['codigo_inventario'] ?? '');
    $nombre   = trim($body['nombre'] ?? '');
    $serial   = trim($body['serial'] ?? '') ?: null;
    $tipoId   = $body['tipo_id'] ?? null;
    $estadoId = $body['estado_id'] ?? null;

    $errores = Validator::ejecutar([
        fn() => Validator::requerido($codigo, 'Código de inventario'),
        fn() => $codigo !== '' ? Validator::codigoInventario($codigo) : null,
        fn() => Validator::requerido($nombre, 'Nombre del equipo'),
        fn() => Validator::requerido($tipoId, 'Tipo de equipo'),
        fn() => Validator::requerido($estadoId, 'Estado'),
    ]);
    if (!empty($errores)) {
        Response::error('No se pudo registrar el equipo. Revisa los datos.', 422, $errores);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO equipos
                (codigo_inventario, nombre, serial, tipo_id, categoria_id, ubicacion_id,
                 responsable_id, estado_id, caracteristicas)
             VALUES
                (:codigo, :nombre, :serial, :tipo_id, :categoria_id, :ubicacion_id,
                 :responsable_id, :estado_id, :caracteristicas)'
        );
        $stmt->execute([
            ':codigo'          => $codigo,
            ':nombre'          => $nombre,
            ':serial'          => $serial,
            ':tipo_id'         => $tipoId,
            ':categoria_id'    => $body['categoria_id'] ?? null,
            ':ubicacion_id'    => $body['ubicacion_id'] ?? null,
            ':responsable_id'  => $body['responsable_id'] ?? null,
            ':estado_id'       => $estadoId,
            ':caracteristicas' => $body['caracteristicas'] ?? null,
        ]);

        $equipoId = (int)$pdo->lastInsertId();

        registrarHistorial($pdo, $equipoId, $user['id'], 'CREACION', null, (int)$estadoId,
            "Registro inicial del equipo $codigo");

        $pdo->commit();
        Response::success(['id' => $equipoId], "Equipo \"$nombre\" registrado correctamente.", 201);
    } catch (PDOException $e) {
        $pdo->rollBack();
        Response::error('No se pudo registrar el equipo. ' . Response::translateDbError($e), 409);
    }
}

// =====================================================================
// ACTUALIZAR (solo Administrador y Técnico) — registra cambio de estado
// =====================================================================
if ($method === 'PUT' && $action === 'update') {
    Auth::requireRole([Auth::ROL_ADMIN, Auth::ROL_TECNICO]);
    $body = Request::json();
    $id   = (int)($body['id'] ?? 0);

    $errIdCheck = Validator::enteroPositivo($id, 'ID');
    if ($errIdCheck) Response::error($errIdCheck, 422);

    $stmtActual = $pdo->prepare('SELECT estado_id, codigo_inventario FROM equipos WHERE id = :id AND eliminado = 0');
    $stmtActual->execute([':id' => $id]);
    $actual = $stmtActual->fetch();
    if (!$actual) {
        Response::notFound('El equipo que intentas editar no existe.');
    }

    $codigo   = trim($body['codigo_inventario'] ?? '');
    $nombre   = trim($body['nombre'] ?? '');
    $estadoId = $body['estado_id'] ?? null;

    $errores = Validator::ejecutar([
        fn() => Validator::requerido($codigo, 'Código de inventario'),
        fn() => $codigo !== '' ? Validator::codigoInventario($codigo) : null,
        fn() => Validator::requerido($nombre, 'Nombre del equipo'),
        fn() => Validator::requerido($estadoId, 'Estado'),
    ]);
    if (!empty($errores)) {
        Response::error('No se pudo actualizar el equipo. Revisa los datos.', 422, $errores);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'UPDATE equipos SET
                codigo_inventario = :codigo, nombre = :nombre, serial = :serial,
                tipo_id = :tipo_id, categoria_id = :categoria_id, ubicacion_id = :ubicacion_id,
                responsable_id = :responsable_id, estado_id = :estado_id,
                caracteristicas = :caracteristicas
             WHERE id = :id AND eliminado = 0'
        );
        $stmt->execute([
            ':codigo'          => $codigo,
            ':nombre'          => $nombre,
            ':serial'          => trim($body['serial'] ?? '') ?: null,
            ':tipo_id'         => $body['tipo_id'] ?? null,
            ':categoria_id'    => $body['categoria_id'] ?? null,
            ':ubicacion_id'    => $body['ubicacion_id'] ?? null,
            ':responsable_id'  => $body['responsable_id'] ?? null,
            ':estado_id'       => $estadoId,
            ':caracteristicas' => $body['caracteristicas'] ?? null,
            ':id'              => $id,
        ]);

        // Trazabilidad: solo registrar si el estado realmente cambió
        if ((int)$actual['estado_id'] !== (int)$estadoId) {
            registrarHistorial($pdo, $id, $user['id'], 'CAMBIO_ESTADO',
                (int)$actual['estado_id'], (int)$estadoId,
                "Cambio de estado del equipo {$actual['codigo_inventario']}");
        } else {
            registrarHistorial($pdo, $id, $user['id'], 'ACTUALIZACION',
                (int)$actual['estado_id'], (int)$estadoId,
                "Datos del equipo {$actual['codigo_inventario']} actualizados");
        }

        $pdo->commit();
        Response::success(null, 'Equipo actualizado correctamente.');
    } catch (PDOException $e) {
        $pdo->rollBack();
        Response::error('No se pudo actualizar el equipo. ' . Response::translateDbError($e), 409);
    }
}

// =====================================================================
// ELIMINAR (baja lógica — solo Administrador)
// Nunca se borra físicamente: se preserva el historial del activo.
// =====================================================================
if ($method === 'DELETE' && $action === 'delete') {
    Auth::requireRole([Auth::ROL_ADMIN]);
    $id = (int)Request::query('id', 0);

    $errIdCheck = Validator::enteroPositivo($id, 'ID');
    if ($errIdCheck) Response::error($errIdCheck, 422);

    $stmtActual = $pdo->prepare('SELECT codigo_inventario, estado_id FROM equipos WHERE id = :id AND eliminado = 0');
    $stmtActual->execute([':id' => $id]);
    $equipo = $stmtActual->fetch();
    if (!$equipo) {
        Response::notFound('El equipo que intentas eliminar no existe o ya fue dado de baja.');
    }

    // Buscamos el id del estado "Baja" en el catálogo (no lo hardcodeamos por número)
    $stmtBaja = $pdo->prepare("SELECT id FROM estados WHERE nombre = 'Baja' LIMIT 1");
    $stmtBaja->execute();
    $estadoBajaId = $stmtBaja->fetch()['id'] ?? null;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'UPDATE equipos SET eliminado = 1, estado_id = COALESCE(:estado_baja, estado_id) WHERE id = :id'
        );
        $stmt->execute([':estado_baja' => $estadoBajaId, ':id' => $id]);

        registrarHistorial($pdo, $id, $user['id'], 'BAJA',
            (int)$equipo['estado_id'], $estadoBajaId,
            "Equipo {$equipo['codigo_inventario']} dado de baja. Se conserva su historial.");

        $pdo->commit();
        Response::success(null, "El equipo {$equipo['codigo_inventario']} fue dado de baja. Su historial se conservó para trazabilidad.");
    } catch (PDOException $e) {
        $pdo->rollBack();
        Response::error('No se pudo dar de baja el equipo. ' . Response::translateDbError($e), 409);
    }
}

Response::error('Acción no encontrada.', 404);
