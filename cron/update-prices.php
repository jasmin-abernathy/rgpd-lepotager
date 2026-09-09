<?php
declare(strict_types=1);

$base=dirname(__DIR__);

/* Empêche deux exécutions cron de crawler les mêmes sources en parallèle. */
$lockPath=sys_get_temp_dir().'/rgpd-lepotager-update-prices.lock';
$lockHandle=fopen($lockPath,'c');
if($lockHandle===false) throw new RuntimeException('Unable to open cron lock: '.$lockPath);
if(!flock($lockHandle,LOCK_EX|LOCK_NB)){
    echo "SKIP: update-prices already running\n";
    exit(0);
}
register_shutdown_function(static function() use($lockHandle): void {
    @flock($lockHandle,LOCK_UN);
    @fclose($lockHandle);
});

$startedAt=microtime(true);
$crawlBudgetSeconds=60;
$deadline=$startedAt+$crawlBudgetSeconds;
if(function_exists('set_time_limit')) @set_time_limit(75);

require_once __DIR__.'/bootstrap-public-data.php';
$publicFile=rgpd_ensure_public_data($base);
$df=__DIR__.'/private/observations-private.json';
$rf=__DIR__.'/private/crawl-rules.json';
$lf=__DIR__.'/last-run.json';

$d=json_decode((string)file_get_contents($df),true,512,JSON_THROW_ON_ERROR);
$c=json_decode((string)file_get_contents($rf),true,512,JSON_THROW_ON_ERROR);
$ua=$c['user_agent'];
$delay=max(0,min(1200,(int)$c['delay_ms']));
$timeout=max(2,min(6,(int)$c['timeout_seconds']));
$log=[];

function geturl(string $u,string $ua,int $t): string|false {
    $x=stream_context_create(['http'=>[
        'timeout'=>$t,'follow_location'=>1,'max_redirects'=>4,
        'header'=>"User-Agent: $ua\r\nAccept: text/html\r\n"
    ]]);
    return @file_get_contents($u,false,$x);
}
function allowed(string $u,string $ua,int $t): bool {
    $p=parse_url($u); if(!$p) return false;
    $r=$p['scheme'].'://'.$p['host'].'/robots.txt'; $txt=geturl($r,$ua,$t);
    if($txt===false) return true;
    $path=strtolower($p['path']??'/'); $active=false;
    foreach(preg_split('/\R/',strtolower($txt)) as $l){
        $l=trim(preg_replace('/#.*/','',$l));
        if(str_starts_with($l,'user-agent:')){$active=trim(substr($l,11))==='*';continue;}
        if($active&&str_starts_with($l,'disallow:')){
            $q=trim(substr($l,9)); if($q!==''&&str_starts_with($path,$q)) return false;
        }
    }
    return true;
}
function textonly(string $h): string {
    $h=preg_replace('#<(script|style|noscript)\b[^>]*>.*?</\1>#is',' ',$h);
    return preg_replace('/\s+/u',' ',html_entity_decode(strip_tags($h),ENT_QUOTES|ENT_HTML5,'UTF-8'));
}
function num(string $s): ?float {
    $s=preg_replace('/[\s\x{00A0}.]/u','',$s); $s=str_replace(',','.',$s);
    return is_numeric($s)?(float)$s:null;
}
function summary(array $items): array {
    $g=[];
    foreach($items as $o){
        $k=$o['service_family']."\x1f".$o['client_segment']."\x1f".$o['unit'];
        $g[$k][]=(float)$o['value'];
    }
    $out=[];
    foreach($g as $k=>$v){
        sort($v); [$service,$segment,$unit]=explode("\x1f",$k);
        $n=count($v); $med=$n%2?$v[intdiv($n,2)]:($v[$n/2-1]+$v[$n/2])/2;
        $out[]=[
            'service_family'=>$service,'client_segment'=>$segment,'unit'=>$unit,'n'=>$n,
            'mean'=>round(array_sum($v)/$n,2),'median'=>round($med,2),
            'min'=>min($v),'max'=>max($v),
            'quality'=>$n>=3?'échantillon':($n===2?'petit échantillon':'observation unique'),
            'display_as_comparison'=>$n>=2 && min($v)!==max($v)
        ];
    }
    usort($out,fn($a,$b)=>[$a['service_family'],$a['client_segment']]<=>[$b['service_family'],$b['client_segment']]);
    return $out;
}

$idx=[]; foreach($d['observations'] as $i=>$o) $idx[$o['id']]=$i;
foreach($c['rules'] as $r){
    if(microtime(true)>=$deadline){
        $log[]=['status'=>'budget_exhausted','budget_seconds'=>$crawlBudgetSeconds];
        break;
    }
    $u=$r['url'];
    if(!allowed($u,$ua,$timeout)){$log[]=['url'=>$u,'status'=>'robots'];continue;}
    if(microtime(true)>=$deadline){
        $log[]=['url'=>$u,'status'=>'budget_exhausted','budget_seconds'=>$crawlBudgetSeconds];
        break;
    }
    $h=geturl($u,$ua,$timeout);
    if($h===false){$log[]=['url'=>$u,'status'=>'fetch_failed'];continue;}
    $t=textonly($h);
    if(!preg_match('/'.$r['pattern'].'/iu',$t,$m)){$log[]=['url'=>$u,'status'=>'pattern_failed'];continue;}
    $up=[];
    foreach($r['updates'] as $x){
        if(!isset($idx[$x['id']],$m[$x['capture']])) continue;
        $v=num($m[$x['capture']]); $i=$idx[$x['id']]; $old=(float)$d['observations'][$i]['value'];
        if(!$v||($old>0&&abs($v-$old)/$old>.70)){
            $log[]=['id'=>$x['id'],'status'=>'manual_review','old'=>$old,'candidate'=>$v]; continue;
        }
        $d['observations'][$i]['value']=$v;
        $d['observations'][$i]['checked']=date('Y-m-d');
        $up[]=['id'=>$x['id'],'old'=>$old,'new'=>$v];
    }
    $log[]=['url'=>$u,'status'=>'ok','updated'=>$up];
    $remainingUs=(int)max(0,($deadline-microtime(true))*1000000);
    if($remainingUs>0 && $delay>0) usleep(min($delay*1000,$remainingUs));
}

