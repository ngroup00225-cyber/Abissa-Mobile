<?php
if ($route === 'admin/committee') {
    requirePermission('COMMITTEE', $method !== 'GET');
    $saved=(array)readJsonFile('state',[]);
    if ($method==='GET') jsonResponse(abissaCommittee($saved));
    if ($method==='PUT') {
        $body=requestJson();
        if (($body['schemaVersion']??0)!==2 || !is_array($body['leaders']??null) || !is_array($body['commissions']??null)) jsonResponse(['error'=>'Structure du comité invalide.'],422);
        $saved['COMMITTEE']=abissaCommittee(['COMMITTEE'=>$body,'COMMISSIONS'=>$saved['COMMISSIONS']??[]],false);
        writeJsonFile('state',$saved);
        jsonResponse(['committee'=>abissaCommittee($saved)]);
    }
    jsonResponse(['error'=>'Méthode non autorisée'],405);
}
