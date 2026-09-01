<?php
declare(strict_types=1);

function rgpd_host(string $url): string {
    $p=parse_url($url); $h=strtolower((string)($p['host']??''));
    return str_starts_with($h,'www.') ? substr($h,4) : $h;
}
function rgpd_base_url(string $url): string {
    $p=parse_url($url); return ($p['scheme']??'https').'://'.($p['host']??'').'/';
}
function rgpd_region_match(array $o,string $region): bool {
    if($region==='france') return true;
    $r=$o['provider_region']??'unknown'; $d=(string)($o['provider_department']??'');
    if($region==='grand-est') return $r==='grand-est' || $d==='57';
    if($region==='moselle') return $d==='57';
    return true;
}
function rgpd_summary(array $items): array {
    $g=[];
    foreach($items as $o){
        $k=$o['service_family']."\x1f".$o['client_segment']."\x1f".$o['unit'];
        $g[$k][]=(float)$o['value'];
    }
    $out=[];
    foreach($g as $k=>$v){
        sort($v,SORT_NUMERIC); [$service,$segment,$unit]=explode("\x1f",$k);
        $n=count($v); $med=$n%2?$v[intdiv($n,2)]:($v[$n/2-1]+$v[$n/2])/2;
        $out[]=['service_family'=>$service,'client_segment'=>$segment,'unit'=>$unit,'n'=>$n,
          'mean'=>round(array_sum($v)/$n,2),'median'=>round($med,2),'min'=>min($v),'max'=>max($v),
          'quality'=>$n>=3?'échantillon':($n===2?'petit échantillon':'observation unique'),
          'display_as_comparison'=>$n>=2 && min($v)!==max($v)];
    }
    usort($out,fn($a,$b)=>[$a['service_family'],$a['client_segment'],$a['unit']]<=>[$b['service_family'],$b['client_segment'],$b['unit']]);
    return $out;
}
function rgpd_public_provider(array $o): string {
    $host=rgpd_host($o['source']); $type=mb_strtolower((string)($o['provider_type']??''));
    $anon=str_contains($type,'indépendant')||str_contains($type,'freelance')||$host==='malt.fr';
    if($anon) return $host==='malt.fr'?'Profil indépendant — Malt':'Prestataire indépendant — identité non publiée';
    return (string)$o['provider'];
}
function rgpd_source_groups(array $items): array {
    $friendly=['malt.fr'=>'Malt','dmouvrard.com'=>'D. Mouvrard','aquiothermes.fr'=>'AQUI O Thermes',
      'dataprivacy.pro'=>'Data Privacy Professionals','egidecyber.com'=>'EgideCyber','administra-tiff.fr'=>'Administra-Tiff',
      'boscop.fr'=>'Boscop','allositeinternet.com'=>'Allo Site Internet','rgpd-avocat-mizrahi.com'=>'Cabinet Mizrahi',
      'deshoulieres-avocats.com'=>'Deshoulières Avocats','codpo.fr'=>'CODPO','lexagone.fr'=>'Lexagone',
      'rgpd-express.com'=>'RGPD Express','confiance-digitale.fr'=>'Conformis / Confiance Digitale','rgpdbox.com'=>'RGPDBox',
      'fastrgpd.com'=>'Fast RGPD','aurele-it.fr'=>'Aurele IT','ressources.apave.com'=>'Apave','mydari.fr'=>'mydari',
      'cholez-pagotto.fr'=>'Cabinet Cholez-Pagotto'];
    $by=[]; foreach($items as $o){$by[rgpd_host($o['source'])][]=$o;}
    $groups=[];
    foreach($by as $host=>$rows){
        $providers=[];$types=[];$services=[];$sc=['market'=>0,'dpo'=>0,'legal'=>0,'historical'=>0];$checked='1970-01-01';
        foreach($rows as $o){$providers[$o['provider']]=1;$types[$o['provider_type']]=1;$services[$o['service_family']]=1;
          $scope=$o['comparison_scope']??'market';if(isset($sc[$scope]))$sc[$scope]++;if(($o['checked']??'')>$checked)$checked=$o['checked'];}
        $shared=count($providers)>1;$ind=false; foreach(array_keys($types) as $t){$l=mb_strtolower($t);if(str_contains($l,'indépendant')||str_contains($l,'freelance')){$ind=true;break;}}
        $anon=$shared||$ind||$host==='malt.fr'; $label=$friendly[$host]??($anon?$host:array_key_first($providers));
        $nature=$anon?($ind||$host==='malt.fr'?'Plateforme de freelances':'Plateforme de prestataires'):(count($types)===1?array_key_first($types):'Site du prestataire');
        $perimeter=$anon?($shared?(count($rows).' relevés anonymisés sur '.count($providers).' profils'):(count($rows).' relevé(s) anonymisé(s)')):(count($rows).' tarif(s) public(s) relevé(s)');
        $sv=array_keys($services);$st=implode(' · ',array_slice($sv,0,3));if(count($sv)>3)$st.=' · +'.(count($sv)-3).' autre(s)';
        $parts=[];if($sc['market'])$parts[]=$sc['market'].' marché';if($sc['dpo'])$parts[]=$sc['dpo'].' DPO hors moyenne';if($sc['legal'])$parts[]=$sc['legal'].' expertise juridique hors moyenne';if($sc['historical'])$parts[]=$sc['historical'].' historique';
        $groups[]=['source_label'=>$label,'host'=>$host,'nature'=>$nature,'anonymized'=>$anon,'perimeter'=>$perimeter,'services'=>$st,
          'observation_count'=>count($rows),'market_count'=>$sc['market'],'dpo_count'=>$sc['dpo'],'legal_count'=>$sc['legal'],'historical_count'=>$sc['historical'],
          'status'=>implode(' · ',$parts),'checked'=>$checked,'source'=>rgpd_base_url($rows[0]['source'])];
    }
    usort($groups,fn($a,$b)=>strcmp($a['source_label'],$b['source_label'])); return $groups;
}
function rgpd_build_view(array $obs,string $region): array {
    $items=array_values(array_filter($obs,fn($o)=>rgpd_region_match($o,$region)));
    $market=array_values(array_filter($items,fn($o)=>(($o['comparison_scope']??'market')==='market') && ($o['included']??true)));
    $dpo=array_values(array_filter($items,fn($o)=>(($o['comparison_scope']??'')==='dpo')));
    $legal=array_values(array_filter($items,fn($o)=>(($o['comparison_scope']??'')==='legal')));
    $legalOffers=[];foreach($legal as $o){$legalOffers[]=['provider'=>rgpd_public_provider($o),'provider_type'=>$o['provider_type'],'service_family'=>$o['service_family'],'client_segment'=>$o['client_segment'],'unit'=>$o['unit'],'value'=>$o['value'],'price_text'=>$o['price_text'],'checked'=>$o['checked'],'source'=>$o['source'],'note'=>$o['note']??''];}
    return ['summaries'=>rgpd_summary($market),'dpo_summaries'=>rgpd_summary($dpo),'legal_offers'=>$legalOffers,'source_groups'=>rgpd_source_groups($items),
      'underlying_observation_count'=>count($items),'market_observation_count'=>count($market),'dpo_observation_count'=>count($dpo),'legal_observation_count'=>count($legal),'source_group_count'=>count(rgpd_source_groups($items))];
}
function rgpd_rebuild_public(string $base): array {
    $privateFile=$base.'/cron/private/observations-private.json';$publicFile=$base.'/comparateur/data/sources.json';
    $d=json_decode((string)file_get_contents($privateFile),true,512,JSON_THROW_ON_ERROR);$old=json_decode((string)file_get_contents($publicFile),true,512,JSON_THROW_ON_ERROR);
    $method=$old['method']??[];
    $method['regional_filter_text']='Le filtre régional est volontaire : aucun emplacement du visiteur n’est détecté. La vue Grand Est/Moselle utilise uniquement les prestataires dont la localisation a été explicitement vérifiée.';
    $public=['generated_at'=>date('Y-m-d'),'method'=>$method,'our_offers'=>$old['our_offers']??[],
      'views'=>['france'=>rgpd_build_view($d['observations'],'france'),'grand-est'=>rgpd_build_view($d['observations'],'grand-est'),'moselle'=>rgpd_build_view($d['observations'],'moselle')]];
    $tmp=$publicFile.'.tmp';file_put_contents($tmp,json_encode($public,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);rename($tmp,$publicFile);return $public;
}
