<?php
// A locked registry keeps short member numbers stable, unique and never reused.
function memberRegistration(string $id): string {
    global $dataDir;
    $handle = fopen($dataDir.'/member_registry.json', 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) jsonResponse(['error'=>'Attribution du matricule indisponible'],503);
    try {
        $registry = json_decode(stream_get_contents($handle) ?: '{}', true) ?: [];
        if (isset($registry['members'][$id])) return $registry['members'][$id];
        $next = (int)($registry['sequence'] ?? 0) + 1;
        if ($next >= 1679616) jsonResponse(['error'=>'Capacité des matricules atteinte'],503);
        $number = 'AB26'.str_pad(strtoupper(base_convert((string)$next,10,36)),4,'0',STR_PAD_LEFT);
        $registry['sequence']=$next; $registry['members'][$id]=$number;
        rewind($handle); ftruncate($handle,0);
        fwrite($handle,json_encode($registry,JSON_UNESCAPED_UNICODE)); fflush($handle);
        return $number;
    } finally { flock($handle,LOCK_UN); fclose($handle); }
}
function withMemberRegistration(array $item): array {
    if (($item['type']??'')==='member') { $item['data']['registration']=memberRegistration((string)$item['id']); $item['data']['joinedYear']=memberYear((array)$item['data']); }
    return $item;
}
