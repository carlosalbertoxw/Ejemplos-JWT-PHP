<?php

namespace Api\Core;

/**
 * Error al validar un JWT (firma inválida, expirado, aún no válido, etc.).
 */
class JwtException extends \RuntimeException
{
}
