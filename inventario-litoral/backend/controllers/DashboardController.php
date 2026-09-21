<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middlewares/Auth.php';
require_once __DIR__ . '/../helpers/Response.php';

Auth::requireAuth();
$pdo = Database::getConnection();

// Totales por estado (para el donut chart y las tarjetas de indicadores)
$stmtPorEstado = $pdo->query(
    'SELECT es.nombre AS estado, es.color, COUNT(e.id) AS total
     FROM estados es
     LEFT JOIN equipos e ON e.estado_id = es.id AND e.eliminado = 0
     GROUP BY es.id, es.nombre, es.color
     ORDER BY es.id'
);
$porEstado = $stmtPorEstado->fetchAll();

$totalEquipos = array_sum(array_column($porEstado, 'total'));

// Últimos elementos registrados (para la tabla del dashboard)
$stmtUltimos = $pdo->query(
    'SELECT e.id, e.codigo_inventario, e.nombre, es.nombre AS estado, es.color AS estado_color,
            c.nombre AS categoria, u.nombre AS ubicacion
     FROM equipos e
     INNER JOIN estados es ON es.id = e.estado_id
     LEFT JOIN categorias c ON c.id = e.categoria_id
     LEFT JOIN ubicaciones u ON u.id = e.ubicacion_id
     WHERE e.eliminado = 0
     ORDER BY e.fecha_registro DESC
     LIMIT 5'
);
$ultimos = $stmtUltimos->fetchAll();

// Mantenimientos activos (sin fecha de fin) — indicador operativo adicional
$stmtMantActivos = $pdo->query(
    'SELECT COUNT(*) AS total FROM mantenimientos WHERE fecha_fin IS NULL'
);
$mantenimientosActivos = (int)$stmtMantActivos->fetch()['total'];

// Asignaciones activas
$stmtAsigActivas = $pdo->query('SELECT COUNT(*) AS total FROM asignaciones WHERE activa = 1');
$asignacionesActivas = (int)$stmtAsigActivas->fetch()['total'];

Response::success([
    'total_equipos'          => $totalEquipos,
    'distribucion_por_estado'=> array_map(function ($fila) use ($totalEquipos) {
        $fila['total']      = (int)$fila['total'];
        $fila['porcentaje'] = $totalEquipos > 0 ? round(($fila['total'] / $totalEquipos) * 100) : 0;
        return $fila;
    }, $porEstado),
    'mantenimientos_activos' => $mantenimientosActivos,
    'asignaciones_activas'   => $asignacionesActivas,
    'ultimos_elementos'      => $ultimos,
]);
