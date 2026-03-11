<?php

function registerAuthRoutes(Router $router, PDO $db): void
{
    // POST /auth/register
    $router->post('/auth/register', function (array $params) use ($db) {
        $body = $params['_body'];

        $missing = Validator::required($body, ['email', 'password', 'display_name']);
        if (!empty($missing)) {
            Response::error('Missing required fields: ' . implode(', ', $missing));
        }

        if (!Validator::email($body['email'])) {
            Response::error('Invalid email format');
        }

        if (!Validator::minLength($body['password'], 6)) {
            Response::error('Password must be at least 6 characters');
        }

        // Check duplicate email
        $existing = User::findByEmail($db, $body['email']);
        if ($existing) {
            Response::error('Email already registered', 409);
        }

        $userId = User::create($db, $body['email'], $body['password'], $body['display_name']);
        $user = User::findById($db, $userId);

        $token = generateToken($user);

        Response::json([
            'token' => $token,
            'user'  => $user,
        ], 201);
    });

    // POST /auth/login
    $router->post('/auth/login', function (array $params) use ($db) {
        $body = $params['_body'];

        $missing = Validator::required($body, ['email', 'password']);
        if (!empty($missing)) {
            Response::error('Missing required fields: ' . implode(', ', $missing));
        }

        $user = User::findByEmail($db, $body['email']);
        if (!$user) {
            Response::error('Invalid email or password', 401);
        }

        if (!password_verify($body['password'], $user['password_hash'])) {
            Response::error('Invalid email or password', 401);
        }

        // Remove sensitive fields from response
        unset($user['password_hash']);

        $token = generateToken($user);

        Response::json([
            'token' => $token,
            'user'  => $user,
        ]);
    });

    // GET /auth/me
    $router->get('/auth/me', function (array $params) use ($db) {
        $authUser = authenticate();
        $user = User::findById($db, $authUser['id']);

        if (!$user) {
            Response::notFound('User not found');
        }

        Response::json(['user' => $user]);
    });
}
