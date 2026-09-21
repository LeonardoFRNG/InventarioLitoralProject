<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middlewares/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';
require_once __DIR__ . '/../helpers/Request.php';
require_once __DIR__ . '/../helpers/XlsxWriter.php';

Auth::requireAuth(); // Consulta, Técnico y Admin pueden generar reportes

$pdo    = Database::getConnection();
$action = Request::query('action', 'inventario');
$formato = Request::query('formato', 'excel'); // excel | pdf

// ---------------------------------------------------------------------
// Filtros comunes (todos opcionales, todos parametrizados)
// ---------------------------------------------------------------------
$estadoId    = Request::query('estado_id');
$categoriaId = Request::query('categoria_id');
$ubicacionId = Request::query('ubicacion_id');
$responsableId = Request::query('responsable_id');
$fechaDesde  = Request::query('fecha_desde');
$fechaHasta  = Request::query('fecha_hasta');

function construirFiltrosInventario(): array
{
    $where  = ['e.eliminado = 0'];
    $params = [];

    if ($v = Request::query('estado_id'))      { $where[] = 'e.estado_id = :estado_id';       $params[':estado_id'] = $v; }
    if ($v = Request::query('categoria_id'))   { $where[] = 'e.categoria_id = :categoria_id';  $params[':categoria_id'] = $v; }
    if ($v = Request::query('ubicacion_id'))   { $where[] = 'e.ubicacion_id = :ubicacion_id';  $params[':ubicacion_id'] = $v; }
    if ($v = Request::query('responsable_id')) { $where[] = 'e.responsable_id = :responsable_id'; $params[':responsable_id'] = $v; }
    if ($v = Request::query('fecha_desde'))    { $where[] = 'DATE(e.fecha_registro) >= :fecha_desde'; $params[':fecha_desde'] = $v; }
    if ($v = Request::query('fecha_hasta'))    { $where[] = 'DATE(e.fecha_registro) <= :fecha_hasta'; $params[':fecha_hasta'] = $v; }

    return [implode(' AND ', $where), $params];
}

// =====================================================================
// REPORTE: INVENTARIO GENERAL
// =====================================================================
if ($action === 'inventario') {
    [$whereSql, $params] = construirFiltrosInventario();

    $stmt = $pdo->prepare(
        "SELECT e.codigo_inventario AS 'Código', e.nombre AS 'Nombre', e.serial AS 'Serial',
                t.nombre AS 'Tipo', c.nombre AS 'Categoría', u.nombre AS 'Ubicación',
                es.nombre AS 'Estado', res.nombre AS 'Responsable',
                DATE_FORMAT(e.fecha_registro, '%d/%m/%Y') AS 'Fecha de registro'
         FROM equipos e
         INNER JOIN tipos_equipo t ON t.id = e.tipo_id
         LEFT JOIN categorias c   ON c.id = e.categoria_id
         LEFT JOIN ubicaciones u  ON u.id = e.ubicacion_id
         INNER JOIN estados es    ON es.id = e.estado_id
         LEFT JOIN usuarios res   ON res.id = e.responsable_id
         WHERE $whereSql
         ORDER BY e.codigo_inventario ASC"
    );
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_NUM);
    $encabezados = ['Código', 'Nombre', 'Serial', 'Tipo', 'Categoría', 'Ubicación', 'Estado', 'Responsable', 'Fecha de registro'];

    exportar('Inventario General', $encabezados, $filas, $formato, 'reporte_inventario',
        [14, 26, 16, 14, 18, 16, 14, 20, 16]);
}

// =====================================================================
// REPORTE: MANTENIMIENTOS
// =====================================================================
if ($action === 'mantenimientos') {
    $where  = ['1=1'];
    $params = [];
    if ($v = Request::query('fecha_desde')) { $where[] = 'm.fecha_inicio >= :fecha_desde'; $params[':fecha_desde'] = $v; }
    if ($v = Request::query('fecha_hasta')) { $where[] = 'm.fecha_inicio <= :fecha_hasta'; $params[':fecha_hasta'] = $v; }
    $whereSql = implode(' AND ', $where);

    $stmt = $pdo->prepare(
        "SELECT e.codigo_inventario AS 'Código equipo', e.nombre AS 'Equipo', m.tipo_mantenimiento AS 'Tipo',
                m.descripcion AS 'Descripción', tec.nombre AS 'Técnico',
                DATE_FORMAT(m.fecha_inicio, '%d/%m/%Y') AS 'Fecha inicio',
                COALESCE(DATE_FORMAT(m.fecha_fin, '%d/%m/%Y'), 'En curso') AS 'Fecha fin'
         FROM mantenimientos m
         INNER JOIN equipos e ON e.id = m.equipo_id
         INNER JOIN usuarios tec ON tec.id = m.tecnico_id
         WHERE $whereSql
         ORDER BY m.fecha_inicio DESC"
    );
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_NUM);
    $encabezados = ['Código equipo', 'Equipo', 'Tipo', 'Descripción', 'Técnico', 'Fecha inicio', 'Fecha fin'];

    exportar('Mantenimientos', $encabezados, $filas, $formato, 'reporte_mantenimientos',
        [14, 24, 14, 36, 20, 14, 14]);
}

