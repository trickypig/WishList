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

    // PUT /families/{id}/members/{userId}/child-status - toggle is_child flag
    $router->put('/families/{id}/members/{userId}/child-status', function (array $params) use ($db) {
        $user = authenticate();
        $familyId = (int) $params['id'];
        $targetUserId = (int) $params['userId'];
        $body = $params['_body'];

        // Caller must be a family admin AND not a child
        $myRole = Family::getMemberRole($db, $familyId, $user['id']);
        if ($myRole !== 'admin') {
            Response::error('Only admins can change child status', 403);
        }
        if ($user['is_child']) {
            Response::error('Child users cannot change child status', 403);
        }

        // Cannot set own flag
        if ($targetUserId === (int) $user['id']) {
            Response::error('Cannot change your own child status', 400);
        }

        // Target must be a member of the family
        if (!Family::isMember($db, $familyId, $targetUserId)) {
            Response::notFound('Member not found in this family');
        }

        // Cannot flag other admins
        $targetRole = Family::getMemberRole($db, $familyId, $targetUserId);
        if ($targetRole === 'admin') {
            Response::error('Cannot change child status of other admins', 403);
        }

        $isChild = isset($body['is_child']) ? (int) $body['is_child'] : 0;
        User::setChildStatus($db, $targetUserId, $isChild);

        $targetUser = User::findById($db, $targetUserId);
        Response::json(['user' => $targetUser]);
    });

    // GET /families/{id}/domains - get effective domain list
    $router->get('/families/{id}/domains', function (array $params) use ($db) {
        $user = authenticate();
        $familyId = (int) $params['id'];

        if (!Family::isMember($db, $familyId, $user['id'])) {
            Response::error('You are not a member of this family', 403);
        }

        $domains = AllowedDomain::getEffectiveForFamily($db, $familyId);
        Response::json(['domains' => $domains]);
    });

    // POST /families/{id}/domains - add a family-specific domain (parent only)
    $router->post('/families/{id}/domains', function (array $params) use ($db) {
        $user = authenticate();
        $familyId = (int) $params['id'];
        $body = $params['_body'];

        // Must be parent (admin + not child)
        $myRole = Family::getMemberRole($db, $familyId, $user['id']);
        if ($myRole !== 'admin' || $user['is_child']) {
            Response::error('Only parent admins can manage domains', 403);
        }

        $missing = Validator::required($body, ['domain']);
        if (!empty($missing)) {
            Response::error('Missing required fields: ' . implode(', ', $missing));
        }

        $domainId = AllowedDomain::addFamilyDomain(
            $db,
            $familyId,
            $body['domain'],
            $body['display_name'] ?? '',
            $body['icon_url'] ?? '',
            $user['id']
        );

        Response::json(['id' => $domainId], 201);
    });

    // DELETE /families/{id}/domains/{domain} - remove a domain (parent only)
    $router->delete('/families/{id}/domains/{domain}', function (array $params) use ($db) {
        $user = authenticate();
        $familyId = (int) $params['id'];
        $domain = $params['domain'];

        // Must be parent (admin + not child)
        $myRole = Family::getMemberRole($db, $familyId, $user['id']);
        if ($myRole !== 'admin' || $user['is_child']) {
            Response::error('Only parent admins can manage domains', 403);
        }

        $removed = AllowedDomain::removeFamilyDomain($db, $familyId, $domain);
        if (!$removed) {
            Response::notFound('Domain not found');
        }

        Response::json(['message' => 'Domain removed']);
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
