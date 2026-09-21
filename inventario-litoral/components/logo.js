/**
 * Logo de Inventario Litoral.
 * SVG vectorizado a partir del archivo original del usuario (sin fondo:
 * usa currentColor, por lo que hereda el color del texto donde se ponga).
 *
 * @param {number} size - tamaño en píxeles
 * @param {string} extraStyle - estilos CSS adicionales opcionales
 */
function logoSvg(size = 28, extraStyle = '') {
    return `<svg viewBox="0 0 83 126" width="${size}" height="${size}" fill="currentColor"
                 xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Inventario Litoral"
                 style="${extraStyle}">
        <g transform="translate(0,126) scale(0.1,-0.1)">
            <path d="M514 891 c-27 -10 -64 -26 -82 -35 -40 -20 -102 -21 -150 0 -20 9 -50 20 -66 25 l-28 8 6 -42 c11 -69 26 -106 47 -112 38 -12 207 -7 236 8 15 7 54 22 86 31 57 18 95 15 161 -10 14 -5 16 3 16 59 0 64 -1 65 -31 76 -47 16 -137 12 -195 -8z M516 689 c-33 -11 -65 -24 -72 -29 -19 -17 -109 -24 -145 -11 -18 6 -34 9 -37 6 -3 -2 7 -20 22 -39 14 -19 26 -37 26 -41 0 -3 12 -17 28 -30 37 -34 76 -31 176 10 95 39 115 41 176 20 23 -8 44 -15 46 -15 2 0 4 29 4 65 l0 64 -37 10 c-54 15 -119 12 -187 -10z M548 485 c-32 -6 -65 -18 -73 -25 -15 -12 -12 -16 20 -31 44 -22 184 -59 220 -59 24 0 25 3 25 54 l0 54 -50 11 c-60 13 -68 13 -142 -4z"/>
        </g>
    </svg>`;
}