// =====================================================================
// REPORTE: HISTORIAL / TRAZABILIDAD
// =====================================================================
if ($action === 'historial') {
    $equipoId = Request::query('equipo_id');
    $where  = ['1=1'];
    $params = [];
    if ($equipoId) { $where[] = 'h.equipo_id = :equipo_id'; $params[':equipo_id'] = $equipoId; }
    $whereSql = implode(' AND ', $where);

    $stmt = $pdo->prepare(
        "SELECT e.codigo_inventario AS 'Código equipo', h.accion AS 'Acción',
                COALESCE(ea.nombre,'-') AS 'Estado anterior', COALESCE(en.nombre,'-') AS 'Estado nuevo',
                COALESCE(us.nombre,'Sistema') AS 'Ejecutado por', h.detalle AS 'Detalle',
                DATE_FORMAT(h.fecha, '%d/%m/%Y %H:%i') AS 'Fecha'
         FROM historial_movimientos h
         INNER JOIN equipos e ON e.id = h.equipo_id
         LEFT JOIN estados ea ON ea.id = h.estado_anterior_id
         LEFT JOIN estados en ON en.id = h.estado_nuevo_id
         LEFT JOIN usuarios us ON us.id = h.usuario_id
         WHERE $whereSql
         ORDER BY h.fecha DESC"
    );
    $stmt->execute($params);
    $filas = $stmt->fetchAll(PDO::FETCH_NUM);
    $encabezados = ['Código equipo', 'Acción', 'Estado anterior', 'Estado nuevo', 'Ejecutado por', 'Detalle', 'Fecha'];

    exportar('Historial', $encabezados, $filas, $formato, 'reporte_historial',
        [14, 16, 16, 16, 20, 34, 18]);
}

Response::error('Reporte no encontrado.', 404);

// =====================================================================
// Funciones de exportación
// =====================================================================
function exportar(string $titulo, array $encabezados, array $filas, string $formato, string $nombreArchivo, array $anchos = []): void
{
    if (empty($filas) && $formato !== 'json') {
        // Igual generamos el archivo pero con una fila indicativa, para no
        // entregar un Excel vacío y confuso.
    }

    if ($formato === 'excel') {
        $writer = new XlsxWriter();
        $writer->agregarHoja($titulo, $encabezados, $filas, $anchos);

        $ruta = sys_get_temp_dir() . "/{$nombreArchivo}_" . date('Ymd_His') . '.xlsx';
        $writer->guardar($ruta);

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $nombreArchivo . '_' . date('Ymd') . '.xlsx"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        unlink($ruta);
        exit;
    }

    if ($formato === 'pdf') {
        header('Content-Type: text/html; charset=utf-8');
        echo generarHtmlImprimible($titulo, $encabezados, $filas);
        exit;
    }

    Response::error('Formato de reporte no soportado. Usa "excel" o "pdf".', 400);
}

/**
 * Genera un HTML listo para imprimir/guardar como PDF desde el navegador
 * (window.print() -> "Guardar como PDF"). Evita depender de librerías
 * PDF externas no disponibles sin Composer.
 */
function generarHtmlImprimible(string $titulo, array $encabezados, array $filas): string
{
    $filasHtml = '';
    foreach ($filas as $fila) {
        $celdas = implode('', array_map(fn($v) => '<td>' . htmlspecialchars((string)$v) . '</td>', $fila));
        $filasHtml .= "<tr>$celdas</tr>";
    }
    $encabezadosHtml = implode('', array_map(fn($h) => '<th>' . htmlspecialchars($h) . '</th>', $encabezados));

    $fecha = date('d/m/Y H:i');

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{$titulo} - Inventario Litoral</title>
<style>
    body { font-family: Arial, sans-serif; color: #1e1e1e; margin: 30px; }
    h1 { color: #4c1d95; margin-bottom: 4px; }
    .fecha { color: #666; font-size: 12px; margin-bottom: 20px; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th { background: #4c1d95; color: #fff; text-align: left; padding: 8px; }
    td { padding: 6px 8px; border-bottom: 1px solid #ddd; }
    tr:nth-child(even) { background: #f5f3ff; }
    @media print { .no-print { display: none; } }
</style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Imprimir / Guardar como PDF</button>
    <h1>{$titulo}</h1>
    <div class="fecha">Generado el {$fecha} — Inventario Litoral</div>
    <table>
        <thead><tr>{$encabezadosHtml}</tr></thead>
        <tbody>{$filasHtml}</tbody>
    </table>
</body>
</html>
HTML;
}
