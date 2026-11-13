<?php

const INVALID_INVITE_MESSAGE = 'This invite link has expired or is no longer valid. Ask a family member to send a new one.';

function registerInviteRoutes(Router $router, PDO $db): void
{
    // GET /invites/{token} - public details for an invite link
    $router->get('/invites/{token}', function (array $params) use ($db) {
        $invite = FamilyInvite::findValid($db, (string) $params['token']);
        if (!$invite) {
            Response::notFound(INVALID_INVITE_MESSAGE);
        }

        Response::json([
            'family_id'      => (int) $invite['family_id'],
            'family_name'    => $invite['family_name'],
            'inviter_name'   => $invite['inviter_name'],
            'email'          => $invite['email'],
            'account_exists' => User::findByEmail($db, (string) $invite['email']) !== false,
            'expires_at'     => $invite['expires_at'],
        ]);
    });

    // POST /invites/{token}/accept - join the invite's family as the signed-in user
    $router->post('/invites/{token}/accept', function (array $params) use ($db) {
        $user = authenticate();

        $invite = FamilyInvite::findValid($db, (string) $params['token']);
        if (!$invite) {
            Response::notFound(INVALID_INVITE_MESSAGE);
        }

        $alreadyMember = FamilyInvite::accept($db, $invite, (int) $user['id']);

        Response::json([
            'family'         => Family::getById($db, (int) $invite['family_id']),
            'already_member' => $alreadyMember,
        ]);
    });
}

/**
 * Creates an invite link and emails it. On a send failure the invite is removed and an error response is sent.
 * Returns the new invite's public summary.
 */
function createAndSendInvite(PDO $db, array $family, string $email, array $inviter): array
{
    $created = FamilyInvite::create($db, (int) $family['id'], $email, (int) $inviter['id']);
    if ($created === null) {
        Response::error('Too many invites sent. Please wait a while and try again.', 429);
    }

    try {
        sendFamilyInviteEmail(strtolower(trim($email)), $inviter['display_name'], $family['name'], $created['token']);
    } catch (Throwable $e) {
        FamilyInvite::discard($db, $created['id']);
        error_log('Family invite email failed: ' . $e->getMessage());
        Response::error('The invite email could not be sent. Please try again.', 502);
    }

    $row = FamilyInvite::findInFamily($db, (int) $family['id'], $created['id']);
    return [
        'id'         => $created['id'],
        'email'      => $row['email'],
        'expires_at' => $row['expires_at'],
    ];
}

function sendFamilyInviteEmail(string $to, string $inviterName, string $familyName, string $token): void
{
    $link = getAppConfig()['app_url'] . '/invite?token=' . rawurlencode($token);
    $safeLink = htmlspecialchars($link, ENT_QUOTES);
    $safeInviter = htmlspecialchars($inviterName, ENT_QUOTES);
    $safeFamily = htmlspecialchars($familyName, ENT_QUOTES);

    $text = "{$inviterName} invited you to join {$familyName} on Wish List.\n\n"
        . "Open this link to join:\n{$link}\n\n"
        . "This link expires in 14 days.";

    $html = '<p>' . $safeInviter . ' invited you to join <strong>' . $safeFamily . '</strong> on Wish List.</p>'
        . '<p><a href="' . $safeLink . '">Join ' . $safeFamily . '</a></p>'
        . '<p>This link expires in 14 days.</p>';

    Mailer::send($to, "{$inviterName} invited you to join {$familyName} on Wish List", $text, $html);
}
