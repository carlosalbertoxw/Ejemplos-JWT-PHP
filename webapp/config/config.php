<?php

/**
 * Configuración de la aplicación web.
 */
return [
    // URL base de la API. Dentro de docker-compose los contenedores se
    // resuelven por nombre de servicio ("api"); en local apunta al puerto
    // publicado de la API.
    'api_url' => getenv('API_URL') ?: 'http://localhost:8000',
];
