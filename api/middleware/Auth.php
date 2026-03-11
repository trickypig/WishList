<?php

/**
 * JWT authentication middleware.
 * Pure PHP implementation using HMAC-SHA256.
 */

function base64urlEncode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64urlDecode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Generate a JWT token for a user.
 */
function generateToken(array $user): string
{
    $config = getAppConfig();
    $secret = $config['jwt_secret'];

    $header = base64urlEncode(json_encode([
        'alg' => 'HS256',
        'typ' => 'JWT',
    ]));

    $payload = base64urlEncode(json_encode([
        'sub'          => $user['id'],
        'email'        => $user['email'],
        'display_name' => $user['display_name'],
        'is_admin'     => (int) ($user['is_admin'] ?? 0),
        'iat'          => time(),
        'exp'          => time() + (30 * 24 * 60 * 60), // 30 days
    ]));

    $signature = base64urlEncode(
        hash_hmac('sha256', "{$header}.{$payload}", $secret, true)
    );

    return "{$header}.{$payload}.{$signature}";
}

/**
 * Authenticate the current request via Bearer token.
 * Returns the decoded user payload or sends 401 and exits.
 */
function authenticate(): array
{
    $config = getAppConfig();
    $secret = $config['jwt_secret'];

    // Extract token from Authorization header
    $authHeader = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? '';

    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        Response::unauthorized('Missing or invalid Authorization header');
    }

    $token = $matches[1];
    $parts = explode('.', $token);

    if (count($parts) !== 3) {
        Response::unauthorized('Invalid token format');
    }

    [$headerB64, $payloadB64, $signatureB64] = $parts;

    // Verify signature
    $expectedSignature = base64urlEncode(
        hash_hmac('sha256', "{$headerB64}.{$payloadB64}", $secret, true)
    );

    if (!hash_equals($expectedSignature, $signatureB64)) {
        Response::unauthorized('Invalid token signature');
    }

    // Decode payload
    $payload = json_decode(base64urlDecode($payloadB64), true);

    if (!$payload) {
        Response::unauthorized('Invalid token payload');
    }

    // Check expiry
    if (isset($payload['exp']) && $payload['exp'] < time()) {
        Response::unauthorized('Token expired');
    }

    return [
        'id'           => $payload['sub'],
        'email'        => $payload['email'],
        'display_name' => $payload['display_name'],
        'is_admin'     => (int) ($payload['is_admin'] ?? 0),
    ];
}

/**
 * Require the authenticated user to be an admin.
 * Must be called after authenticate().
 */
function requireAdmin(): array
{
    $user = authenticate();
    if (!$user['is_admin']) {
        Response::error('Admin access required', 403);
    }
    return $user;
}
