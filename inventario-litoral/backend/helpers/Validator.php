<?php
/**
 * Validaciones de backend (obligatorias, independientes del frontend).
 * Cada método retorna un mensaje de error claro en español, o null si es válido.
 */
class Validator
{
    public static function requerido($valor, string $campo): ?string
    {
        if ($valor === null || trim((string)$valor) === '') {
            return "El campo \"$campo\" es obligatorio.";
        }
        return null;
    }

    public static function email(string $email): ?string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'El correo electrónico ingresado no tiene un formato válido.';
        }
        return null;
    }

    public static function nombrePersonaValido(string $nombre): ?string
    {
        if (!preg_match('/[A-Za-zÀ-ÿ]/', $nombre)) {
            return 'El nombre no puede estar compuesto solo por números o símbolos.';
        }
        return null;
    }

    public static function enteroPositivo($valor, string $campo): ?string
    {
        if (!is_numeric($valor) || (int)$valor <= 0 || (string)(int)$valor !== (string)$valor) {
            return "El campo \"$campo\" debe ser un número entero positivo.";
        }
        return null;
    }

    public static function telefono(string $telefono): ?string
    {
        if (!preg_match('/^[0-9+\-\s]{7,15}$/', $telefono)) {
            return 'El número de teléfono ingresado no es válido (debe tener entre 7 y 15 dígitos).';
        }
        return null;
    }

    public static function fechaValida(string $fecha, string $campo): ?string
    {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            return "La fecha \"$campo\" no es válida.";
        }
        return null;
    }

    public static function rangoFechas(string $inicio, ?string $fin, string $etiquetaFin = 'fecha_fin'): ?string
    {
        if ($fin === null || $fin === '') {
            return null;
        }
        if (strtotime($fin) < strtotime($inicio)) {
            return "La $etiquetaFin no puede ser anterior a la fecha de inicio.";
        }
        return null;
    }

    public static function codigoInventario(string $codigo): ?string
    {
        if (!preg_match('/^[A-Za-z0-9\-]{3,30}$/', $codigo)) {
            return 'El código de inventario solo puede contener letras, números y guiones (3 a 30 caracteres).';
        }
        return null;
    }

    /**
     * Ejecuta una lista de validaciones (closures) y retorna el primer
     * arreglo de errores encontrado. Útil para validar un formulario completo.
     *
     * @param array<callable> $reglas
     * @return array<string> errores encontrados
     */
    public static function ejecutar(array $reglas): array
    {
        $errores = [];
        foreach ($reglas as $regla) {
            $resultado = $regla();
            if ($resultado !== null) {
                $errores[] = $resultado;
            }
        }
        return $errores;
    }
}