/* La classification comparison_scope est persistante dans observations-private.json.
   Un crawl ne peut donc pas réintégrer un DPO ou un cabinet d'avocats dans les moyennes. */
$market=[]; $dpo=[]; $legal=[];
foreach($d['observations'] as $o){
    $scope=$o['comparison_scope']??'market';
    if($scope==='market' && ($o['included']??true)) $market[]=$o;
    elseif($scope==='dpo') $dpo[]=$o;
    elseif($scope==='legal') $legal[]=$o;
}
$marketSummaries=summary($market);
$dpoSummaries=summary($dpo);

/* Sources publiques : mêmes règles d'anonymisation. */
$byHost=[];
foreach($d['observations'] as $o){
    $p=parse_url($o['source']); $host=strtolower($p['host']??'');
    if(str_starts_with($host,'www.')) $host=substr($host,4);
    $byHost[$host][]=$o;
}
$friendly=['malt.fr'=>'Malt']; $sourceGroups=[];
foreach($byHost as $host=>$items){
    $providers=[];$types=[];$services=[];$scopes=['market'=>0,'dpo'=>0,'legal'=>0,'historical'=>0];$checked='1970-01-01';
    foreach($items as $o){
        $providers[$o['provider']]=1;$types[$o['provider_type']]=1;$services[$o['service_family']]=1;
        $scope=$o['comparison_scope']??'market'; if(isset($scopes[$scope]))$scopes[$scope]++;
        if($o['checked']>$checked)$checked=$o['checked'];
    }
    $shared=count($providers)>1;$independent=false;
    foreach(array_keys($types) as $type){
        $l=mb_strtolower($type);
        if(str_contains($l,'indépendant')||str_contains($l,'freelance')){$independent=true;break;}
    }
    $anon=$shared||$independent;
    $label=$friendly[$host]??($anon?$host:array_key_first($providers));
    $nature=$anon?($independent?'Plateforme de freelances':'Plateforme de prestataires'):(count($types)===1?array_key_first($types):'Site du prestataire');
    $perimeter=$anon?($shared?(count($items).' relevés anonymisés sur '.count($providers).' profils'):(count($items).' relevé(s) anonymisé(s)')):(count($items).' tarif(s) public(s) relevé(s)');
    $sv=array_keys($services);$serviceText=implode(' · ',array_slice($sv,0,3));if(count($sv)>3)$serviceText.=' · +'.(count($sv)-3).' autre(s)';
    $parts=[];
    if($scopes['market'])$parts[]=$scopes['market'].' marché';
    if($scopes['dpo'])$parts[]=$scopes['dpo'].' DPO hors moyenne';
    if($scopes['legal'])$parts[]=$scopes['legal'].' juridique hors moyenne';
    if($scopes['historical'])$parts[]=$scopes['historical'].' historique';
    $sourceGroups[]=[
        'source_label'=>$label,'host'=>$host,'nature'=>$nature,'anonymized'=>$anon,
        'perimeter'=>$perimeter,'services'=>$serviceText,'observation_count'=>count($items),
        'market_count'=>$scopes['market'],'dpo_count'=>$scopes['dpo'],'legal_count'=>$scopes['legal'],
        'historical_count'=>$scopes['historical'],'status'=>implode(' · ',$parts),
        'checked'=>$checked,'source'=>'https://'.$host.'/'
    ];
}
usort($sourceGroups,fn($a,$b)=>strcmp($a['source_label'],$b['source_label']));

$legalOffers=[];
foreach($legal as $o){
    $legalOffers[]=[
        'provider'=>$o['provider'],'provider_type'=>$o['provider_type'],
        'service_family'=>$o['service_family'],'client_segment'=>$o['client_segment'],
        'unit'=>$o['unit'],'value'=>$o['value'],'price_text'=>$o['price_text'],
        'checked'=>$o['checked'],'source'=>$o['source'],'note'=>$o['note']??''
    ];
}

$oldPublic=json_decode((string)file_get_contents($publicFile),true);
$public=[
    'generated_at'=>date('Y-m-d'),
    'method'=>$oldPublic['method'],
    'our_offers'=>$oldPublic['our_offers'],
    'summaries'=>$marketSummaries,
    'dpo_summaries'=>$dpoSummaries,
    'legal_offers'=>$legalOffers,
    'source_groups'=>$sourceGroups,
    'underlying_observation_count'=>count($d['observations']),
    'market_observation_count'=>count($market),
    'dpo_observation_count'=>count($dpo),
    'legal_observation_count'=>count($legal),
    'source_group_count'=>count($sourceGroups)
];

$d['generated_at']=date('Y-m-d');
$tmp=$df.'.tmp';
file_put_contents($tmp,json_encode($d,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
rename($tmp,$df);

$tmpPublic=$publicFile.'.tmp';
file_put_contents($tmpPublic,json_encode($public,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
rename($tmpPublic,$publicFile);

file_put_contents($lf,json_encode([
    'finished_at'=>date(DATE_ATOM),
    'duration_seconds'=>round(microtime(true)-$startedAt,3),
    'network_timeout_seconds'=>$timeout,
    'events'=>$log
],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
require_once __DIR__.'/lib-comparator.php'; rgpd_rebuild_public(dirname(__DIR__)); echo "OK\n";
