<?php
/**
 * Conexión centralizada a la base de datos usando PDO.
 *
 * REGLA DE SEGURIDAD: en TODO el backend, cualquier consulta que reciba
 * datos externos (formularios, query params, JSON del frontend) DEBE
 * usar prepared statements con parámetros bind. Nunca concatenar
 * valores directamente dentro del SQL.
 *
 * Correcto:
 *   $stmt = $pdo->prepare("SELECT * FROM equipos WHERE codigo_inventario = :codigo");
 *   $stmt->execute([':codigo' => $codigo]);
 *
 * Prohibido:
 *   $pdo->query("SELECT * FROM equipos WHERE codigo_inventario = '$codigo'");
 */

class Database
{
    private static ?PDO $instance = null;

    // Ajusta estas credenciales según tu entorno local (XAMPP/WAMP).
    // Se usa un usuario dedicado con permisos limitados a esta base de
    // datos (buena práctica de seguridad: nunca conectar la app como root).
    // Ver database/database.sql -> sección "USUARIO DE APLICACIÓN" para
    // crear esta cuenta.
    private const HOST    = '127.0.0.1';
    private const PORT    = '3306';
    private const DBNAME  = 'inventario_litoral';
    private const USER    = 'inventario_app';
    private const PASS    = 'InventarioLitoral2026!';
    private const CHARSET = 'utf8mb4';

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                self::HOST,
                self::PORT,
                self::DBNAME,
                self::CHARSET
            );

            $options = [
                // Lanza excepciones en vez de fallar silenciosamente
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Devuelve arrays asociativos por defecto
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Usa prepared statements NATIVOS del driver (no emulados),
                // lo cual refuerza la protección contra inyección SQL.
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, self::USER, self::PASS, $options);
            } catch (PDOException $e) {
                // Nunca exponer el mensaje técnico real al cliente.
                error_log('Error de conexión a BD: ' . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'No fue posible conectar con el servidor. Intenta de nuevo más tarde.'
                ]);
                exit;
            }
        }

        return self::$instance;
    }
}
