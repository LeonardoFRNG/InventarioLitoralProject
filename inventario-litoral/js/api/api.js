/**
 * Cliente HTTP centralizado para comunicarse con el backend PHP.
 * Toda petición pasa por aquí: maneja cookies de sesión, parseo de
 * JSON y errores de forma consistente en toda la aplicación.
 */
const API_BASE = '/backend/controllers';

async function apiRequest(path, { method = 'GET', body = null, isFormUrl = false } = {}) {
    const options = {
        method,
        credentials: 'include', // envía la cookie de sesión (PHPSESSID)
        headers: {},
    };

    if (body !== null) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(body);
    }

    let response;
    try {
        response = await fetch(`${API_BASE}/${path}`, options);
    } catch (networkError) {
        throw new ApiError('No fue posible conectar con el servidor. Verifica tu conexión.', 0);
    }

    let data;
    try {
        data = await response.json();
    } catch {
        throw new ApiError('El servidor devolvió una respuesta inesperada.', response.status);
    }

    if (!response.ok || data.success === false) {
        throw new ApiError(data.message || 'Ocurrió un error inesperado.', response.status, data.errors || []);
    }

    return data;
}

class ApiError extends Error {
    constructor(message, status, errors = []) {
        super(message);
        this.status = status;
        this.errors = errors;
    }
}

const Api = {
    get:    (path)       => apiRequest(path, { method: 'GET' }),
    post:   (path, body) => apiRequest(path, { method: 'POST', body }),
    put:    (path, body) => apiRequest(path, { method: 'PUT', body }),
    delete: (path)        => apiRequest(path, { method: 'DELETE' }),
};
