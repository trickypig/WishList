<?php

function registerFamilyRoutes(Router $router, PDO $db): void
{
    // GET /families - user's families
    $router->get('/families', function (array $params) use ($db) {
        $user = authenticate();
        $families = Family::getByUserId($db, $user['id']);
        Response::json(['families' => $families]);
    });

    // POST /families - create family
    $router->post('/families', function (array $params) use ($db) {
        $user = authenticate();
        $body = $params['_body'];

        $missing = Validator::required($body, ['name']);
        if (!empty($missing)) {
            Response::error('Missing required fields: ' . implode(', ', $missing));
        }

        $familyId = Family::create($db, $body['name'], $user['id']);
        $family = Family::getById($db, $familyId);

        Response::json(['family' => $family], 201);
    });

    // POST /families/join - join via invite code
    // NOTE: Must be registered before /families/{id} to avoid {id} matching "join"
    $router->post('/families/join', function (array $params) use ($db) {
        $user = authenticate();
        $body = $params['_body'];

        $missing = Validator::required($body, ['invite_code']);
        if (!empty($missing)) {
            Response::error('Missing required fields: ' . implode(', ', $missing));
        }

        $family = Family::findByInviteCode($db, $body['invite_code']);
        if (!$family) {
            Response::notFound('Invalid invite code');
        }

        if (Family::isMember($db, $family['id'], $user['id'])) {
            Response::error('You are already a member of this family', 409);
        }

        Family::addMember($db, $family['id'], $user['id'], 'member');

        $family = Family::getById($db, $family['id']);
        Response::json(['family' => $family]);
    });

    // GET /families/{id} - family details with members
    $router->get('/families/{id}', function (array $params) use ($db) {
        $user = authenticate();
        $familyId = (int) $params['id'];

        if (!Family::isMember($db, $familyId, $user['id'])) {
            Response::error('You are not a member of this family', 403);
        }

        $family = Family::getById($db, $familyId);
        if (!$family) {
            Response::notFound('Family not found');
        }

        Response::json(['family' => $family]);
    });

    // POST /families/{id}/invite - generate/return invite code (admin only)
    $router->post('/families/{id}/invite', function (array $params) use ($db) {
        $user = authenticate();
        $familyId = (int) $params['id'];

        $role = Family::getMemberRole($db, $familyId, $user['id']);
        if ($role !== 'admin') {
            Response::error('Only admins can invite members', 403);
        }

        $family = Family::getById($db, $familyId);
        if (!$family) {
            Response::notFound('Family not found');
        }

        // Store the invite record (email is optional context, invite code is on the family)
        $body = $params['_body'];
        $email = $body['email'] ?? null;

        if ($email) {
            // Store invite record for tracking
            $stmt = $db->prepare(
                'INSERT INTO family_invites (family_id, email, invited_by, created_at)
                 VALUES (:family_id, :email, :invited_by, :created_at)'
            );
            $stmt->execute([
                'family_id'  => $familyId,
                'email'      => $email,
                'invited_by' => $user['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        Response::json([
            'invite_code' => $family['invite_code'],
            'family_name' => $family['name'],
        ]);
    });

    // DELETE /families/{id}/members/{userId} - remove member or leave
    $router->delete('/families/{id}/members/{userId}', function (array $params) use ($db) {
        $user = authenticate();
        $familyId = (int) $params['id'];
        $targetUserId = (int) $params['userId'];

        $myRole = Family::getMemberRole($db, $familyId, $user['id']);
        if ($myRole === false) {
            Response::error('You are not a member of this family', 403);
        }

        if ($targetUserId === (int) $user['id']) {
            // Leaving the family - any member can do this
            Family::removeMember($db, $familyId, $targetUserId);
            Response::json(['message' => 'You have left the family']);
        }

        // Removing someone else - must be admin
        if ($myRole !== 'admin') {
            Response::error('Only admins can remove other members', 403);
        }

        if (!Family::isMember($db, $familyId, $targetUserId)) {
            Response::notFound('Member not found in this family');
        }

        Family::removeMember($db, $familyId, $targetUserId);
        Response::json(['message' => 'Member removed']);
    });
}
