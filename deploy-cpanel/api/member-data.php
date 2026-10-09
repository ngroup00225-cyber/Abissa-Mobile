<?php
function memberRoleOptions(): array {
    $path=dirname(__DIR__).'/data/state.json';
    $state=is_file($path)?json_decode((string)file_get_contents($path),true):[];
    return array_values(array_unique(array_merge((array)($state['SETTINGS']['memberRoles']??['Membre','Président','Bénévole']),['Autre'])));
}
function memberYear(array $data): string {
    $value=(string)($data['joinedYear']??$data['joinedAt']??'');
    return preg_match('/^([12][0-9]{3})(?:$|-)/',$value,$m)?$m[1]:'';
}
function validateMemberData(array $data, bool $editing=false): array {
    if (trim((string)($data['name']??$data['fullName']??''))==='') jsonResponse(['error'=>'Indiquez le nom et les prénoms.'],422);
    // Old APKs may still send joinedAt. Preserve that date and expose its year.
    $year=memberYear($data);
    if (array_key_exists('joinedYear',$data) && (string)$data['joinedYear']!=='' && !preg_match('/^[12][0-9]{3}$/',(string)$data['joinedYear'])) jsonResponse(['error'=>'Année d’adhésion : saisissez quatre chiffres.'],422);
    if ($year!=='' && ((int)$year<1900 || (int)$year>(int)gmdate('Y')+1)) jsonResponse(['error'=>'Année d’adhésion invalide.'],422);
    $data['joinedYear']=$year;
    if (array_key_exists('roleChoice',$data)) {
        $choice=(string)$data['roleChoice'];
        if (!in_array($choice,memberRoleOptions(),true) && !($editing && $choice===($data['role']??null))) jsonResponse(['error'=>'Choisissez une fonction proposée.'],422);
        $other=cleanText($data['roleOther']??'',100);
        if ($choice==='Autre' && $other==='') jsonResponse(['error'=>'Précisez votre fonction.'],422);
        $data['role']=$choice==='Autre'?$other:$choice;
        $data['roleOther']=$choice==='Autre'?$other:'';
    } elseif (empty($data['role'])) $data['role']='Membre';
    if (abissaCommitteeKey((string)$data['role'])==='president') {
        $found=false;
        foreach (abissaCommitteeDefaults()['commissions'] as $c) if (abissaCommissionMatches($c,(string)($data['commission']??''))) {$found=true;break;}
        if (!$found) jsonResponse(['error'=>'Choisissez la commission présidée.'],422);
    }
    return $data;
}
function memberPublicPresidents(array $committee): array {
    $path=dirname(__DIR__).'/data/submissions.json';
    $dossiers=is_file($path)?json_decode((string)file_get_contents($path),true):[];
    foreach($committee['commissions'] as &$commission)$commission['members']=[];unset($commission);
    $candidates=array_values(array_filter((array)$dossiers,fn($s)=>is_array($s)&&($s['type']??'')==='member'&&($s['status']??'')==='validated'&&abissaCommitteeKey((string)($s['data']['role']??''))==='president'));
    usort($candidates,fn($a,$b)=>strcmp(($b['validatedAt']??$b['updatedAt']??$b['createdAt']??'').($b['id']??''),($a['validatedAt']??$a['updatedAt']??$a['createdAt']??'').($a['id']??'')));
    foreach ($committee['commissions'] as &$commission) {
        foreach ($candidates as $s) {
            if (!abissaCommissionMatches($commission,(string)($s['data']['commission']??''))) continue;
            $d=$s['data'];$name=(string)($d['name']??$d['fullName']??'');
            $commission['members']=array_values(array_filter($commission['members'],fn($p)=>abissaCommitteeKey((string)$p['name'])!==abissaCommitteeKey($name)));
            foreach ($commission['members'] as &$person) if(abissaCommitteeKey((string)$person['role'])==='president')$person['role']='Membre';
            unset($person);
            array_unshift($commission['members'],['name'=>$name,'role'=>'Président','description'=>'Présidence de la commission','img'=>(string)($d['photo']??''),'sourceId'=>(string)$s['id']]);
            break;
        }
    }
    unset($commission);
    $committee['additionalMembers']=[];
    $all=array_values(array_filter((array)$dossiers,fn($s)=>is_array($s)&&($s['type']??'')==='member'&&($s['status']??'')==='validated'));
    usort($all,fn($a,$b)=>strcmp(($b['updatedAt']??$b['createdAt']??''),($a['updatedAt']??$a['createdAt']??'')));
    $seen=[];
    foreach($all as $s){
        $d=(array)($s['data']??[]);$name=trim((string)($d['name']??$d['fullName']??''));
        if($name==='')continue;
        $identity=abissaCommitteeKey($name).'|'.abissaCommitteeKey((string)($d['commission']??''));
        if(isset($seen[$identity]))continue;$seen[$identity]=true;
        $person=['name'=>$name,'role'=>(string)($d['role']??'Membre'),'description'=>'','img'=>(string)($d['photo']??''),'sourceId'=>(string)$s['id']];
        $matched=false;
        foreach($committee['commissions'] as &$commission){
            if(!abissaCommissionMatches($commission,(string)($d['commission']??'')))continue;
            $matched=true;
            if(abissaCommitteeKey($person['role'])==='president')break;
            $commission['members']=array_values(array_filter($commission['members'],fn($p)=>abissaCommitteeKey((string)$p['name'])!==abissaCommitteeKey($name)));
            $commission['members'][]=$person;break;
        }
        unset($commission);
        if(!$matched)$committee['additionalMembers'][]=$person;
    }
    return $committee;
}
