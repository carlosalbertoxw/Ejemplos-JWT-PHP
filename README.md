# Ejemplo de JWT en PHP puro — API REST + Aplicación web

Ejemplo educativo de autenticación con **JSON Web Tokens (JWT)** implementado en
**PHP puro, sin frameworks ni librerías externas**: la creación y validación del
token (HS256) está escrita a mano para que el mecanismo quede a la vista.

El proyecto se divide en dos partes independientes:

| Carpeta | Qué es | Puerto (Docker) |
|---|---|---|
| [`api/`](api/) | API REST que emite y valida los tokens | http://localhost:8000 |
| [`webapp/`](webapp/) | Aplicación web que consume la API | http://localhost:8080 |

## Características

- **Access token** de corta vida (15 min) firmado con HMAC-SHA256, con claims
  estándar (`iss`, `aud`, `iat`, `nbf`, `exp`, `jti`) y de negocio (`sub`,
  `username`, `role`).
- **Refresh token** de larga vida (7 días) **con rotación y detección de
  reuso**: cada uso lo revoca y emite uno nuevo; si alguien reutiliza un token
  ya rotado (señal de robo), se revocan **todas** las sesiones del usuario.
  Se almacena hasheado (SHA-256) en la base de datos.
- **Logout real**: revoca el refresh token en el servidor.
- **Autorización por roles**: endpoint de creación de productos solo para `Admin`.
- **Datos según nivel de acceso**: el catálogo público muestra los productos
  sin precio; el precio solo se expone en el endpoint autenticado.
- **Contraseñas con bcrypt** (`password_hash` / `password_verify`), con
  verificación contra un hash señuelo cuando el usuario no existe (el tiempo
  de respuesta no revela qué usuarios existen).
- **Rate limiting anti fuerza bruta** en login y refresh: tras 5 intentos
  fallidos en 15 minutos responde `429` con encabezado `Retry-After`
  (configurable por variables de entorno).
- **Renovación transparente en la web**: ante un `401`, el cliente HTTP renueva
  el token y reintenta la petición sin que el usuario lo note.
- **Webapp endurecida**: cookie de sesión `HttpOnly` + `SameSite=Lax`,
  regeneración del ID de sesión al iniciar sesión, tokens anti-CSRF en todos
  los formularios (el logout es un POST) y cabeceras de seguridad
  (`CSP`, `X-Frame-Options`, `X-Content-Type-Options`…).
- **Pruebas unitarias sin dependencias** (31 tests) del JWT, el flujo de
  autenticación, el rate limiter y el router, con CI en GitHub Actions.
- Persistencia en **SQLite** (se crea y siembra sola en el primer arranque;
  modo WAL para tolerar peticiones concurrentes).

## Cómo ejecutarlo (Docker)

Requiere Docker (Desktop en Windows/Mac). En la raíz del proyecto:

```bash
docker compose up --build
```

Esto construye las dos imágenes (`api/Dockerfile` y `webapp/Dockerfile`) y
levanta ambos contenedores. La base SQLite se crea y siembra sola en el
primer arranque:

- Aplicación web: **http://localhost:8080**
- API (para probar directo con curl/Postman): **http://localhost:8000**
- Documentación interactiva (Swagger UI): **http://localhost:8000/docs**

Comandos útiles del día a día:

```bash
docker compose up -d --build   # arrancar en segundo plano
docker compose up -d           # volver a arrancar sin reconstruir (si no cambió el código)
docker compose logs -f api     # seguir los logs de la API (avisos de rate limiting, reuso de tokens…)
docker compose down            # detener y eliminar los contenedores
docker compose down -v         # además borra el volumen de datos: la base se resiembra al arrancar
```

> La base de datos vive en el volumen `api-data`, así que sobrevive a
> `docker compose down`; usa `down -v` para empezar de cero. Si defines un
> archivo `.env` en la raíz (ver [`.env.example`](.env.example)), compose lo
> carga automáticamente.

