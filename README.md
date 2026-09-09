# Inventario Litoral — Sistema de Inventario Tecnológico

Sistema web para la gestión centralizada de activos tecnológicos (computadores,
portátiles, monitores, impresoras, periféricos, aires acondicionados, etc.),
desarrollado para reemplazar el manejo manual en Excel.

## Descripción

Permite registrar equipos con código de inventario único, controlar su ciclo
de vida completo (asignaciones, mantenimientos, cambios de estado), gestionar
usuarios con 3 roles (Administrador, Técnico, Consulta), generar reportes
exportables (Excel/PDF) y visualizar un dashboard con indicadores en tiempo
real, todo con trazabilidad completa de quién hizo qué y cuándo.

## Tecnologías

| Capa       | Tecnología |
|------------|------------|
| Frontend   | HTML5, Tailwind (utilidades vía CSS propio + clases utilitarias), JavaScript ES6+ (vanilla, sin frameworks) |
| Backend    | PHP 8+ (sin framework, arquitectura MVC ligera propia) |
| Base de datos | MySQL / MariaDB 10.11+ |
| Reportes   | Generador Excel (.xlsx) nativo en PHP (sin dependencias de Composer) + exportación PDF vía HTML imprimible |
| Autenticación | Sesiones PHP + `password_hash()` (bcrypt) |

> **Nota sobre Excel**: el entorno de desarrollo no tenía acceso a Packagist/Composer,
> así que en vez de PhpSpreadsheet se construyó `backend/helpers/XlsxWriter.php`,
> un generador de `.xlsx` nativo (usa `ZipArchive`, sin dependencias externas).
> Genera archivos reales de Excel 2007+ con encabezados en negrita, colores y
> anchos de columna — verificado abriéndolo con LibreOffice. Si más adelante
> tienes acceso a Composer, puedes reemplazarlo por PhpSpreadsheet sin tocar
> el resto del código: solo cambia la función `exportar()` en `ReportesController.php`.

## Arquitectura

```
inventario-litoral/
├── index.html                  # Redirige a login o dashboard según sesión
├── pages/                      # Todas las vistas (login, dashboard, equipos...)
├── components/
│   └── sidebar.js              # Sidebar reutilizable, consistente en toda la app
├── css/
│   └── styles.css              # Design System: tokens, colores, componentes
├── js/
│   ├── api/api.js              # Cliente fetch centralizado (maneja sesión/errores)
│   ├── services/               # Una función por endpoint del backend
│   ├── controllers/            # Lógica reutilizable de páginas (ej. catálogos)
│   ├── utils/ui.js             # Toasts, confirmaciones, badges, formato de fechas
│   └── validators/validators.js# Validación de formularios en frontend
├── backend/
│   ├── config/database.php     # Conexión PDO (prepared statements obligatorios)
│   ├── controllers/            # Un archivo por módulo (Equipos, Usuarios, ...)
│   ├── middlewares/Auth.php    # Sesión, requireAuth(), requireRole()
│   └── helpers/                # Response.php, Validator.php, XlsxWriter.php, Request.php
└── database/
    └── database.sql            # Script completo: esquema + datos de prueba
```

## Instalación (XAMPP / WAMP)

1. Copia la carpeta `inventario-litoral` dentro de `htdocs` (XAMPP) o `www` (WAMP).
2. Abre phpMyAdmin (o el cliente `mysql`) e importa `database/database.sql`.
   Esto crea la base de datos `inventario_litoral`, todas las tablas, los
   datos de prueba **y** el usuario de aplicación dedicado.
3. Verifica que `backend/config/database.php` tenga las credenciales correctas:
   ```php
   USER = 'inventario_app'
   PASS = 'InventarioLitoral2026!'
   ```
   (ya vienen configuradas por defecto, coincidiendo con lo que crea `database.sql`).
4. Abre `http://localhost/inventario-litoral/` en el navegador.

## Base de datos

11 tablas: `roles`, `usuarios`, `tipos_equipo`, `categorias`, `estados`,
`ubicaciones`, `equipos`, `asignaciones`, `mantenimientos`,
`historial_movimientos`, `sesiones`.

Integridad garantizada con `PRIMARY KEY`, `FOREIGN KEY`, `UNIQUE`
(código de inventario, serial, email), `NOT NULL`, `CHECK` (fechas
coherentes, formato de email, nombres no numéricos) e índices en las
columnas de búsqueda frecuente.

