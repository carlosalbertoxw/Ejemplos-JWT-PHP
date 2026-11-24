<?php

declare(strict_types=1);

use Api\Core\AuthService;
use Api\Core\JwtService;
use Api\Core\PasswordHasher;
use Api\Data\Database;
use Api\Data\RefreshTokenRepository;
use Api\Data\UserRepository;

/**
 * Pruebas del flujo de autenticación completo contra una base SQLite en
 * memoria (con los usuarios semilla admin/user): login, rotación de
 * refresh tokens, detección de reuso y logout.
 */
final class AuthServiceTest extends TestCase
{
    private AuthService $auth;
    private JwtService $jwt;
    private RefreshTokenRepository $refreshTokens;
    private \PDO $pdo;

    protected function setUp(): void
    {
        // Base en memoria: Database crea el esquema y siembra admin/user.
        $pdo = $this->pdo = (new Database(':memory:'))->pdo();

        $this->jwt = new JwtService('secreto-de-pruebas-de-al-menos-32-bytes', 'iss', 'aud', 900);
        $this->refreshTokens = new RefreshTokenRepository($pdo);

        $this->auth = new AuthService(
            new UserRepository($pdo),
            $this->refreshTokens,
            $this->jwt,
            new PasswordHasher(),
            900,
            3600,
        );
    }

    public function testLoginConCredencialesValidasEmiteElParDeTokens(): void
    {
        $tokens = $this->auth->login('admin', 'Admin123!');

        $this->assertNotNull($tokens);
        $this->assertSame('Bearer', $tokens['token_type']);
        $this->assertSame(900, $tokens['expires_in']);

        // El access token es un JWT válido con los claims del usuario.
        $claims = $this->jwt->verify($tokens['access_token']);
        $this->assertSame('admin', $claims['username']);
        $this->assertSame('Admin', $claims['role']);

        // El refresh token emitido quedó guardado y vigente.
        $stored = $this->refreshTokens->find($tokens['refresh_token']);
        $this->assertNotNull($stored);
        $this->assertSame(0, (int) $stored['revoked']);
    }

    public function testLoginConPasswordIncorrectaDevuelveNull(): void
    {
        $this->assertNull($this->auth->login('admin', 'incorrecta'));
    }

    public function testLoginConUsuarioInexistenteDevuelveNull(): void
    {
        $this->assertNull($this->auth->login('no-existe', 'Admin123!'));
    }

    public function testRefreshRotaElToken(): void
    {
        $first = $this->auth->login('user', 'User123!');

        $second = $this->auth->refresh($first['refresh_token']);

        $this->assertNotNull($second, 'Un refresh token válido debe renovarse');
        $this->assertTrue(
            $first['refresh_token'] !== $second['refresh_token'],
            'La rotación debe emitir un refresh token distinto',
        );

        // El token usado quedó revocado: un segundo canje falla.
        $this->assertNull($this->auth->refresh($first['refresh_token']));
    }

    public function testElReusoDeUnTokenRotadoRevocaTodasLasSesionesDelUsuario(): void
    {
        $first = $this->auth->login('user', 'User123!');
        $second = $this->auth->refresh($first['refresh_token']);

        // Reuso del token ya rotado (escenario de robo): falla…
        $this->assertNull($this->auth->refresh($first['refresh_token']));

        // …y además revoca la familia completa: el token "bueno" tampoco sirve ya.
        $this->assertNull(
            $this->auth->refresh($second['refresh_token']),
            'Tras detectar reuso deben revocarse todos los tokens del usuario',
        );
    }

    public function testLogoutRevocaElRefreshToken(): void
    {
        $tokens = $this->auth->login('admin', 'Admin123!');

        $this->auth->logout($tokens['refresh_token']);

        $this->assertNull($this->auth->refresh($tokens['refresh_token']));
    }

    public function testLogoutSoloCierraLaSesionDelTokenEnviado(): void
    {
        // Dos sesiones del mismo usuario (p. ej. dos dispositivos).
        $phone = $this->auth->login('user', 'User123!');
        $laptop = $this->auth->login('user', 'User123!');

        $this->auth->logout($phone['refresh_token']);

        $this->assertSame(1, (int) $this->refreshTokens->find($phone['refresh_token'])['revoked']);
        $this->assertNotNull(
            $this->auth->refresh($laptop['refresh_token']),
            'La otra sesión debe seguir activa tras el logout',
        );

        // Reusar el token ya cerrado se trata como robo: revoca todo.
        $this->assertNull($this->auth->refresh($phone['refresh_token']));
    }

    public function testElCanjeEsAtomicoSoloUnoDeDosUsosGana(): void
    {
        $tokens = $this->auth->login('user', 'User123!');

        // Simula dos peticiones simultáneas: la base serializa los UPDATE y
        // solo el primero cambia la fila.
        $this->assertTrue($this->refreshTokens->consume($tokens['refresh_token']));
        $this->assertFalse($this->refreshTokens->consume($tokens['refresh_token']));
    }

    public function testUnRefreshTokenDesconocidoNoRenueva(): void
    {
        $this->assertNull($this->auth->refresh(bin2hex(random_bytes(32))));
    }

    public function testElSenueloTieneElMismoCosteQueLosHashesReales(): void
    {
        // Si el señuelo costara menos, un usuario inexistente respondería más
        // rápido y se podría saber qué usuarios existen.
        $hasher = new PasswordHasher();
        $this->assertSame(
            password_get_info($hasher->hash('cualquiera'))['options'],
            password_get_info($hasher->decoyHash())['options'],
        );
        $this->assertFalse($hasher->needsRehash($hasher->decoyHash()));
    }

    public function testElLoginRehaceLosHashesCreadosConOtroCoste(): void
    {
        // Simula un usuario sembrado con el coste por defecto de PHP < 8.4.
        $oldHash = password_hash('User123!', PASSWORD_BCRYPT, ['cost' => 10]);
        $this->pdo->prepare('UPDATE users SET password_hash = ? WHERE username = ?')
            ->execute([$oldHash, 'user']);

        $this->assertNotNull($this->auth->login('user', 'User123!'));

        $stored = $this->pdo->query("SELECT password_hash FROM users WHERE username = 'user'")->fetchColumn();
        $this->assertSame(PasswordHasher::COST, password_get_info($stored)['options']['cost']);
        $this->assertNotNull($this->auth->login('user', 'User123!'), 'El hash nuevo debe seguir validando');
    }
}
