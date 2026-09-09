<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middlewares/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/Request.php';

/**
 * Maneja CRUD de catálogos simples: categorias, ubicaciones, tipos_equipo, estados.
 *
 * Como el nombre de tabla NUNCA puede ir parametrizado en un prepared
 * statement, se restringe estrictamente a una lista blanca (whitelist).
 * Todo dato de usuario (nombre, descripción, id) sí va parametrizado.
 */

const CATALOGOS_PERMITIDOS = [
    'categorias'    => ['columnas' => ['nombre', 'descripcion'], 'tabla_equipos_fk' => 'categoria_id'],
    'ubicaciones'   => ['columnas' => ['nombre', 'dependencia'], 'tabla_equipos_fk' => 'ubicacion_id'],
    'tipos_equipo'  => ['columnas' => ['nombre'],                'tabla_equipos_fk' => 'tipo_id'],
    'estados'       => ['columnas' => ['nombre', 'color'],       'tabla_equipos_fk' => 'estado_id'],
];

Auth::requireAuth();

$tabla  = Request::query('tabla', '');
$action = Request::query('action', 'list');
$method = $_SERVER['REQUEST_METHOD'];

if (!array_key_exists($tabla, CATALOGOS_PERMITIDOS)) {
    Response::error('Catálogo no válido.', 400);
}

$config  = CATALOGOS_PERMITIDOS[$tabla];
$pdo     = Database::getConnection();

// Solo Administrador puede crear/editar/eliminar catálogos
$escrituraPermitida = fn() => Auth::requireRole([Auth::ROL_ADMIN]);

if ($method === 'GET' && $action === 'list') {
    // Nombre de tabla viene de la whitelist, nunca del usuario directamente -> seguro
    $stmt = $pdo->query("SELECT * FROM `$tabla` ORDER BY nombre ASC");
    Response::success($stmt->fetchAll());
}

if ($method === 'POST' && $action === 'create') {
    $escrituraPermitida();
    $body   = Request::json();
    $nombre = trim($body['nombre'] ?? '');

    $errores = Validator::ejecutar([
        fn() => Validator::requerido($nombre, 'Nombre'),
    ]);
    if (!empty($errores)) {
        Response::error('Revisa los datos ingresados.', 422, $errores);
    }

    $columnas = $config['columnas'];
    $placeholders = implode(', ', array_map(fn($c) => ":$c", $columnas));
    $columnasSql  = implode(', ', $columnas);

    $params = [];
    foreach ($columnas as $col) {
        $params[":$col"] = trim($body[$col] ?? null) ?: null;
    }
    $params[':nombre'] = $nombre; // aseguramos el nombre validado

    try {
        $stmt = $pdo->prepare("INSERT INTO `$tabla` ($columnasSql) VALUES ($placeholders)");
        $stmt->execute($params);
        Response::success(['id' => $pdo->lastInsertId()], 'Registro creado correctamente.', 201);
    } catch (PDOException $e) {
        Response::error(Response::translateDbError($e), 409);
    }
}

if ($method === 'PUT' && $action === 'update') {
    $escrituraPermitida();
    $body = Request::json();
    $id   = (int)($body['id'] ?? 0);

    $errIdCheck = Validator::enteroPositivo($id, 'ID');
    if ($errIdCheck) Response::error($errIdCheck, 422);

    $columnas = $config['columnas'];
    $sets = implode(', ', array_map(fn($c) => "$c = :$c", $columnas));

    $params = [':id' => $id];
    foreach ($columnas as $col) {
        $params[":$col"] = trim($body[$col] ?? null) ?: null;
    }

    try {
        $stmt = $pdo->prepare("UPDATE `$tabla` SET $sets WHERE id = :id");
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) {
            Response::notFound('El registro que intentas editar no existe.');
        }
        Response::success(null, 'Registro actualizado correctamente.');
    } catch (PDOException $e) {
        Response::error(Response::translateDbError($e), 409);
    }
}

if ($method === 'DELETE' && $action === 'delete') {
    $escrituraPermitida();
    $id = (int)Request::query('id', 0);

    $errIdCheck = Validator::enteroPositivo($id, 'ID');
    if ($errIdCheck) Response::error($errIdCheck, 422);

    // Verificamos dependencias antes de intentar borrar, para dar un
    // mensaje claro en vez de depender solo del error crudo de MySQL.
    $fkColumn = $config['tabla_equipos_fk'];
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) AS total FROM equipos WHERE `$fkColumn` = :id AND eliminado = 0");
    $stmtCheck->execute([':id' => $id]);
    $enUso = (int)$stmtCheck->fetch()['total'];

    if ($enUso > 0) {
        Response::error(
            "No se puede eliminar este registro porque está siendo usado por $enUso equipo(s). " .
            "Reasigna esos equipos a otro valor antes de eliminarlo.",
            409
        );
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM `$tabla` WHERE id = :id");
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) {
            Response::notFound('El registro que intentas eliminar no existe.');
        }
        Response::success(null, 'Registro eliminado correctamente.');
    } catch (PDOException $e) {
        Response::error(Response::translateDbError($e), 409);
    }
}

Response::error('Acción no encontrada.', 404);
