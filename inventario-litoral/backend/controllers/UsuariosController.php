<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middlewares/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';
require_once __DIR__ . '/../helpers/Request.php';

$pdo    = Database::getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = Request::query('action', 'list');

// Endpoint reducido (solo id + nombre) para poblar selects de "responsable"
// en Asignaciones/Mantenimientos. Accesible a Admin y Técnico.
if ($method === 'GET' && $action === 'simple') {
    Auth::requireRole([Auth::ROL_ADMIN, Auth::ROL_TECNICO]);
    $stmt = $pdo->query('SELECT id, nombre FROM usuarios WHERE activo = 1 ORDER BY nombre ASC');
    Response::success($stmt->fetchAll());
}

// El resto de la gestión de usuarios (listar completo, crear, editar, desactivar)
// es exclusiva del rol Administrador.
$user = Auth::requireRole([Auth::ROL_ADMIN]);

if ($method === 'GET' && $action === 'list') {
    $busqueda = trim(Request::query('busqueda', ''));

    $sql = 'SELECT u.id, u.nombre, u.email, u.email_recuperacion, u.activo, u.rol_id, r.nombre AS rol
            FROM usuarios u INNER JOIN roles r ON r.id = u.rol_id';
    $params = [];
    if ($busqueda !== '') {
        $sql .= ' WHERE u.nombre LIKE :busqueda OR u.email LIKE :busqueda';
        $params[':busqueda'] = "%$busqueda%";
    }
    $sql .= ' ORDER BY u.id ASC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    Response::success($stmt->fetchAll());
}

if ($method === 'GET' && $action === 'roles') {
    // Útil para poblar el <select> de roles en el formulario
    $stmt = $pdo->query('SELECT id, nombre FROM roles ORDER BY id ASC');
    Response::success($stmt->fetchAll());
}

if ($method === 'POST' && $action === 'create') {
    $body = Request::json();

    $nombre   = trim($body['nombre'] ?? '');
    $email    = trim($body['email'] ?? '');
    $password = (string)($body['password'] ?? '');
    $rolId    = $body['rol_id'] ?? null;

    $errores = Validator::ejecutar([
        fn() => Validator::requerido($nombre, 'Nombre'),
        fn() => $nombre !== '' ? Validator::nombrePersonaValido($nombre) : null,
        fn() => Validator::requerido($email, 'Email'),
        fn() => $email !== '' ? Validator::email($email) : null,
        fn() => Validator::requerido($password, 'Contraseña'),
        fn() => strlen($password) < 8 ? 'La contraseña debe tener al menos 8 caracteres.' : null,
        fn() => Validator::requerido($rolId, 'Rol'),
    ]);
    if (!empty($errores)) {
        Response::error('No se pudo crear el usuario. Revisa los datos.', 422, $errores);
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO usuarios (nombre, email, email_recuperacion, password_hash, rol_id)
             VALUES (:nombre, :email, :email_recuperacion, :password_hash, :rol_id)'
        );
        $stmt->execute([
            ':nombre'             => $nombre,
            ':email'              => $email,
            ':email_recuperacion' => trim($body['email_recuperacion'] ?? '') ?: $email,
            ':password_hash'      => Auth::hashPassword($password),
            ':rol_id'             => $rolId,
        ]);
        Response::success(['id' => $pdo->lastInsertId()], "Usuario \"$nombre\" creado correctamente.", 201);
    } catch (PDOException $e) {
        Response::error(Response::translateDbError($e), 409);
    }
}

if ($method === 'PUT' && $action === 'update') {
    $body = Request::json();
    $id   = (int)($body['id'] ?? 0);

    $errIdCheck = Validator::enteroPositivo($id, 'ID');
    if ($errIdCheck) Response::error($errIdCheck, 422);

    $nombre = trim($body['nombre'] ?? '');
    $email  = trim($body['email'] ?? '');
    $rolId  = $body['rol_id'] ?? null;

    $errores = Validator::ejecutar([
        fn() => Validator::requerido($nombre, 'Nombre'),
        fn() => $nombre !== '' ? Validator::nombrePersonaValido($nombre) : null,
        fn() => Validator::requerido($email, 'Email'),
        fn() => $email !== '' ? Validator::email($email) : null,
        fn() => Validator::requerido($rolId, 'Rol'),
    ]);
    if (!empty($errores)) {
        Response::error('No se pudo actualizar el usuario. Revisa los datos.', 422, $errores);
    }

    // Evitar que un admin se quite a sí mismo el único rol de administrador
    if ((int)$id === (int)$user['id'] && (int)$rolId !== Auth::ROL_ADMIN) {
        $stmtOtros = $pdo->prepare('SELECT COUNT(*) AS total FROM usuarios WHERE rol_id = :rol AND id != :id AND activo = 1');
        $stmtOtros->execute([':rol' => Auth::ROL_ADMIN, ':id' => $id]);
        if ((int)$stmtOtros->fetch()['total'] === 0) {
            Response::error('No puedes quitarte el rol de Administrador: eres el único administrador activo del sistema.', 409);
        }
    }

    $sql = 'UPDATE usuarios SET nombre = :nombre, email = :email,
                email_recuperacion = :email_recuperacion, rol_id = :rol_id';
    $params = [
        ':nombre'             => $nombre,
        ':email'              => $email,
        ':email_recuperacion' => trim($body['email_recuperacion'] ?? '') ?: $email,
        ':rol_id'             => $rolId,
        ':id'                 => $id,
    ];

    // Solo actualizar contraseña si se proporcionó una nueva
    if (!empty($body['password'])) {
        if (strlen($body['password']) < 8) {
            Response::error('La nueva contraseña debe tener al menos 8 caracteres.', 422);
        }
        $sql .= ', password_hash = :password_hash';
        $params[':password_hash'] = Auth::hashPassword($body['password']);
    }

    $sql .= ' WHERE id = :id';

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() === 0 && !$pdo->lastInsertId()) {
            // rowCount 0 puede significar "no encontrado" o "sin cambios"; verificamos existencia
            $check = $pdo->prepare('SELECT COUNT(*) AS total FROM usuarios WHERE id = :id');
            $check->execute([':id' => $id]);
            if ((int)$check->fetch()['total'] === 0) {
                Response::notFound('El usuario que intentas editar no existe.');
            }
        }
        Response::success(null, 'Usuario actualizado correctamente.');
    } catch (PDOException $e) {
        Response::error(Response::translateDbError($e), 409);
    }
}

if ($method === 'DELETE' && $action === 'delete') {
    $id = (int)Request::query('id', 0);

    $errIdCheck = Validator::enteroPositivo($id, 'ID');
    if ($errIdCheck) Response::error($errIdCheck, 422);

    if ((int)$id === (int)$user['id']) {
        Response::error('No puedes eliminar tu propio usuario mientras tienes la sesión activa.', 409);
    }

    // Desactivación lógica en vez de borrado físico: preserva quién hizo
    // qué en el historial (mantenimientos, asignaciones, movimientos).
    try {
        $stmt = $pdo->prepare('UPDATE usuarios SET activo = 0 WHERE id = :id');
        $stmt->execute([':id' => $id]);
        if ($stmt->rowCount() === 0) {
            Response::notFound('El usuario que intentas eliminar no existe.');
        }
        Response::success(null, 'Usuario desactivado correctamente. Su historial de acciones se conserva.');
    } catch (PDOException $e) {
        Response::error(Response::translateDbError($e), 409);
    }
}

Response::error('Acción no encontrada.', 404);
