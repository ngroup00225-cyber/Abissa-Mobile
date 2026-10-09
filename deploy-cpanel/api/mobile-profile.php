<?php
// The mobile profile only exposes the authenticated member's own information.
if ($route === 'users/me' && $method === 'PUT') {
    $actor = authenticatedUser();
    $body = requestJson();
    $name = cleanText($body['name'] ?? '', 100);
    $email = cleanText($body['email'] ?? '', 180);
    if (mb_strlen($name) < 2) jsonResponse(['error'=>'Indiquez votre nom et vos prénoms.'], 422);
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(['error'=>'Adresse e-mail invalide.'], 422);
    $users = (array)readJsonFile('users', []);
    foreach ($users as &$user) {
        if (($user['id'] ?? '') !== $actor['id']) continue;
        // Explicit allowlist: no client-supplied role, status, ID, phone or password changes.
        $user['name'] = $name;
        $user['email'] = $email;
        $updated = $user;
        break;
    }
    unset($user);
    if (!isset($updated)) jsonResponse(['error'=>'Compte indisponible.'], 404);
    writeJsonFile('users', $users);
    unset($updated['passwordHash']);
    jsonResponse(['user'=>$updated]);
}
if ($route === 'users/submissions' && $method === 'GET') {
    $actor = authenticatedUser();
    $items = [];
    foreach ((array)readJsonFile('submissions', []) as $item) {
        if (($item['userId'] ?? '') !== $actor['id']) continue;
        $row = array_intersect_key($item, array_flip(['id','type','status','createdAt']));
        $row['data'] = array_intersect_key((array)($item['data'] ?? []), array_flip(['name','activity','date','people','organization','subject','commission','role','vehicle','plate','validUntil']));
        if (($item['status'] ?? '') === 'validated'
            && in_array($item['type'] ?? '', ['member','press','pass'], true)
            && !(($item['type'] ?? '') === 'pass' && !empty($item['data']['validUntil']) && $item['data']['validUntil'] < gmdate('Y-m-d'))) {
            $row['verifyUrl']=credentialVerifyUrl($item['id']);
        }
        $items[] = $row;
    }
    usort($items, fn($a, $b)=>strcmp($b['createdAt'] ?? '', $a['createdAt'] ?? ''));
    header('Cache-Control: private, no-store');
    jsonResponse(['items'=>$items]);
}