## Variables de entorno / configuración

Este proyecto no usa un `.env` (por simplicidad para un entorno académico
XAMPP/WAMP); la configuración vive directamente en
`backend/config/database.php`. Para producción, se recomienda mover esas
constantes a variables de entorno reales.

## Usuarios de prueba

| Email | Contraseña | Rol |
|---|---|---|
| admin@litoral.com | Admin123! | Administrador |
| leonardo@litoral.com | Admin123! | Técnico |
| consulta@litoral.com | Admin123! | Consulta |

## Roles y permisos

| Acción | Administrador | Técnico | Consulta |
|---|:---:|:---:|:---:|
| Ver dashboard, listados y reportes | ✅ | ✅ | ✅ |
| Crear / editar equipos | ✅ | ✅ | ❌ |
| Dar de baja equipos | ✅ | ❌ | ❌ |
| Registrar asignaciones y mantenimientos | ✅ | ✅ | ❌ |
| Gestionar categorías / ubicaciones / tipos / estados | ✅ | ❌ | ❌ |
| Gestionar usuarios | ✅ | ❌ | ❌ |
| Exportar reportes Excel/PDF | ✅ | ✅ | ✅ |

La autorización se verifica **siempre en el backend** (`Auth::requireRole()`),
no solo ocultando botones en el frontend. Un rol sin permiso recibe `403`.

## Funcionalidades

- **Dashboard**: indicadores dinámicos (total de equipos, mantenimientos y
  asignaciones activas) y gráfico de distribución por estado, calculados
  en tiempo real desde la base de datos.
- **Inventario (Equipos)**: CRUD completo con búsqueda, filtros (estado,
  categoría, ubicación) y paginación. Código de inventario y serial únicos.
  Eliminación = baja lógica (nunca se pierde el historial).
- **Categorías / Ubicaciones**: catálogos administrables, con bloqueo de
  eliminación si están en uso por algún equipo.
- **Asignaciones**: asigna un equipo a un responsable; cierra automáticamente
  cualquier asignación activa previa del mismo equipo.
- **Mantenimientos**: preventivos/correctivos, con actualización opcional
  del estado del equipo y registro automático en el historial.
- **Usuarios** (solo Administrador): CRUD con contraseñas hasheadas
  (bcrypt), desactivación lógica en vez de borrado físico.
- **Reportes**: Excel bien formateado (encabezados en negrita, colores,
  anchos de columna) y vista PDF imprimible, con filtros por estado,
  categoría, ubicación, responsable y rango de fechas.
- **Trazabilidad**: cada creación, cambio de estado, asignación y
  mantenimiento queda registrado en `historial_movimientos` con quién,
  cuándo y qué cambió.
- **Seguridad**: 100% de las consultas usan *prepared statements* (PDO con
  `ATTR_EMULATE_PREPARES = false`), contraseñas con `password_hash()`
  (bcrypt), sesiones `httponly`, autorización verificada en backend,
  mensajes de error claros sin exponer detalles técnicos internos.

## Cómo ejecutar y probar

```bash
# Servidor de desarrollo rápido (alternativa a XAMPP)
php -S localhost:8090 -t inventario-litoral/
```

Luego abre `http://localhost:8090/`.

Casos de prueba sugeridos:
1. Iniciar sesión con cada uno de los 3 usuarios de prueba y verificar que
   el menú lateral y los botones cambian según el rol.
2. Intentar registrar un equipo con un código de inventario ya existente
   (ej. `INV-827`) → debe mostrar un mensaje claro, no un error técnico.
3. Iniciar sesión como Consulta e intentar crear un equipo directamente
   por la API → debe responder `403`.
4. Dar de baja un equipo y verificar que su historial se conserva
   (botón "Ver detalle" en Inventario).
5. Generar un reporte Excel desde la sección Reportes y abrirlo — debe
   verse con encabezado morado en negrita y columnas con ancho adecuado.

## Mejoras propuestas (no requeridas por la rúbrica original)

- Recuperación de contraseña por correo electrónico (actualmente el enlace
  "¿Olvidaste tu contraseña?" solo indica contactar al administrador).
- Notificaciones automáticas de mantenimientos preventivos próximos a vencer.
- Exportación de reportes con gráficos incluidos dentro del Excel.
- Autenticación de dos factores para el rol Administrador.
