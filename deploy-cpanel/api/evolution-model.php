<?php
// Additive schema defaults shared by the API and the public website.
function abissaRoyalPalaceDefaults(): array {
    return ['title'=>'Palais Royal de Grand-Bassam','presentation'=>'','history'=>'','king'=>'',
        'kingProfile'=>['name'=>'','reignTitle'=>'','biography'=>'','image'=>'','active'=>true],
        'royaltyHistory'=>'','traditions'=>'','architecture'=>'','visits'=>'','articles'=>[],
        'authorities'=>'','culture'=>'','image'=>'','photos'=>[],'videos'=>[],
        'address'=>'','latitude'=>'','longitude'=>'','website'=>'','phone'=>'','email'=>''];
}
function abissaRoyalPalace(array $saved): array {
    $defaults=abissaRoyalPalaceDefaults();
    $profile=is_array($saved['kingProfile']??null)?$saved['kingProfile']:[];
    // Older published king text remains visible until the biography is edited.
    if(!array_key_exists('biography',$profile)&&is_string($saved['king']??null))$profile['biography']=$saved['king'];
    $palace=array_merge($defaults,$saved);
    $palace['kingProfile']=array_merge($defaults['kingProfile'],$profile);
    $palace['articles']=array_values(array_map(fn($article)=>array_merge(['title'=>'','text'=>'','image'=>'','photos'=>[],'videos'=>[],'active'=>true],$article),array_filter((array)($saved['articles']??[]),'is_array')));
    return $palace;
}
function abissaEvolutionState(array $state): array {
    $settings=(array)($state['SETTINGS']??[]);
    $settings+=['officialWebsite'=>'https://abissamobile.com/','touristGuides'=>[],
        'royalPalace'=>[],
        'requestControls'=>[]];
    $settings['royalPalace']=abissaRoyalPalace((array)$settings['royalPalace']);
    foreach(['member'=>'membres','press'=>'accréditation','pass'=>'laissez-passer'] as $key=>$label) {
        $settings['requestControls'][$key]=array_merge(['open'=>true,'message'=>'Les demandes de '.$label.' sont actuellement clôturées. Merci de votre compréhension.'],(array)($settings['requestControls'][$key]??[]));
    }
    $state['SETTINGS']=$settings;
    $menu=(array)($state['MENU_ITEMS']??[]);
    foreach(['guides'=>'Guide touristique','palais'=>'Palais Royal de Grand-Bassam','site-web'=>'Visitez notre site web','boutique'=>'Boutique'] as $id=>$label) {
        if(!array_filter($menu,fn($m)=>($m['page']??'')===$id))$menu[]=['id'=>$id,'page'=>$id,'line1'=>$label,'line2'=>'','active'=>true,'hidden'=>false,'gated'=>false,'icon'=>'book','color'=>'#07567e'];
    }
    $state['MENU_ITEMS']=array_values(array_map(fn($m)=>array_merge(['active'=>true,'hidden'=>false],$m),array_filter($menu,fn($m)=>!in_array($m['page']??'',['autres-services','other-services'],true))));
    foreach(['SITES','PARTNERS'] as $key)$state[$key]=array_map(function($item)use($key){
        if($key==='SITES')return array_merge(['category'=>'Sites culturels','latitude'=>'','longitude'=>'','active'=>true],$item);
        $official=preg_match('/orange|solibra|bock/i',(string)($item['name']??''))||preg_match('/officiel/i',(string)($item['category']??''));
        return array_merge(['partnerType'=>$official?'official':'partner'],$item);
    },(array)($state[$key]??[]));
    $state['BADGE_SETTINGS']=array_merge(['widthMm'=>95,'heightMm'=>135,'templateVersion'=>5,'frontBackground'=>'','commissionSize'=>7.5,'commissionX'=>50,'commissionY'=>15,'commissionFont'=>'Arial','commissionColor'=>'','commissionAlign'=>'center','commissionNames'=>[]],(array)($state['BADGE_SETTINGS']??[]));
    return $state;
}
