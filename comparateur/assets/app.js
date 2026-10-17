const euro=new Intl.NumberFormat("fr-FR",{maximumFractionDigits:2});
const fmt=(v,u)=>{let x=euro.format(v);return u==="EUR/jour"?x+" €/jour":u==="EUR/mois"?x+" €/mois":u==="EUR/an"?x+" €/an":u==="EUR/traitement"?x+" €/traitement":typeof u==="string"&&u.startsWith("EUR/")?x+" €/"+u.slice(4):x+" €"};
const e=s=>String(s??"").replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#039;"}[c]));

const cards=document.getElementById('cards');
const headline=document.getElementById('headline');
const rows=document.getElementById('rows');
const offers=document.getElementById('offers');
const notai=document.getElementById('notai');

// Anciennes données publiques : regrouper les prix sans afficher de cabinet.
function legacyLegalSummaries(offers=[]){
  const groups=new Map();
  for(const o of offers){
    if(!o||!o.service_family||!o.client_segment||!o.unit)continue;
    const value=Number(o.value);
    if(!Number.isFinite(value)||value<=0)continue;
    const key=JSON.stringify([o.service_family,o.client_segment,o.unit]);
    if(!groups.has(key))groups.set(key,{service_family:o.service_family,client_segment:o.client_segment,unit:o.unit,values:[]});
    groups.get(key).values.push(value);
  }
  return [...groups.values()].filter(g=>g.values.length>=2).map(g=>({
    service_family:g.service_family,client_segment:g.client_segment,unit:g.unit,
    n:g.values.length,mean:Math.round(g.values.reduce((a,b)=>a+b,0)/g.values.length*100)/100
  })).sort((a,b)=>[a.service_family,a.client_segment,a.unit].join('|').localeCompare([b.service_family,b.client_segment,b.unit].join('|'),'fr'));
}

// Fallback pendant la transition du JSON public produit par le cron.
 // Un regroupement depuis les résumés existants fournit la moyenne pondérée,
 // mais PAS une médiane globale ; elle est donc volontairement omise.
function legacyDpoOverallSummaries(summaries=[]){
  const groups=new Map();
  for(const item of summaries){
    const n=Number(item.n),mean=Number(item.mean);
    if(!item.service_family||!item.unit||!Number.isFinite(n)||n<1||!Number.isFinite(mean)||mean<=0)continue;
    const key=JSON.stringify([item.service_family,item.unit]);
    if(!groups.has(key))groups.set(key,{service_family:item.service_family,unit:item.unit,client_segment:'Toutes structures',n:0,sum:0,min:Infinity,max:-Infinity,median:null});
    const g=groups.get(key);
    g.n+=n;g.sum+=n*mean;
    if(Number.isFinite(Number(item.min)))g.min=Math.min(g.min,Number(item.min));
    if(Number.isFinite(Number(item.max)))g.max=Math.max(g.max,Number(item.max));
  }
  return [...groups.values()].filter(g=>g.n>=2).map(g=>({
    service_family:g.service_family,unit:g.unit,client_segment:g.client_segment,
    n:g.n,mean:Math.round(g.sum/g.n*100)/100,
    min:Number.isFinite(g.min)?g.min:null,max:Number.isFinite(g.max)?g.max:null,
    median:null
  }));
}

function normalizeView(v={}){
  return {
    summaries:Array.isArray(v.summaries)?v.summaries:[],
    dpo_summaries:Array.isArray(v.dpo_summaries)?v.dpo_summaries:[],
    dpo_overall_summaries:Array.isArray(v.dpo_overall_summaries)?v.dpo_overall_summaries:legacyDpoOverallSummaries(Array.isArray(v.dpo_summaries)?v.dpo_summaries:[]),
    legal_summaries:Array.isArray(v.legal_summaries)?v.legal_summaries:legacyLegalSummaries(Array.isArray(v.legal_offers)?v.legal_offers:[]),
    source_groups:Array.isArray(v.source_groups)?v.source_groups:[],
    underlying_observation_count:Number(v.underlying_observation_count??0),
    market_observation_count:Number(v.market_observation_count??0),
    dpo_observation_count:Number(v.dpo_observation_count??0),
    legal_observation_count:Number(v.legal_observation_count??(Array.isArray(v.legal_offers)?v.legal_offers.length:0)),
    source_group_count:Number(v.source_group_count??0)
  };
}

