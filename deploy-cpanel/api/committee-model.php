<?php
require_once __DIR__.'/member-data.php';
require_once __DIR__.'/experience-model.php';
// Shared by the public website and API. Read-time enrichment never writes state.
function abissaCommitteeDefaults(): array {
    static $defaults;
    return $defaults ??= json_decode((string)file_get_contents(dirname(__DIR__).'/assets/committee-2026.json'), true, 512, JSON_THROW_ON_ERROR);
}
function abissaCommitteeKey(string $value): string {
    $value = strtr(mb_strtolower($value, 'UTF-8'), ['é'=>'e','è'=>'e','ê'=>'e','ë'=>'e','à'=>'a','â'=>'a','î'=>'i','ï'=>'i','ô'=>'o','ö'=>'o','ù'=>'u','û'=>'u','ç'=>'c','&'=>'et']);
    return preg_replace('/[^a-z0-9]/', '', str_replace('commission', '', $value));
}
function abissaCommitteePerson(array $person): array {
    $clean = [];
    foreach (['name','role','description','img'] as $key) $clean[$key] = is_string($person[$key] ?? null) ? mb_substr($person[$key],0,$key==='description'?2000:1000) : '';
    // These are explicitly edited public profiles; never copy dossier contact fields.
    return $clean;
}
function abissaCommissionMatches(array $commission,string $name): bool {
    return in_array(abissaCommitteeKey($name),array_map('abissaCommitteeKey',array_merge([$commission['name']],$commission['aliases']??[])),true);
}
function abissaCommitteeIcon(mixed $icon,string $fallback): string {
    return is_string($icon)&&isset(abissaCommitteeDefaults()['iconGlyphs'][$icon])?$icon:$fallback;
}
function abissaCommittee(array $state, bool $includeAutomatic=true): array {
    $result=abissaCommitteeDefaults(); $saved=(array)($state['COMMITTEE']??[]);
    if (($saved['schemaVersion']??0)===2) {
        foreach ($result['leaders'] as &$leader) foreach ((array)($saved['leaders']??[]) as $person) {
            if (is_array($person) && ($person['id']??'')===$leader['id']) { $leader=array_merge($leader,abissaCommitteePerson($person)); $leader['icon']=abissaCommitteeIcon($person['icon']??'', $leader['icon']); }
        }
        unset($leader);
    } else {
        // The existing PCO portrait remains attached to the same person.
        $president=(array)($saved['president']??[]);
        if (str_contains(abissaCommitteeKey((string)($president['name']??'')),'amichia')) $result['leaders'][1]['img']=(string)($president['img']??'');
    }
    foreach ($result['poles'] as &$pole) foreach ((array)($saved['poles']??[]) as $oldPole) if(is_array($oldPole)&&($oldPole['id']??'')===$pole['id']) $pole['icon']=abissaCommitteeIcon($oldPole['icon']??'',$pole['icon']);
    unset($pole);
    foreach ($result['commissions'] as &$commission) {
        $keys=array_map('abissaCommitteeKey', array_merge([$commission['name']],$commission['aliases']));
        foreach ((array)($saved['commissions']??[]) as $existing) {
            if (!is_array($existing)) continue;
            if (($existing['id']??'')!==$commission['id'] && !in_array(abissaCommitteeKey((string)($existing['name']??'')),$keys,true)) continue;
            $commission['members']=[];
            $commission['icon']=abissaCommitteeIcon($existing['icon']??'',$commission['icon']);
            break;
        }
        $pole=array_values(array_filter($result['poles'],fn($p)=>$p['id']===$commission['poleId']))[0];
        $commission['color']=$pole['color'];
        foreach ((array)($state['COMMISSIONS']??[]) as $configured) if(is_array($configured)&&abissaCommissionMatches($commission,(string)($configured['name']??''))&&preg_match('/^#[0-9a-f]{6}$/i',(string)($configured['color']??''))) $commission['color']=$configured['color'];
    }
    unset($commission);
    foreach(abissaCommitteeDefaults()['leaders'] as $i=>$default)$result['leaders'][$i]['role']=$default['role'];
    if($result['leaders'][3]['role']==='Chargé des Affaires Extérieures')$result['leaders'][3]['role']='VP Chargé des Affaires Extérieures';
    $gold='#D4AF37';foreach((array)($state['COMMISSIONS']??[]) as $c)if(!empty($state['SETTINGS']['commissionsManagedVersion'])&&abissaCommitteeKey((string)($c['name']??''))==='comiteabissa'&&preg_match('/^#[a-f0-9]{6}$/i',(string)($c['color']??'')))$gold=$c['color'];
    foreach($result['leaders'] as &$l)$l['color']=$gold;unset($l);
    if($includeAutomatic)$result=memberPublicPresidents($result);
    // Legacy keys remain compatible with previous APKs and their offline cache.
    $result['president']=$result['leaders'][1]; $result['vicePresident']=$result['leaders'][3];
    return $result;
}
function abissaEnrichState(array $state): array {
    $initializeBanner=!is_array($state['PAGE_BANNERS']??null);
    if($initializeBanner)$state['PAGE_BANNERS']=[];
    $hasCommitteeBanner=false;
    foreach($state['PAGE_BANNERS'] as &$banner) if(is_array($banner)&&($banner['page']??'')==='membres') {
        $hasCommitteeBanner=true;
        if(in_array(trim((string)($banner['title']??'')),['','Membres','Comité d’organisation','COMITÉ D’ORGANISATION'],true))$banner['title']='Comité Abissa';
    }
    unset($banner);
    if(!$hasCommitteeBanner&&$initializeBanner)$state['PAGE_BANNERS'][]=['page'=>'membres','title'=>'Comité Abissa','description'=>'Tradition, culture, rayonnement et développement.','image'=>'','position'=>'center','height'=>200,'active'=>true];
    $state['COMMITTEE']=abissaCommittee($state);
    $configured=is_array($state['COMMISSIONS']??null)?$state['COMMISSIONS']:array_map(fn($c)=>['name'=>$c['name'],'description'=>'','color'=>$c['color'],'active'=>true],$state['COMMITTEE']['commissions']);
    $commissions=[];$seen=[];
    foreach($configured as $c){
        if(!is_array($c))continue;
        $key=abissaCommitteeKey((string)($c['name']??''));
        if(in_array($key,['bureauexecutif','comiteexecutif','comiteabissa'],true)){$c['name']='COMITE ABISSA';if(empty($state['SETTINGS']['commissionsManagedVersion']))$c['color']='#D4AF37';}
        foreach(array_slice(abissaCommitteeDefaults()['commissions'],-2) as $official)if(abissaCommissionMatches($official,(string)($c['name']??'')))$c['name']=$official['name'];
        $key=abissaCommitteeKey((string)($c['name']??''));if(isset($seen[$key]))continue;$seen[$key]=true;$commissions[]=$c;
    }
    if(empty($state['SETTINGS']['commissionsManagedVersion'])&&!isset($seen['comiteabissa']))$commissions[]=['name'=>'COMITE ABISSA','description'=>'Royauté et direction de l’Abissa','color'=>'#D4AF37','active'=>true];
    foreach(abissaCommitteeDefaults()['commissions'] as $official)if(in_array($official['id'],['juridique','force-vente'],true)&&!isset($seen[abissaCommitteeKey($official['name'])]))$commissions[]=['name'=>$official['name'],'description'=>'','color'=>array_values(array_filter(abissaCommitteeDefaults()['poles'],fn($p)=>$p['id']===$official['poleId']))[0]['color'],'active'=>true];
    $state['SETTINGS']['commissionsManagedVersion']=1;
    $state['COMMISSIONS']=$commissions;
    return abissaExperienceState($state);
}
