<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../middlewares/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Validator.php';

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$body = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'POST' && $action === 'login') {
    $email    = trim($body['email'] ?? '');
    $password = (string)($body['password'] ?? '');

    $errores = Validator::ejecutar([
        fn() => Validator::requerido($email, 'Email'),
        fn() => Validator::requerido($password, 'Contraseña'),
        fn() => $email !== '' ? Validator::email($email) : null,
    ]);

    if (!empty($errores)) {
        Response::error('Revisa los datos ingresados.', 422, $errores);
    }

    $resultado = Auth::attempt($email, $password);

    if (!$resultado['success']) {
        Response::error($resultado['message'], 401);
    }

    Response::success($resultado['usuario'], 'Bienvenido, ' . $resultado['usuario']['nombre']);
}

if ($method === 'POST' && $action === 'logout') {
    Auth::logout();
    Response::success(null, 'Sesión cerrada correctamente.');
}

if ($method === 'GET' && $action === 'me') {
    $user = Auth::currentUser();
    if (!$user) {
        Response::unauthorized('No hay una sesión activa.');
    }
    Response::success($user);
}

// Autoservicio: cualquier usuario autenticado puede actualizar su propio
// nombre y contraseña (requiere confirmar la contraseña actual).
if ($method === 'PUT' && $action === 'actualizar_perfil') {
    $user = Auth::requireAuth();
    $pdo  = Database::getConnection();

    $nombre           = trim($body['nombre'] ?? '');
    $passwordActual   = (string)($body['password_actual'] ?? '');
    $passwordNueva    = (string)($body['password_nueva'] ?? '');

    $errores = Validator::ejecutar([
        fn() => Validator::requerido($nombre, 'Nombre'),
        fn() => $nombre !== '' ? Validator::nombrePersonaValido($nombre) : null,
        fn() => Validator::requerido($passwordActual, 'Contraseña actual'),
    ]);
    if (!empty($errores)) {
        Response::error('Revisa los datos ingresados.', 422, $errores);
    }

    $stmt = $pdo->prepare('SELECT password_hash FROM usuarios WHERE id = :id');
    $stmt->execute([':id' => $user['id']]);
    $actual = $stmt->fetch();

    if (!$actual || !password_verify($passwordActual, $actual['password_hash'])) {
        Response::error('La contraseña actual ingresada es incorrecta.', 401);
    }

    if ($passwordNueva !== '' && strlen($passwordNueva) < 8) {
        Response::error('La nueva contraseña debe tener al menos 8 caracteres.', 422);
    }

    $sql = 'UPDATE usuarios SET nombre = :nombre';
    $params = [':nombre' => $nombre, ':id' => $user['id']];
    if ($passwordNueva !== '') {
        $sql .= ', password_hash = :password_hash';
        $params[':password_hash'] = Auth::hashPassword($passwordNueva);
    }
    $sql .= ' WHERE id = :id';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $_SESSION['usuario_nombre'] = $nombre;
    Response::success(null, 'Perfil actualizado correctamente.');
}

Response::error('Acción no encontrada.', 404);
