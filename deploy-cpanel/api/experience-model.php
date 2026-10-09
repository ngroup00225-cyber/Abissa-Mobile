<?php
require_once __DIR__.'/evolution-model.php';
function abissaProgramme(array $saved): array {
    foreach($saved as $day)if(is_array($day)&&($day['modelVersion']??0)===2)return $saved;
    $seed=json_decode((string)file_get_contents(dirname(__DIR__).'/assets/programme-2026.json'),true);
    $days=$seed['days'];
    if($saved)$days[]=['id'=>'programme-legacy','modelVersion'=>2,'legacy'=>true,'active'=>false,'title'=>'Ancien programme conservé','items'=>$saved];
    return $days;
}
function abissaLearningEntries(array $saved): array {
    static $seed, $baseline;
    if($seed===null){$catalog=json_decode((string)file_get_contents(dirname(__DIR__).'/assets/nzema-learning.json'),true);$seed=$catalog['entries'];$baseline=$catalog['baseline']??[];}
    $entries=$seed;$ids=[];$keys=[];
    foreach($entries as $i=>$entry){$ids[$entry['id']]=$i;$keys[mb_strtolower(trim($entry['ntzima']).'|'.trim($entry['french']),'UTF-8')]=$i;}
    foreach($saved as $entry){
        if(!is_array($entry))continue;
        $key=mb_strtolower(trim((string)($entry['ntzima']??'')).'|'.trim((string)($entry['french']??'')),'UTF-8');
        $index=$ids[$entry['id']??'']??$keys[$key]??null;
        if($index!==null){
            $old=$baseline[$entry['id']??'']??[];
            foreach(['ntzima','french','category','type','note','validation','variant','source'] as $field)if(isset($old[$field])&&($entry[$field]??null)===$old[$field])unset($entry[$field]);
            $entries[$index]=array_merge($entries[$index],$entry);continue;
        }
        $entry['id']=$entry['id']??'nzi-custom-'.substr(hash('sha256',$key),0,16);
        $entries[]=array_merge(['ntzima'=>'','french'=>'','phonetic'=>'','category'=>'Autres','type'=>'mot','note'=>'','validation'=>'needs_local_review','audio'=>'','image'=>'','active'=>true],$entry);
    }
    return array_values($entries);
}
function normalizeEditorialLinks(array $state): array {
    $visit=function($v,$key='')use(&$visit){if(is_array($v)){foreach($v as $k=>$x)$v[$k]=$visit($x,is_int($k)?$key:(string)$k);return $v;}
        if(in_array($key,['website','officialWebsite','url','link'],true)&&($v===null||is_string($v))){$v=trim((string)$v);if(str_starts_with($v,'//'))$v='https:'.$v;elseif(preg_match('~^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}(?::[0-9]+)?(?:[/?#].*)?$~i',$v))$v='https://'.$v;}return $v;};
    return $visit($state);
}
function abissaExperienceState(array $state): array {
    $state['PROGRAMME']=abissaProgramme((array)($state['PROGRAMME']??[]));
    $state['NZEMA_DICT']=abissaLearningEntries((array)($state['NZEMA_DICT']??[]));
    $state=normalizeEditorialLinks($state);
    $state['WELCOME']=array_merge(['logo'=>'/appli/assets/logo-site-2026.png'],(array)($state['WELCOME']??[]));
    $state['SETTINGS']=array_merge(['shopWhatsapp'=>'+2250749001515','memberRoles'=>['Membre','Président','Bénévole'],'adminIdleMinutes'=>3,'appBanners'=>[],'abissaLexicon'=>[],'liveSources'=>[],'countdownEnabled'=>true,'countdownTarget'=>'2026-10-23T07:00:00+00:00'],(array)($state['SETTINGS']??[]));
    $state['SETTINGS']['liveSources']=array_map(fn($s)=>array_merge(['name'=>'','type'=>'radio','url'=>'','embedUrl'=>'','description'=>'','active'=>true],(array)$s),(array)$state['SETTINGS']['liveSources']);
    $contact=['name'=>'','logo'=>'','img'=>'','photos'=>[],'desc'=>'','phone'=>'','phone2'=>'','whatsapp'=>'','email'=>'','website'=>'','address'=>'','latitude'=>'','longitude'=>'','active'=>true];
    foreach(['HOTELS','PARTNERS','ADVERTISEMENTS'] as $key){$defaults=$key==='HOTELS'?array_merge(['type'=>'hotel'],$contact,['stars'=>0,'priceList'=>[]]):array_merge($contact,['category'=>'Partenaires']);$state[$key]=array_map(fn($item)=>array_merge($defaults,(array)$item),(array)($state[$key]??[]));}
    $state['MENU_ITEMS']=array_map(fn($m)=>($m['page']??'')==='reservations'?array_merge($m,['id'=>'lexique','page'=>'lexique','line1'=>'Lexique Abissa','line2'=>'','gated'=>false]):$m,(array)($state['MENU_ITEMS']??[]));
    foreach(['lexique'=>'Lexique Abissa','direct'=>'Abissa en direct','publicite'=>'Espace Publicité'] as $id=>$title)if(!array_filter($state['MENU_ITEMS'],fn($m)=>($m['page']??'')===$id))$state['MENU_ITEMS'][]=['id'=>$id,'page'=>$id,'line1'=>$title,'line2'=>'','icon'=>'book','color'=>'#00548a','gated'=>false];
    return abissaEvolutionState($state);
}
