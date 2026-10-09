<?php
// Account owner confirmation is required; no other account ID is accepted.
if ($route === 'users/me' && $method === 'DELETE') {
    $actor = authenticatedUser();
    $body = requestJson();
    if (($body['confirmation'] ?? '') !== 'SUPPRIMER') jsonResponse(['error'=>'Confirmez en écrivant SUPPRIMER.'],422);
    if (!password_verify((string)($body['password'] ?? ''), (string)($actor['passwordHash'] ?? ''))) jsonResponse(['error'=>'Mot de passe incorrect.'],403);
    require_once __DIR__.'/account-deletion-model.php';
    try { abissaDeleteAccount((string)$actor['id']); }
    catch (Throwable $error) { jsonResponse(['error'=>'Suppression incomplète. Réessayez ou contactez le support.'],503); }
    jsonResponse(['ok'=>true,'message'=>'Votre compte et les données associées ont été supprimés.']);
}
