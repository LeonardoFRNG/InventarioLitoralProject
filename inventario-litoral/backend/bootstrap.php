<?php
/**
 * Bootstrap compartido por todos los controladores.
 *
 * PROBLEMA QUE RESUELVE: si algo falla (warning de PHP, notice, error de
 * MySQL no capturado, etc.), por defecto PHP imprime HTML de error ANTES
 * del JSON, y el frontend recibe una respuesta que no puede parsear
 * ("El servidor devolvió una respuesta inesperada"). Este archivo se
 * asegura de que SIEMPRE se devuelva JSON, incluso si algo explota.
 */

// No mostrar errores como HTML en la respuesta (however sí los registramos)
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

// Si por alguna razón ya se envió salida antes de tiempo, la limpiamos
if (ob_get_level() === 0) {
    ob_start();
}

/**
 * Convierte cualquier excepción no capturada en una respuesta JSON,
 * en vez de dejar que PHP imprima un stack trace en HTML.
 */
set_exception_handler(function (Throwable $e) {
    while (ob_get_level() > 0) { ob_end_clean(); }
    error_log('Excepción no capturada: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'message' => 'Ocurrió un error inesperado en el servidor.',
        // Detalle solo para depuración local; quítalo en producción.
        'debug'   => $e->getMessage(),
    ]);
    exit;
});

/**
 * Convierte errores fatales de PHP (los que no son excepciones, como
 * llamar a una función que no existe) también en JSON.
 */
register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        while (ob_get_level() > 0) { ob_end_clean(); }
        error_log('Error fatal: ' . $error['message'] . ' en ' . $error['file'] . ':' . $error['line']);
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Ocurrió un error inesperado en el servidor.',
            'debug'   => $error['message'] . ' (' . $error['file'] . ':' . $error['line'] . ')',
        ]);
    }
});
