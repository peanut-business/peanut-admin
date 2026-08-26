<?php
declare(strict_types=1);

use app\api\middleware\CheckTokenMiddleware;
use app\api\service\UserTokenService;
use Firebase\JWT\JWT;
use think\Config as ThinkConfig;
use think\Container;
use think\facade\Config;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

function jwtExpect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function jwtExpectThrows(callable $operation, string $message): void
{
    try {
        $operation();
    } catch (Throwable) {
        return;
    }
    throw new RuntimeException($message);
}

/** @param array<string,mixed> $overrides */
function jwtToken(string $secret, array $overrides = [], string $algorithm = 'HS256'): string
{
    $now = time();
    $payload = array_merge([
        'iss' => 'peanut-admin',
        'aud' => 'peanut-admin-member-api',
        'sub' => 'member:17',
        'iat' => $now,
        'nbf' => $now,
        'exp' => $now + 7200,
        'member_id' => 17,
    ], $overrides);
    foreach ($payload as $claim => $value) {
        if ($value === null) {
            unset($payload[$claim]);
        }
    }
    return JWT::encode($payload, $secret, $algorithm);
}

$container = new Container();
Container::setInstance($container);
$container->instance('config', new ThinkConfig());

Config::set(['secret' => '', 'expire' => 7200], 'jwt');
jwtExpectThrows(
    static fn(): string => UserTokenService::createToken(17),
    'member JWT signing accepted a missing secret',
);
Config::set(['secret' => str_repeat('s', 31), 'expire' => 7200], 'jwt');
jwtExpectThrows(
    static fn(): string => UserTokenService::createToken(17),
    'member JWT signing accepted a secret shorter than 32 bytes',
);

$secret = str_repeat('s', 64);
Config::set(['secret' => $secret, 'expire' => 0], 'jwt');
jwtExpectThrows(
    static fn(): string => UserTokenService::createToken(17),
    'member JWT signing accepted an invalid expiry',
);
Config::set(['secret' => $secret, 'expire' => 7200], 'jwt');
$issued = UserTokenService::createToken(17);
jwtExpect(UserTokenService::parseToken($issued) === 17, 'member JWT round trip failed');
jwtExpectThrows(
    static fn(): string => UserTokenService::createToken(0),
    'member JWT signing accepted an invalid member id',
);

$invalidClaims = [
    'missing iss' => ['iss' => null],
    'wrong iss type' => ['iss' => 1],
    'wrong aud' => ['aud' => 'another-api'],
    'wrong aud type' => ['aud' => ['peanut-admin-member-api']],
    'wrong sub' => ['sub' => 'member:18'],
    'wrong sub type' => ['sub' => 17],
    'missing iat' => ['iat' => null],
    'wrong iat type' => ['iat' => (string)time()],
    'missing nbf' => ['nbf' => null],
    'wrong nbf type' => ['nbf' => time() + 0.5],
    'missing exp' => ['exp' => null],
    'wrong exp type' => ['exp' => (string)(time() + 7200)],
    'member id string' => ['member_id' => '17'],
    'member id mismatch' => ['member_id' => 18],
    'iat after nbf' => ['iat' => time() + 1, 'nbf' => time()],
    'nbf at exp' => ['nbf' => time(), 'exp' => time()],
    'expired' => ['iat' => time() - 7201, 'nbf' => time() - 7201, 'exp' => time() - 1],
    'future' => ['iat' => time() + 60, 'nbf' => time() + 60, 'exp' => time() + 7260],
];
foreach ($invalidClaims as $label => $claims) {
    jwtExpect(
        UserTokenService::parseToken(jwtToken($secret, $claims)) === false,
        'member JWT accepted invalid claims: ' . $label,
    );
}
jwtExpect(
    UserTokenService::parseToken(jwtToken($secret, [], 'HS512')) === false,
    'member JWT accepted a non-HS256 algorithm',
);

$bearerParser = new ReflectionMethod(CheckTokenMiddleware::class, 'bearerToken');
$compactToken = 'abc.DEF_123.xyz-789';
foreach (['Bearer ', 'bearer ', 'BEARER  '] as $prefix) {
    jwtExpect(
        $bearerParser->invoke(null, $prefix . $compactToken) === $compactToken,
        'member middleware rejected a valid case-insensitive Bearer scheme',
    );
}
foreach ([
    '',
    $compactToken,
    'Basic ' . $compactToken,
    ' Bearer ' . $compactToken,
    'Bearer\t' . $compactToken,
    'Bearer ' . $compactToken . ' ',
    'Bearer abc',
    'Bearer abc.def.ghi.extra',
    'Bearer abc.def.ghi,another',
] as $authorization) {
    jwtExpect(
        $bearerParser->invoke(null, $authorization) === '',
        'member middleware accepted a malformed Authorization value: ' . $authorization,
    );
}

$rootEnvExample = (string)file_get_contents(dirname(__DIR__, 3) . '/.env.example');
$envExample = (string)file_get_contents(dirname(__DIR__, 2) . '/.env.example');
jwtExpect(
    !str_contains($rootEnvExample, 'JWT_SECRET=')
        && preg_match('/^JWT_SECRET=$/m', $envExample) === 1,
    'backend environment samples expose, duplicate, or supply a JWT secret',
);
$jwtConfig = (string)file_get_contents(dirname(__DIR__, 2) . '/config/jwt.php');
jwtExpect(
    preg_match("/'secret'\\s*=>\\s*env\\('JWT_SECRET'\\),/", $jwtConfig) === 1,
    'JWT configuration supplies a default secret',
);

echo "Member JWT contract passed\n";
