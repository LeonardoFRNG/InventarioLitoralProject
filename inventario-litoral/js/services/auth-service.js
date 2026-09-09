/**
 * Servicio de autenticación: envuelve las llamadas a AuthController
 * y protege páginas que requieren sesión iniciada.
 */
const AuthService = {
    async login(email, password) {
        return Api.post('AuthController.php?action=login', { email, password });
    },

    async logout() {
        return Api.post('AuthController.php?action=logout', {});
    },

    async me() {
        return Api.get('AuthController.php?action=me');
    },

    /** Redirige a login si no hay sesión. Debe llamarse al cargar cada página protegida. */
    async requireSession() {
        try {
            const res = await this.me();
            return res.data;
        } catch {
            window.location.href = 'login.html';
            return null;
        }
    },

    /** Oculta elementos marcados con data-role-only="Administrador,Tecnico" si el rol actual no aplica. */
    aplicarVisibilidadPorRol(usuario) {
        document.querySelectorAll('[data-role-only]').forEach((el) => {
            const rolesPermitidos = el.dataset.roleOnly.split(',').map((r) => r.trim());
            if (!rolesPermitidos.includes(usuario.rol)) {
                el.style.display = 'none';
            }
        });
    },
};
