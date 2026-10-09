<?php
declare(strict_types=1);
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$id=(string)($_GET['id']??'');
$path=__DIR__.'/data/qrcodes.json';
$items=is_file($path)?json_decode((string)file_get_contents($path),true):[];
foreach ((array)$items as $item) {
    if (($item['id']??'')!==$id || ($item['kind']??'')!=='url') continue;
    if (empty($item['active'])) { http_response_code(410); break; }
    $url=(string)($item['content']??'');
    if (filter_var($url,FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url,PHP_URL_SCHEME)?:''),['https','http'],true) && !parse_url($url,PHP_URL_USER)) { header('Location: '.$url,true,302); exit; }
}
if (http_response_code()!==410) http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
?><!doctype html><html lang="fr"><meta name="viewport" content="width=device-width, initial-scale=1"><title>QR Code Abissa</title><body style="font:18px system-ui;max-width:640px;margin:12vh auto;padding:24px"><h1>Ce QR Code est indisponible</h1><p>Ce lien a été désactivé ou supprimé.</p><a href="/">Retour à Abissa Mobile</a></body></html>
