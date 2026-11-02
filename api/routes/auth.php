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

        $token = generateToken($user);

        // Remove internal fields from response
        unset($user['password_hash'], $user['token_version']);

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

    // POST /auth/change-password
    $router->post('/auth/change-password', function (array $params) use ($db) {
        $authUser = authenticate();
        $body = $params['_body'];

        $missing = Validator::required($body, ['current_password', 'new_password', 'confirm_password']);
        if (!empty($missing)) {
            Response::error('Missing required fields: ' . implode(', ', $missing));
        }

        if ($body['new_password'] !== $body['confirm_password']) {
            Response::error('New passwords do not match');
        }

        if (!Validator::minLength($body['new_password'], 6)) {
            Response::error('New password must be at least 6 characters');
        }

        $user = User::findByIdWithHash($db, $authUser['id']);
        if (!$user) {
            Response::notFound('User not found');
        }

        if (!password_verify($body['current_password'], $user['password_hash'])) {
            Response::error('Current password is incorrect', 403);
        }

        User::updatePassword($db, $authUser['id'], $body['new_password']);

        Response::json(['message' => 'Password updated successfully']);
    });

    // POST /auth/forgot-password
    $router->post('/auth/forgot-password', function (array $params) use ($db) {
        $email = $params['_body']['email'] ?? '';

        if (!is_string($email) || !Validator::email(trim($email))) {
            Response::error('Enter a valid email address');
        }

        $reset = PasswordReset::start($db, $email, $_SERVER['REMOTE_ADDR'] ?? '');
        if ($reset === null) {
            Response::error('Too many reset requests. Please wait a few minutes and try again.', 429);
        }

        // Reply before sending: an email takes seconds, which would otherwise reveal whether the account exists.
        Response::jsonThenContinue([
            'message' => 'If an account exists for that email, a reset code is on its way.',
        ]);

        if ($reset['recipient'] !== null) {
            try {
                sendPasswordResetEmail($reset['recipient'], $reset['code'], $reset['token']);
            } catch (Throwable $e) {
                PasswordReset::discard($db, $reset['id']);
                error_log('Password reset email failed: ' . $e->getMessage());
            }
        }
    });

    // POST /auth/reset-password
    // Takes new_password and confirm_password, plus either token (from the emailed link) or email and code.
    $router->post('/auth/reset-password', function (array $params) use ($db) {
        $body = $params['_body'];
        $token = $body['token'] ?? '';
        $email = $body['email'] ?? '';
        $code = $body['code'] ?? '';
        $newPassword = $body['new_password'] ?? '';
        $confirmPassword = $body['confirm_password'] ?? '';

        foreach ([$token, $email, $code, $newPassword, $confirmPassword] as $value) {
            if (!is_string($value)) {
                Response::error('Invalid request');
            }
        }

        if ($token === '' && (trim($email) === '' || trim($code) === '')) {
            Response::error('Enter your email and the code from the email');
        }

        // Checked first so a mistyped password doesn't use up a code attempt.
        if ($newPassword !== $confirmPassword) {
            Response::error('New passwords do not match');
        }

        if (!Validator::minLength($newPassword, 6)) {
            Response::error('New password must be at least 6 characters');
        }

        $done = $token !== ''
            ? PasswordReset::redeemToken($db, $token, $newPassword)
            : PasswordReset::redeemCode($db, $email, $code, $newPassword);

        if (!$done) {
            Response::error('That code or link is wrong or has expired. Please request a new one.');
        }

        Response::json(['message' => 'Your password has been reset. You can now sign in.']);
    });
}

function sendPasswordResetEmail(string $to, string $code, string $token): void
{
    $link = getAppConfig()['app_url'] . '/reset-password?token=' . rawurlencode($token);
    $safeLink = htmlspecialchars($link, ENT_QUOTES);

    $text = "We received a request to reset your Wish List password.\n\n"
        . "Your reset code is {$code}.\n\n"
        . "Or open this link to choose a new password:\n{$link}\n\n"
        . "The code and link expire in 15 minutes. If you didn't ask for this, you can ignore this email and your password will stay the same.";

    $html = '<p>We received a request to reset your Wish List password.</p>'
        . '<p>Your reset code is <strong style="font-size:1.4em;letter-spacing:.15em">' . $code . '</strong>.</p>'
        . '<p><a href="' . $safeLink . '">Choose a new password</a></p>'
        . '<p>The code and link expire in 15 minutes. If you didn\'t ask for this, you can ignore this email and your password will stay the same.</p>';

    Mailer::send($to, 'Reset your Wish List password', $text, $html);
}