Usuarios de prueba:

| Usuario | Contraseña | Rol |
|---|---|---|
| `admin` | `Admin123!` | Admin |
| `user` | `User123!` | User |

## Endpoints de la API

| Método | Ruta | Acceso | Descripción |
|---|---|---|---|
| POST | `/api/auth/login` | Público | Valida credenciales y devuelve el par de tokens (429 tras varios fallos) |
| POST | `/api/auth/refresh` | Público | Rota el refresh token y devuelve un nuevo par (429 tras varios fallos) |
| POST | `/api/auth/logout` | Bearer | Revoca el refresh token |
| GET | `/api/auth/me` | Bearer | Devuelve los claims del token |
| GET | `/api/products/public` | Público | Catálogo público (**oculta el precio**) |
| GET | `/api/products` | Bearer | Listado completo (**incluye el precio**) |
| POST | `/api/products` | Bearer + rol Admin | Crea un producto |

### Documentación interactiva (Swagger)

La API describe sus endpoints en [`api/public/openapi.json`](api/public/openapi.json)
(especificación **OpenAPI 3.0**) y sirve **Swagger UI** en
[http://localhost:8000/docs](http://localhost:8000/docs). Desde ahí se puede
probar todo el flujo:

1. Ejecuta `POST /api/auth/login` con un usuario de prueba (**Try it out**).
2. Copia el `access_token` de la respuesta.
3. Pulsa **Authorize** (candado) y pega el token, sin el prefijo `Bearer`.
4. Prueba los endpoints protegidos; con `user` verás el `403` del endpoint de rol Admin.

> Swagger UI se carga desde CDN (unpkg) con la versión fijada y
> **Subresource Integrity** (el navegador rechaza el archivo si el CDN lo
> altera), por lo que la página de documentación requiere conexión a
> internet; la API en sí no.

### Ejemplo con curl

```bash
# Login
curl -s -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"Admin123!"}'

# Respuesta:
# { "access_token":"...", "refresh_token":"...", "token_type":"Bearer", "expires_in":900 }

# Recurso protegido
curl -s http://localhost:8000/api/products \
  -H "Authorization: Bearer <access_token>"

# Renovar el par de tokens (rotación)
curl -s -X POST http://localhost:8000/api/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refresh_token":"<refresh_token>"}'
```

## Arquitectura

### API (`api/`)

```
api/
├── public/index.php      # Front controller: dependencias + rutas
├── config/config.php     # Configuración (lee variables de entorno)
├── tests/                # Pruebas unitarias (runner propio, sin dependencias)
│   ├── run.php                   # php tests/run.php
│   ├── TestCase.php              # Aserciones mínimas
│   ├── JwtServiceTest.php
│   ├── AuthServiceTest.php
│   ├── RateLimiterTest.php
│   └── RouterTest.php
└── src/
    ├── Core/             # Lógica de autenticación (sin HTTP)
    │   ├── JwtService.php        # Crea/valida JWT HS256 a mano
    │   ├── AuthService.php       # Login, rotación + detección de reuso, logout
    │   ├── RateLimiter.php       # Límite de intentos (ventana deslizante)
    │   └── PasswordHasher.php    # bcrypt
    ├── Data/             # Acceso a datos (SQLite vía PDO)
    │   ├── Database.php          # Conexión + esquema + datos semilla
    │   ├── UserRepository.php
    │   ├── RefreshTokenRepository.php
    │   ├── RateLimitRepository.php
    │   └── ProductRepository.php
    ├── Http/             # Capa HTTP
    │   ├── Router.php            # Rutas con control de acceso (Access + rol)
    │   ├── Access.php            # Enum: PublicRoute / Auth
    │   ├── Request.php
    │   └── Response.php          # Los controladores la DEVUELVEN; send() la emite
    └── Controllers/
        ├── AuthController.php
        └── ProductController.php
```

Cambiar SQLite por otra base de datos solo requiere tocar la capa `Data/`.

### Aplicación web (`webapp/`)

```
webapp/
├── public/               # Páginas (document root)
│   ├── index.php         # Catálogo público
│   ├── login.php         # Inicio de sesión
│   ├── products.php      # Listado protegido
│   ├── product-new.php   # Alta de producto (solo Admin)
│   ├── profile.php       # Claims del token (/api/auth/me)
│   └── logout.php
└── src/
    ├── Api/              # Cliente HTTP de la API (cURL)
    │   ├── ApiHttpClient.php     # Canal común: 401 → refresh → reintento
    │   ├── AuthApiClient.php
    │   └── ProductsApiClient.php
    └── Auth/TokenStore.php       # Tokens en la sesión PHP (nunca en el navegador)
```

La web es **PHP del lado del servidor**: los tokens se guardan en la sesión y
las llamadas a la API salen del servidor con cURL, de modo que el JWT nunca
llega al navegador.

## Flujo de autenticación

1. **Login** — la web envía las credenciales a `POST /api/auth/login`; la API
   verifica la contraseña (bcrypt) y devuelve un access token y un refresh token.
2. **Peticiones protegidas** — la web envía `Authorization: Bearer <access_token>`.
3. **Expiración** — cuando la API responde `401`, la web llama a
   `POST /api/auth/refresh`; la API revoca el refresh token usado (rotación) y
   emite un par nuevo; la web reintenta la petición original.
4. **Detección de robo** — si alguien presenta un refresh token que ya fue
   rotado (solo puede pasar si el token se usó dos veces: robo), la API revoca
   **todas** las sesiones de ese usuario y lo registra en el log.
5. **Logout** — la web llama a `POST /api/auth/logout` y la API revoca el
   refresh token: ya no se puede renovar la sesión.

## Pruebas

La API incluye pruebas unitarias escritas con un mini-runner propio (sin
dependencias, fiel al espíritu "PHP puro"): cubren la creación/validación de
JWT (firma manipulada, `alg: none`, expiración, emisor/audiencia…), el flujo
de login/rotación/reuso/logout, el rate limiter y el router.

```bash
# Con PHP local
php api/tests/run.php

# O con Docker, sin instalar PHP
docker run --rm -v "$PWD/api:/app" -w /app php:8.3-cli php tests/run.php
```

También corren en CI en cada push (GitHub Actions: `.github/workflows/tests.yml`).

## Configuración

Todos los valores se leen de variables de entorno (ver
[`.env.example`](.env.example)); docker compose carga automáticamente un
archivo `.env` en la raíz si existe. Destacan:

| Variable | Default | Qué controla |
|---|---|---|
| `JWT_SECRET` | clave de desarrollo (avisa en el log) | Firma de los JWT |
| `ACCESS_TOKEN_TTL` | `900` (15 min) | Vida del access token |
| `REFRESH_TOKEN_TTL` | `604800` (7 días) | Vida del refresh token |
| `RATE_LIMIT_MAX_ATTEMPTS` | `5` | Intentos fallidos permitidos |
| `RATE_LIMIT_WINDOW_SECONDS` | `900` | Ventana del rate limiting |

## Ejecución sin Docker (opcional)

Con PHP 8.2+ instalado, en dos terminales:

```bash
# Terminal 1 — API en el puerto 8000
cd api && php -S localhost:8000 -t public public/index.php

# Terminal 2 — Web en el puerto 8080
cd webapp && php -S localhost:8080 -t public
```

> Nota: este proyecto es educativo. Para producción, además de definir una
> clave secreta robusta en `JWT_SECRET` (el default de desarrollo solo avisa
> en el log), se debe servir todo por HTTPS (y activar el flag `secure` que
> la cookie de sesión ya aplica automáticamente bajo HTTPS).

## Licencia

[MIT](LICENSE).
