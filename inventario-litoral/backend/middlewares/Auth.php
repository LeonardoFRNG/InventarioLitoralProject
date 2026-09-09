<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/Response.php';

/**
 * Maneja autenticación (login/logout) y autorización por rol.
 *
 * IMPORTANTE: la verificación de rol aquí ocurre en el BACKEND.
 * El frontend puede ocultar botones por comodidad de UX, pero la
 * autorización real y obligatoria siempre pasa por requireRole().
 */
class Auth
{
    public const ROL_ADMIN     = 1;
    public const ROL_TECNICO   = 2;
    public const ROL_CONSULTA  = 3;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_start();
        }
    }

    public static function attempt(string $email, string $password): array
    {
        $pdo = Database::getConnection();

        // Prepared statement: el email nunca se concatena al SQL.
        $stmt = $pdo->prepare(
            'SELECT u.id, u.nombre, u.email, u.password_hash, u.activo,
                    u.rol_id, r.nombre AS rol_nombre
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id
             WHERE u.email = :email
             LIMIT 1'
        );
        $stmt->execute([':email' => $email]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            return ['success' => false, 'message' => 'El correo o la contraseña son incorrectos.'];
        }

        if ((int)$usuario['activo'] === 0) {
            return ['success' => false, 'message' => 'Tu cuenta se encuentra inactiva. Contacta al administrador.'];
        }

        if (!password_verify($password, $usuario['password_hash'])) {
            return ['success' => false, 'message' => 'El correo o la contraseña son incorrectos.'];
        }

        self::start();
        session_regenerate_id(true);
        $_SESSION['usuario_id']   = $usuario['id'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['rol_id']       = (int)$usuario['rol_id'];
        $_SESSION['rol_nombre']   = $usuario['rol_nombre'];

        return [
            'success' => true,
            'usuario' => [
                'id'     => $usuario['id'],
                'nombre' => $usuario['nombre'],
                'email'  => $usuario['email'],
                'rol'    => $usuario['rol_nombre'],
            ],
        ];
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie('PHPSESSID', '', time() - 42000, $params['path'], $params['domain']);
        }
        session_destroy();
    }

    public static function currentUser(): ?array
    {
        self::start();
        if (!isset($_SESSION['usuario_id'])) {
            return null;
        }
        return [
            'id'     => $_SESSION['usuario_id'],
            'nombre' => $_SESSION['usuario_nombre'],
            'rol_id' => $_SESSION['rol_id'],
            'rol'    => $_SESSION['rol_nombre'],
        ];
    }

    /** Corta la ejecución con 401 si no hay sesión activa. */
    public static function requireAuth(): array
    {
        $user = self::currentUser();
        if (!$user) {
            Response::unauthorized('Debes iniciar sesión para acceder a esta información.');
        }
        return $user;
    }

    /**
     * Corta la ejecución con 403 si el usuario autenticado no tiene
     * uno de los roles permitidos. Uso:
     *   $user = Auth::requireRole([Auth::ROL_ADMIN]);
     */
    public static function requireRole(array $rolesPermitidos): array
    {
        $user = self::requireAuth();
        if (!in_array((int)$user['rol_id'], $rolesPermitidos, true)) {
            Response::forbidden('Tu rol (' . $user['rol'] . ') no tiene permiso para realizar esta acción.');
        }
        return $user;
    }

    public static function hashPassword(string $plain): string
    {
        return password_hash($plain, PASSWORD_BCRYPT);
    }
}
