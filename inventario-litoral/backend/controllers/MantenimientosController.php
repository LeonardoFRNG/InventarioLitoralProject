<?php
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

    $sql = 'SELECT m.*, e.codigo_inventario, e.nombre AS equipo_nombre,
                   t.nombre AS tecnico_nombre, es.nombre AS estado_resultante
            FROM mantenimientos m
            INNER JOIN equipos e ON e.id = m.equipo_id
            INNER JOIN usuarios t ON t.id = m.tecnico_id
            LEFT JOIN estados es ON es.id = m.estado_resultante_id';
    $params = [];
    if ($equipoId) {
        $sql .= ' WHERE m.equipo_id = :equipo_id';
        $params[':equipo_id'] = $equipoId;
    }
    $sql .= ' ORDER BY m.fecha_inicio DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    Response::success($stmt->fetchAll());
}

if ($method === 'POST' && $action === 'create') {
    // Un Técnico puede registrar sus propios mantenimientos; el Admin también.
    Auth::requireRole([Auth::ROL_ADMIN, Auth::ROL_TECNICO]);
    $body = Request::json();

    $equipoId    = $body['equipo_id'] ?? null;
    $descripcion = trim($body['descripcion'] ?? '');
    $fechaInicio = trim($body['fecha_inicio'] ?? '');
    $fechaFin    = trim($body['fecha_fin'] ?? '') ?: null;
    $tipo        = $body['tipo_mantenimiento'] ?? 'PREVENTIVO';

    $errores = Validator::ejecutar([
        fn() => Validator::requerido($equipoId, 'Equipo'),
        fn() => Validator::requerido($descripcion, 'Descripción'),
        fn() => Validator::requerido($fechaInicio, 'Fecha de inicio'),
        fn() => $fechaInicio !== '' ? Validator::fechaValida($fechaInicio, 'fecha de inicio') : null,
        fn() => $fechaFin ? Validator::fechaValida($fechaFin, 'fecha de fin') : null,
        fn() => $fechaFin ? Validator::rangoFechas($fechaInicio, $fechaFin, 'fecha de finalización') : null,
        fn() => !in_array($tipo, ['PREVENTIVO', 'CORRECTIVO'], true)
                    ? 'El tipo de mantenimiento debe ser PREVENTIVO o CORRECTIVO.' : null,
    ]);
    if (!empty($errores)) {
        Response::error('No se pudo registrar el mantenimiento. Revisa los datos.', 422, $errores);
    }

    $estadoResultanteId = $body['estado_resultante_id'] ?? null;

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO mantenimientos
                (equipo_id, tecnico_id, tipo_mantenimiento, descripcion, fecha_inicio, fecha_fin, estado_resultante_id)
             VALUES (:equipo_id, :tecnico_id, :tipo, :descripcion, :fecha_inicio, :fecha_fin, :estado_resultante_id)'
        );
        $stmt->execute([
            ':equipo_id'            => $equipoId,
            ':tecnico_id'           => $user['id'],
            ':tipo'                 => $tipo,
            ':descripcion'          => $descripcion,
            ':fecha_inicio'         => $fechaInicio,
            ':fecha_fin'            => $fechaFin,
            ':estado_resultante_id' => $estadoResultanteId,
        ]);
        $mantenimientoId = $pdo->lastInsertId();

        // Si se indicó un estado resultante, se refleja también en el equipo
        if ($estadoResultanteId) {
            $stmtActual = $pdo->prepare('SELECT estado_id FROM equipos WHERE id = :id');
            $stmtActual->execute([':id' => $equipoId]);
            $estadoAnterior = $stmtActual->fetch()['estado_id'] ?? null;

            $stmtUpd = $pdo->prepare('UPDATE equipos SET estado_id = :estado WHERE id = :id');
            $stmtUpd->execute([':estado' => $estadoResultanteId, ':id' => $equipoId]);

            $stmtHist = $pdo->prepare(
                'INSERT INTO historial_movimientos
                    (equipo_id, usuario_id, accion, estado_anterior_id, estado_nuevo_id, detalle)
                 VALUES (:equipo_id, :usuario_id, :accion, :estado_anterior, :estado_nuevo, :detalle)'
            );
            $stmtHist->execute([
                ':equipo_id'      => $equipoId,
                ':usuario_id'     => $user['id'],
                ':accion'         => 'MANTENIMIENTO',
                ':estado_anterior'=> $estadoAnterior,
                ':estado_nuevo'   => $estadoResultanteId,
                ':detalle'        => "Mantenimiento $tipo registrado: $descripcion",
            ]);
        }

        $pdo->commit();
        Response::success(['id' => $mantenimientoId], 'Mantenimiento registrado correctamente.', 201);
    } catch (PDOException $e) {
        $pdo->rollBack();
        Response::error('No se pudo registrar el mantenimiento. ' . Response::translateDbError($e), 409);
    }
}

Response::error('Acción no encontrada.', 404);