fetch("data/sources.json?v=7",{cache:"no-store"}).then(r=>{
  if(!r.ok)throw new Error(`HTTP ${r.status}`);
  return r.json();
}).then(d=>{
  const method=d.method||{};
  if(notai)notai.textContent=method.not_ai_text||'';
  const privacy=document.getElementById("source-privacy");if(privacy)privacy.textContent=method.source_privacy_text||'';
  const legalSep=document.getElementById("legal-separation");if(legalSep)legalSep.textContent=method.legal_separation_text||'';
  const dpoExpl=document.getElementById("dpo-explanation");if(dpoExpl)dpoExpl.textContent=method.dpo_text||'';

  const regionSel=document.getElementById('region'),segmentSel=document.getElementById('segment'),serviceSel=document.getElementById('service'),dpoSegmentSel=document.getElementById('dpo-segment');
  if(!regionSel||!segmentSel||!serviceSel||!dpoSegmentSel||!cards||!headline||!rows||!offers)throw new Error('Comparator DOM incomplete');

  const legacyView=normalizeView(d);
  const hasViews=d.views&&typeof d.views==='object'&&d.views.france;
  const views=hasViews?{
    france:normalizeView(d.views.france),
    'grand-est':normalizeView(d.views['grand-est']),
    moselle:normalizeView(d.views.moselle)
  }:{france:legacyView,'grand-est':legacyView,moselle:legacyView};

  if(!hasViews){
    const grandEst=[...regionSel.options].find(o=>o.value==='grand-est');
    const moselle=[...regionSel.options].find(o=>o.value==='moselle');
    if(grandEst)grandEst.disabled=true;
    if(moselle)moselle.disabled=true;
    regionSel.value='france';
  }else{
    const saved=localStorage.getItem('rgpd-comparator-region');
    if(['france','grand-est','moselle'].includes(saved))regionSel.value=saved;
  }

  const labels={'france':'France','grand-est':'Grand Est','moselle':'Moselle (57)'};
  function current(){return views[regionSel.value]||views.france;}
  function refillFilters(v){const oldSeg=segmentSel.value,oldSrv=serviceSel.value;segmentSel.innerHTML='<option value="all">Toutes</option>';serviceSel.innerHTML='<option value="all">Toutes</option>';const vs=v.summaries.filter(x=>x.n>=2&&x.min!==x.max);[...new Set(vs.map(x=>x.client_segment))].sort().forEach(x=>segmentSel.insertAdjacentHTML('beforeend',`<option>${e(x)}</option>`));[...new Set(vs.map(x=>x.service_family))].sort().forEach(x=>serviceSel.insertAdjacentHTML('beforeend',`<option>${e(x)}</option>`));if([...segmentSel.options].some(o=>o.value===oldSeg))segmentSel.value=oldSeg;if([...serviceSel.options].some(o=>o.value===oldSrv))serviceSel.value=oldSrv;}
  function refillDpoFilter(v){
    const old=dpoSegmentSel.value;
    dpoSegmentSel.replaceChildren(new Option('Toutes les structures','all'));
    [...new Set(v.dpo_summaries.map(x=>x.client_segment).filter(Boolean))].sort((a,b)=>a.localeCompare(b,'fr')).forEach(label=>dpoSegmentSel.add(new Option(label,label)));
    dpoSegmentSel.value=[...dpoSegmentSel.options].some(x=>x.value===old)?old:'all';
  }
  function render(){const v=current();document.getElementById('hero-zone-label').textContent=`Observatoire · tarifs publics · ${labels[regionSel.value]}`;document.getElementById('region-note').textContent=hasViews?(method.regional_filter_text||''):'Vue régionale temporairement indisponible : affichage France entière.';const cnt=document.getElementById('count'),sc=document.getElementById('source-count');if(cnt)cnt.textContent=v.underlying_observation_count;if(sc)sc.textContent=v.source_group_count;const visible=v.summaries.filter(x=>x.n>=2&&x.min!==x.max);const a=visible.filter(x=>(segmentSel.value==='all'||x.client_segment===segmentSel.value)&&(serviceSel.value==='all'||x.service_family===serviceSel.value));cards.innerHTML=a.length?a.map(x=>`<article class="card"><p class="eyebrow">${e(x.client_segment)} · ${e(x.unit.replace('EUR/',''))}</p><h3>${e(x.service_family)}</h3><div class="big">${fmt(x.mean,x.unit)}</div><span class="muted">moyenne observée</span><div class="meta"><div><small>Médiane</small><b>${fmt(x.median,x.unit)}</b></div><div><small>Mini</small><b>${fmt(x.min,x.unit)}</b></div><div><small>Maxi</small><b>${fmt(x.max,x.unit)}</b></div></div><div class="q">n = ${x.n} · ${e(x.quality)}</div></article>`).join(''):'<div class="empty">Pas encore assez de tarifs publics strictement comparables pour cette zone et ces filtres.</div>';
    const t=visible.find(x=>x.service_family==='Conseil RGPD / DPO — TJM'&&x.client_segment==='Tous');if(t){headline.textContent=fmt(t.mean,t.unit);document.getElementById('headline-detail').textContent=`TJM moyen sur ${t.n} relevés anonymisés dans la zone choisie ; médiane ${fmt(t.median,t.unit)}.`}else{headline.textContent='—';document.getElementById('headline-detail').textContent='Pas encore assez de relevés comparables pour ce repère dans la zone choisie.'}
    const overall=dpoSegmentSel.value==='all';
    const dv=(overall?v.dpo_overall_summaries:v.dpo_summaries)
      .filter(x=>Number(x.n)>=2&&Number.isFinite(Number(x.mean))&&(overall||x.client_segment===dpoSegmentSel.value));
    document.getElementById('dpo-cards').innerHTML=dv.length?dv.map(x=>`<article class="card"><p class="eyebrow">${e(overall?'Toutes les structures':x.client_segment)} · ${e(x.unit.replace('EUR/',''))}</p><h3>${e(x.service_family||'DPO externalisé')}</h3><div class="big">${e(fmt(Number(x.mean),x.unit))}</div><span class="muted">Prix moyen observé · hors moyenne principale</span><div class="meta">${x.median!=null?`<div><small>Médiane</small><b>${e(fmt(x.median,x.unit))}</b></div>`:''}${x.min!=null?`<div><small>Mini</small><b>${e(fmt(x.min,x.unit))}</b></div>`:''}${x.max!=null?`<div><small>Maxi</small><b>${e(fmt(x.max,x.unit))}</b></div>`:''}</div><div class="q">${x.n} tarifs publics · ${e(overall?'toutes catégories de clientèle confondues':'clientèle : '+x.client_segment)}</div></article>`).join(''):'<div class="empty">Pas assez de tarifs DPO comparables dans cette zone avec ce filtre. Il faut au moins deux tarifs pour une même prestation et une même unité.</div>';
    const legal=(v.legal_summaries||[]).filter(x=>Number(x.n)>=2&&Number.isFinite(Number(x.mean)));
    document.getElementById('legal-cards').innerHTML=legal.length?legal.map(x=>`<article class="legal-card"><span class="legal-tag">Expertise juridique · hors moyenne principale</span><h4>${e(x.service_family)}</h4><div>${e(x.client_segment)}</div><div class="legal-price">${e(fmt(Number(x.mean),x.unit))}</div><small>Moyenne de ${e(x.n)} tarifs publics comparables · même prestation, même clientèle et même unité.</small><a href="#sources">Consulter le registre des sources ↓</a></article>`).join(''):v.legal_observation_count>0?'<div class="empty">Des tarifs juridiques ont été relevés dans cette zone, mais pas encore deux tarifs comparables pour une même prestation, clientèle et unité. Aucune moyenne fiable à afficher.</div>':'<div class="empty">Aucun tarif de prestation juridique relevé dans cette zone.</div>';
    rows.innerHTML=(v.source_groups||[]).map(s=>`<tr><td><b>${e(s.source_label)}</b>${s.anonymized?'<small>Relevés individuels anonymisés</small>':''}</td><td>${e(s.nature)}</td><td>${e(s.perimeter)}<small>${e(s.services)}</small></td><td>${e(String(s.observation_count))}</td><td>${e(s.status)}</td><td>${e(String(s.checked||'').split('-').reverse().join('/'))}</td><td><a href="${e(s.source)}" target="_blank" rel="noopener nofollow">Consulter le site ↗</a></td></tr>`).join('');
  }
  offers.innerHTML=(Array.isArray(d.our_offers)?d.our_offers:[]).map(o=>`<article class="offer"><small>${e(o.name)}</small><strong>${fmt(o.value,o.unit)}</strong><small>Hors moyenne concurrentielle</small></article>`).join('');
  regionSel.addEventListener('change',()=>{if(hasViews)localStorage.setItem('rgpd-comparator-region',regionSel.value);refillFilters(current());refillDpoFilter(current());render()});segmentSel.addEventListener('change',render);serviceSel.addEventListener('change',render);dpoSegmentSel.addEventListener('change',render);refillFilters(current());refillDpoFilter(current());render();
}).catch(err=>{console.error(err);if(cards)cards.innerHTML='<div class="empty">Impossible de charger les données.</div>';if(headline)headline.textContent='—';});
