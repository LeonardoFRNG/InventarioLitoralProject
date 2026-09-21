/**
 * Validaciones de FRONTEND: dan feedback inmediato al usuario.
 * Estas NUNCA reemplazan la validación de backend — son solo UX.
 */
const Validators = {
    requerido: (valor) => (valor === null || String(valor).trim() === '') ? 'Este campo es obligatorio.' : null,

    email: (valor) => {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(valor) ? null : 'Ingresa un correo electrónico válido.';
    },

    codigoInventario: (valor) => {
        const regex = /^[A-Za-z0-9\-]{3,30}$/;
        return regex.test(valor) ? null : 'Solo letras, números y guiones (3 a 30 caracteres).';
    },

    minLength: (valor, min) => (String(valor).length < min) ? `Debe tener al menos ${min} caracteres.` : null,

    fechaNoFutura: (valor) => {
        if (!valor) return null;
        return new Date(valor) > new Date() ? 'La fecha no puede ser futura.' : null;
    },

    rangoFechas: (inicio, fin) => {
        if (!fin) return null;
        return new Date(fin) < new Date(inicio) ? 'La fecha de fin no puede ser anterior a la de inicio.' : null;
    },

    /** Aplica un mapa {campoId: [reglas...]} y muestra errores bajo cada input. Retorna true si todo es válido. */
    validarFormulario(mapaReglas) {
        let esValido = true;
        for (const [campoId, reglas] of Object.entries(mapaReglas)) {
            const input = document.getElementById(campoId);
            const errorEl = document.getElementById(`${campoId}-error`);
            if (!input) continue;

            let mensaje = null;
            for (const regla of reglas) {
                mensaje = regla(input.value);
                if (mensaje) break;
            }

            if (errorEl) errorEl.textContent = mensaje || '';
            input.style.outline = mensaje ? '2px solid var(--color-danger)' : '';
            if (mensaje) esValido = false;
        }
        return esValido;
    },
};
