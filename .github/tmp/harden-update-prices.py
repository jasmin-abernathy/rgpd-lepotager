from pathlib import Path

path = Path('cron/update-prices.php')
text = path.read_text(encoding='utf-8')


def replace_once(old: str, new: str) -> None:
    global text
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'Expected one match, got {count}: {old[:120]!r}')
    text = text.replace(old, new, 1)

replace_once(
    "$base=dirname(__DIR__);\nrequire_once __DIR__.'/bootstrap-public-data.php';",
    "$base=dirname(__DIR__);\n\n/* Empêche deux exécutions cron de crawler les mêmes sources en parallèle. */\n$lockPath=sys_get_temp_dir().'/rgpd-lepotager-update-prices.lock';\n$lockHandle=fopen($lockPath,'c');\nif($lockHandle===false) throw new RuntimeException('Unable to open cron lock: '.$lockPath);\nif(!flock($lockHandle,LOCK_EX|LOCK_NB)){\n    echo \"SKIP: update-prices already running\\n\";\n    exit(0);\n}\nregister_shutdown_function(static function() use($lockHandle): void {\n    @flock($lockHandle,LOCK_UN);\n    @fclose($lockHandle);\n});\n\n$startedAt=microtime(true);\n$crawlBudgetSeconds=60;\n$deadline=$startedAt+$crawlBudgetSeconds;\nif(function_exists('set_time_limit')) @set_time_limit(75);\n\nrequire_once __DIR__.'/bootstrap-public-data.php';"
)

replace_once(
    "$ua=$c['user_agent']; $delay=(int)$c['delay_ms']; $timeout=(int)$c['timeout_seconds']; $log=[];",
    "$ua=$c['user_agent'];\n$delay=max(0,min(1200,(int)$c['delay_ms']));\n$timeout=max(2,min(6,(int)$c['timeout_seconds']));\n$log=[];"
)

replace_once(
    "$idx=[]; foreach($d['observations'] as $i=>$o) $idx[$o['id']]=$i;\nforeach($c['rules'] as $r){\n    $u=$r['url'];",
    "$idx=[]; foreach($d['observations'] as $i=>$o) $idx[$o['id']]=$i;\nforeach($c['rules'] as $r){\n    if(microtime(true)>=$deadline){\n        $log[]=['status'=>'budget_exhausted','budget_seconds'=>$crawlBudgetSeconds];\n        break;\n    }\n    $u=$r['url'];"
)

replace_once(
    "    if(!allowed($u,$ua,$timeout)){$log[]=['url'=>$u,'status'=>'robots'];continue;}\n    $h=geturl($u,$ua,$timeout);",
    "    if(!allowed($u,$ua,$timeout)){$log[]=['url'=>$u,'status'=>'robots'];continue;}\n    if(microtime(true)>=$deadline){\n        $log[]=['url'=>$u,'status'=>'budget_exhausted','budget_seconds'=>$crawlBudgetSeconds];\n        break;\n    }\n    $h=geturl($u,$ua,$timeout);"
)

replace_once(
    "    $log[]=['url'=>$u,'status'=>'ok','updated'=>$up];\n    usleep($delay*1000);",
    "    $log[]=['url'=>$u,'status'=>'ok','updated'=>$up];\n    $remainingUs=(int)max(0,($deadline-microtime(true))*1000000);\n    if($remainingUs>0 && $delay>0) usleep(min($delay*1000,$remainingUs));"
)

replace_once(
    "file_put_contents($lf,json_encode(['finished_at'=>date(DATE_ATOM),'events'=>$log],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);",
    "file_put_contents($lf,json_encode([\n    'finished_at'=>date(DATE_ATOM),\n    'duration_seconds'=>round(microtime(true)-$startedAt,3),\n    'network_timeout_seconds'=>$timeout,\n    'events'=>$log\n],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);"
)

path.write_text(text, encoding='utf-8')
print('cron/update-prices.php hardened')
