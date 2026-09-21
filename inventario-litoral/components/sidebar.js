/**
 * Renderiza el sidebar de navegación, consistente en todas las páginas.
 * @param {string} paginaActiva - id de la página actual (dashboard, equipos, ...)
 * @param {object} usuario - { nombre, rol }
 */
function renderSidebar(paginaActiva, usuario) {
    const links = [
        { id: 'dashboard',      href: 'dashboard.html',      label: 'Dashboard',    roles: null,
          icon: '<path d="M3 3h8v8H3zM13 3h8v5h-8zM13 10h8v11h-8zM3 13h8v8H3z"/>' },
        { id: 'equipos',        href: 'equipos.html',        label: 'Elementos',    roles: null,
          icon: '<path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/>' },
        { id: 'categorias',     href: 'categorias.html',     label: 'Categorías',   roles: null,
          icon: '<path d="M20.59 13.41 11 3.83A2 2 0 0 0 9.59 3.24L4 3a1 1 0 0 0-1 1l.24 5.59a2 2 0 0 0 .58 1.41l9.58 9.59a2 2 0 0 0 2.83 0l4.36-4.36a2 2 0 0 0 0-2.82z"/>' },
        { id: 'ubicaciones',    href: 'ubicaciones.html',    label: 'Ubicaciones',  roles: null,
          icon: '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>' },
        { id: 'asignaciones',   href: 'asignaciones.html',   label: 'Asignaciones', roles: null,
          icon: '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>' },
        { id: 'mantenimientos', href: 'mantenimientos.html', label: 'Mantenimientos', roles: null,
          icon: '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>' },
        { id: 'usuarios',       href: 'usuarios.html',       label: 'Usuarios',     roles: ['Administrador'],
          icon: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>' },
        { id: 'reportes',       href: 'reportes.html',       label: 'Reportes',     roles: null,
          icon: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>' },
        { id: 'perfil',         href: 'perfil.html',         label: 'Mi perfil',    roles: null,
          icon: '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>' },
    ];

    const linksHtml = links
        .filter((l) => !l.roles || l.roles.includes(usuario.rol))
        .map((l) => `
            <a href="${l.href}" class="sidebar-link ${l.id === paginaActiva ? 'active' : ''}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${l.icon}</svg>
                <span>${l.label}</span>
            </a>`)
        .join('');

    return `
        <button class="sidebar-toggle" id="btn-sidebar-toggle" aria-label="Abrir menú">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
        <div class="sidebar-backdrop" id="sidebar-backdrop"></div>
        <aside class="sidebar" id="app-sidebar">
            <div class="sidebar-logo">
                ${logoSvg(30)}
                <span class="sidebar-brand">Inventario Litoral</span>
                <button class="sidebar-close" id="btn-sidebar-close" aria-label="Cerrar menú">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            <nav class="flex flex-col gap-1">${linksHtml}</nav>
            <div class="sidebar-footer">
                <div>
                    <div style="font-weight:600;color:#fff;">${usuario.nombre}</div>
                    <div style="font-size:0.75rem;">${usuario.rol}</div>
                </div>
                <button class="icon-btn" id="btn-logout" title="Cerrar sesión">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </button>
            </div>
        </aside>`;
}

/** Activa la apertura/cierre del menú lateral en pantallas pequeñas. */
function activarMenuMovil() {
    const sidebar  = document.getElementById('app-sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');
    const btnOpen  = document.getElementById('btn-sidebar-toggle');
    const btnClose = document.getElementById('btn-sidebar-close');
    if (!sidebar) return;

    const abrir  = () => { sidebar.classList.add('open'); backdrop.classList.add('visible'); };
    const cerrar = () => { sidebar.classList.remove('open'); backdrop.classList.remove('visible'); };

    btnOpen?.addEventListener('click', abrir);
    btnClose?.addEventListener('click', cerrar);
    backdrop?.addEventListener('click', cerrar);

    // Al navegar a otra sección desde el móvil, el menú se cierra solo
    sidebar.querySelectorAll('.sidebar-link').forEach(link => link.addEventListener('click', cerrar));
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') cerrar(); });
}

function activarLogout() {
    const btn = document.getElementById('btn-logout');
    if (!btn) return;
    btn.addEventListener('click', async () => {
        const ok = await Ui.confirmar('¿Cerrar sesión?', 'Deberás iniciar sesión nuevamente para continuar.');
        if (!ok) return;
        await AuthService.logout();
        window.location.href = 'login.html';
    });
}
