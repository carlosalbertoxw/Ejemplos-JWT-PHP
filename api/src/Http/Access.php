<?php

namespace Api\Http;

/**
 * Nivel de acceso de una ruta. Usar un enum (en vez de strings sueltos)
 * hace imposible declarar un nivel inexistente por un error de tipeo.
 *
 * El rol requerido se declara aparte (Router::add, parámetro $role).
 */
enum Access
{
    /** No requiere token. */
    case PublicRoute;

    /** Requiere un access token válido (cualquier rol). */
    case Auth;
}
