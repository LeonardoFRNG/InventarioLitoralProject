<?php
/**
 * Estandariza el formato de todas las respuestas de la API,
 * incluyendo mensajes de error claros y no técnicos para el usuario.
 */
class Response
{
    public static function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success($data = null, string $message = 'Operación exitosa', int $statusCode = 200): void
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, array $errors = []): void
    {
        self::json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], $statusCode);
    }

    public static function unauthorized(string $message = 'Debes iniciar sesión para continuar.'): void
    {
        self::error($message, 401);
    }

    public static function forbidden(string $message = 'No tienes permisos para realizar esta acción.'): void
    {
        self::error($message, 403);
    }

    public static function notFound(string $message = 'El recurso solicitado no existe.'): void
    {
        self::error($message, 404);
    }

    /**
     * Traduce errores técnicos de MySQL/PDO a mensajes claros para el usuario,
     * sin exponer detalles internos (SQLSTATE, nombres de tablas, etc.).
     */
    public static function translateDbError(PDOException $e): string
    {
        $code = $e->errorInfo[1] ?? null;

        // 1062 = Duplicate entry (violación de UNIQUE)
        if ($code === 1062) {
            if (str_contains($e->getMessage(), 'uq_equipos_codigo')) {
                return 'El código de inventario ingresado ya está registrado en otro equipo.';
            }
            if (str_contains($e->getMessage(), 'uq_equipos_serial')) {
                return 'El número de serie ingresado ya está asociado a otro equipo.';
            }
            if (str_contains($e->getMessage(), 'uq_usuarios_email')) {
                return 'El correo electrónico ingresado ya está registrado.';
            }
            return 'Ya existe un registro con ese valor único. Verifica los datos ingresados.';
        }

        // 1451 = Cannot delete or update a parent row (FK RESTRICT)
        if ($code === 1451) {
            return 'No es posible eliminar este registro porque tiene información asociada (mantenimientos, asignaciones o historial).';
        }

        // 4025 / 3819 = CHECK constraint failed (fechas, formato, etc.)
        if ($code === 4025 || $code === 3819) {
            return 'Los datos ingresados no cumplen con las reglas de validación (revisa fechas o formatos).';
        }

        error_log('Error de base de datos no traducido: ' . $e->getMessage());
        return 'No fue posible completar la operación. Intenta de nuevo.';
    }
}
