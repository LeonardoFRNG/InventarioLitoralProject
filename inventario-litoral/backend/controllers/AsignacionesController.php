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

if ($method === 'GET' && $action === 'list') {
    $equipoId = Request::query('equipo_id');

    $sql = 'SELECT a.*, e.codigo_inventario, e.nombre AS equipo_nombre,
                   u.nombre AS usuario_nombre, ap.nombre AS asignado_por_nombre
            FROM asignaciones a
            INNER JOIN equipos e ON e.id = a.equipo_id
            INNER JOIN usuarios u ON u.id = a.usuario_id
            LEFT JOIN usuarios ap ON ap.id = a.asignado_por';
    $params = [];
    if ($equipoId) {
        $sql .= ' WHERE a.equipo_id = :equipo_id';
        $params[':equipo_id'] = $equipoId;
    }
    $sql .= ' ORDER BY a.fecha_asignacion DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    Response::success($stmt->fetchAll());
}

if ($method === 'POST' && $action === 'create') {
    Auth::requireRole([Auth::ROL_ADMIN, Auth::ROL_TECNICO]);
    $body = Request::json();

    $equipoId = $body['equipo_id'] ?? null;
    $usuarioId = $body['usuario_id'] ?? null;
    $fechaAsignacion = trim($body['fecha_asignacion'] ?? '');

    $errores = Validator::ejecutar([
        fn() => Validator::requerido($equipoId, 'Equipo'),
        fn() => Validator::requerido($usuarioId, 'Usuario responsable'),
        fn() => Validator::requerido($fechaAsignacion, 'Fecha de asignación'),
        fn() => $fechaAsignacion !== '' ? Validator::fechaValida($fechaAsignacion, 'fecha de asignación') : null,
    ]);
    if (!empty($errores)) {
        Response::error('No se pudo registrar la asignación. Revisa los datos.', 422, $errores);
    }

    try {
        $pdo->beginTransaction();

        // Cerrar cualquier asignación activa previa del mismo equipo
        $stmtCerrar = $pdo->prepare(
            'UPDATE asignaciones SET activa = 0, fecha_devolucion = :hoy
             WHERE equipo_id = :equipo_id AND activa = 1'
        );
        $stmtCerrar->execute([':hoy' => date('Y-m-d'), ':equipo_id' => $equipoId]);

        $stmt = $pdo->prepare(
            'INSERT INTO asignaciones (equipo_id, usuario_id, asignado_por, fecha_asignacion, observaciones)
             VALUES (:equipo_id, :usuario_id, :asignado_por, :fecha_asignacion, :observaciones)'
        );
        $stmt->execute([
            ':equipo_id'        => $equipoId,
            ':usuario_id'       => $usuarioId,
            ':asignado_por'     => $user['id'],
            ':fecha_asignacion' => $fechaAsignacion,
            ':observaciones'    => $body['observaciones'] ?? null,
        ]);

        // Actualizamos el responsable actual del equipo
        $stmtEquipo = $pdo->prepare('UPDATE equipos SET responsable_id = :usuario_id WHERE id = :equipo_id');
        $stmtEquipo->execute([':usuario_id' => $usuarioId, ':equipo_id' => $equipoId]);

        $stmtHist = $pdo->prepare(
            'INSERT INTO historial_movimientos (equipo_id, usuario_id, accion, detalle)
             VALUES (:equipo_id, :usuario_id, :accion, :detalle)'
        );
        $stmtHist->execute([
            ':equipo_id'  => $equipoId,
            ':usuario_id' => $user['id'],
            ':accion'     => 'ASIGNACION',
            ':detalle'    => 'Equipo asignado a un nuevo responsable',
        ]);

        $pdo->commit();
        Response::success(['id' => $pdo->lastInsertId()], 'Asignación registrada correctamente.', 201);
    } catch (PDOException $e) {
        $pdo->rollBack();
        Response::error('No se pudo registrar la asignación. ' . Response::translateDbError($e), 409);
    }
}

if ($method === 'PUT' && $action === 'devolver') {
    Auth::requireRole([Auth::ROL_ADMIN, Auth::ROL_TECNICO]);
    $body = Request::json();
    $id   = (int)($body['id'] ?? 0);

    $errIdCheck = Validator::enteroPositivo($id, 'ID');
    if ($errIdCheck) Response::error($errIdCheck, 422);

    try {
        $stmt = $pdo->prepare(
            'UPDATE asignaciones SET activa = 0, fecha_devolucion = :hoy WHERE id = :id AND activa = 1'
        );
        $stmt->execute([':hoy' => date('Y-m-d'), ':id' => $id]);

        if ($stmt->rowCount() === 0) {
            Response::error('Esta asignación ya fue cerrada o no existe.', 409);
        }

        Response::success(null, 'Devolución registrada correctamente.');
    } catch (PDOException $e) {
        Response::error(Response::translateDbError($e), 409);
    }
}

Response::error('Acción no encontrada.', 404);
