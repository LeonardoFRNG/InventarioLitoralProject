/**
 * Utilidades de interfaz compartidas por todas las páginas.
 */
const Ui = {
    toast(message, type = 'success') {
        const el = document.createElement('div');
        el.className = `toast ${type === 'success' ? 'toast-success' : 'toast-error'}`;
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 3800);
    },

    badgeClass(color) {
        const map = { green: 'badge-green', yellow: 'badge-yellow', orange: 'badge-orange', red: 'badge-red' };
        return `badge ${map[color] || 'badge-gray'}`;
    },

    formatFecha(fechaIso) {
        if (!fechaIso) return '—';
        const [fecha] = fechaIso.split(' ');
        const [y, m, d] = fecha.split('-');
        return `${d}/${m}/${y}`;
    },

    /** Muestra un modal de confirmación y resuelve true/false según la elección del usuario. */
    confirmar(titulo, descripcion) {
        return new Promise((resolve) => {
            const overlay = document.createElement('div');
            overlay.className = 'modal-overlay';
            overlay.innerHTML = `
                <div class="modal-box">
                    <h3 class="text-lg font-semibold mb-2">${titulo}</h3>
                    <p class="text-sm" style="color:var(--color-text-muted)">${descripcion}</p>
                    <div class="flex justify-end gap-2 mt-6">
                        <button class="btn-ghost" data-action="cancelar">Cancelar</button>
                        <button class="btn-danger-ghost" data-action="confirmar">Sí, continuar</button>
                    </div>
                </div>`;
            document.body.appendChild(overlay);

            overlay.querySelector('[data-action="cancelar"]').onclick = () => { overlay.remove(); resolve(false); };
            overlay.querySelector('[data-action="confirmar"]').onclick = () => { overlay.remove(); resolve(true); };
            overlay.onclick = (e) => { if (e.target === overlay) { overlay.remove(); resolve(false); } };
        });
    },

    setLoading(button, loading, textoNormal) {
        if (loading) {
            button.disabled = true;
            button.dataset.originalText = button.innerHTML;
            button.innerHTML = `<span class="spinner"></span>`;
        } else {
            button.disabled = false;
            button.innerHTML = button.dataset.originalText || textoNormal;
        }
    },

    escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    },
};
