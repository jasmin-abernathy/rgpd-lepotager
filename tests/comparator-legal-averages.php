<?php
declare(strict_types=1);
require dirname(__DIR__) . '/cron/lib-comparator.php';

// La fixture emploie uniquement des libellés ASCII ; mbstring reste facultatif en CI.
if (!function_exists('mb_strtolower')) {
    function mb_strtolower(string $v): string { return strtolower($v); }
}
function check_same(mixed $actual, mixed $expected, string $message): void {
    if ($actual !== $expected) {
        throw new RuntimeException($message . ': expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}
function offer(string $provider, string $scope, string $service, string $segment, string $unit, float $value, string $region, string $department): array {
    return ['provider'=>$provider,'provider_type'=>'Cabinet avocat','comparison_scope'=>$scope,
        'service_family'=>$service,'client_segment'=>$segment,'unit'=>$unit,'value'=>$value,
        'provider_region'=>$region,'provider_department'=>$department,'source'=>'https://'.strtolower($provider).'.example.com/prix',
        'checked'=>'2026-10-17','included'=>true,'price_text'=>(string)$value];
}
$rows=[
    offer('A','legal','Audit RGPD','TPE','EUR',500,'grand-est','57'),
    offer('B','legal','Audit RGPD','TPE','EUR',700,'grand-est','67'),
    offer('C','legal','Audit RGPD','TPE','EUR',1000,'ile-de-france','75'),
    offer('D','legal','Audit RGPD','TPE','EUR/jour',350,'grand-est','57'),
    offer('E','legal','Conseil juridique','TPE','EUR',900,'grand-est','57'),
    offer('F','legal','Conseil juridique','TPE','EUR',900,'grand-est','67'),
    offer('G','market','Audit RGPD','TPE','EUR',5,'grand-est','57'),
    offer('H','dpo','Audit RGPD','TPE','EUR',50,'grand-est','57'),
    offer('I','legal','Audit RGPD','TPE','EUR',0,'grand-est','57')
];
$fr=rgpd_build_view($rows,'france');
$ge=rgpd_build_view($rows,'grand-est');
$mo=rgpd_build_view($rows,'moselle');
foreach([$fr,$ge,$mo] as $view) {
    if (isset($view['legal_offers'])) throw new RuntimeException('Prix par cabinet encore publiés');
    foreach ($view['legal_summaries'] as $s) {
        if ($s['n'] < 2) throw new RuntimeException('Publication d’une observation juridique isolée');
        foreach (['provider','price_text','source','note'] as $field) {
            if (isset($s[$field])) throw new RuntimeException('Champ individuel exposé : '.$field);
        }
    }
}
check_same(count($fr['legal_summaries']),2,'Deux familles comparables en France');
check_same($fr['legal_summaries'][0]['service_family'],'Audit RGPD','Séparation des prestations');
check_same($fr['legal_summaries'][0]['n'],3,'Nombre de tarifs France');
check_same($fr['legal_summaries'][0]['mean'],733.33,'Moyenne France');
check_same($ge['legal_summaries'][0]['n'],2,'Filtre Grand Est');
check_same($ge['legal_summaries'][0]['mean'],600.0,'Moyenne Grand Est');
check_same($ge['legal_summaries'][1]['mean'],900.0,'Tarifs identiques restent calculables');
check_same(count($mo['legal_summaries']),0,'Aucun singleton ne doit être affiché en Moselle');
check_same(count($fr['dpo_summaries']),1,'Prestations DPO maintenues à part'); 
// Quatre valeurs, deux unités : le regroupement ne doit jamais moyenner
// ensemble des mensualités, des journées ou des forfaits différents.
$dpoRows=[
    offer('J','dpo','DPO externalisé','TPE','EUR/mois',180,'grand-est','57'),
    offer('K','dpo','DPO externalisé','PME','EUR/mois',320,'grand-est','67'),
    offer('L','dpo','DPO externalisé','PME','EUR/an',2400,'grand-est','57'),
    offer('M','dpo','DPO externalisé','TPE','EUR/mois',100,'ile-de-france','75'),
];
$dpoFrance=rgpd_build_view($dpoRows,'france');
$dpoGrandEst=rgpd_build_view($dpoRows,'grand-est');
check_same(count($dpoFrance['dpo_summaries']),3,'Segmentation DPO conserve les types de structures');
check_same(count($dpoFrance['dpo_overall_summaries']),2,'Separation des unités DPO');
$monthly=array_values(array_filter($dpoFrance['dpo_overall_summaries'],fn($s)=>$s['unit']==='EUR/mois'))[0];
check_same($monthly['client_segment'],'Toutes structures','Regroupement de clientele par defaut');
check_same($monthly['n'],3,'Regroupement mensuel TPE et PME');
check_same($monthly['mean'],200.0,'Moyenne simple de toutes les offres mensuelles');
$monthlyGE=array_values(array_filter($dpoGrandEst['dpo_overall_summaries'],fn($s)=>$s['unit']==='EUR/mois'))[0];
check_same($monthlyGE['mean'],250.0,'Filtre regional DPO');
check_same($monthlyGE['n'],2,'Releves DPO Grand Est');
check_same($dpoFrance['dpo_observation_count'],4,'Total DPO conserve');

check_same(count($fr['source_groups'])>0,true,'Le registre des sources reste disponible');
echo "OK : agrégation juridique et confidentialité des données publiques\n";
