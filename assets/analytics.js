(()=>{
  'use strict';
  const script=document.currentScript;
  if(!script||document.body.classList.contains('awd-lm-visual')||script.dataset.enabled!=='1') return;
  const endpoint=script.dataset.endpoint;
  if(!endpoint) return;
  const storageKey='awd_lm_analytics_session';
  const uuid=()=>globalThis.crypto&&crypto.randomUUID?crypto.randomUUID():`${Date.now().toString(16)}-${Array.from(crypto.getRandomValues(new Uint8Array(16)),b=>b.toString(16).padStart(2,'0')).join('')}`;
  let runtime=null;
  const consentGranted=()=>!!(window.awdLmConsent&&window.awdLmConsent.has('analytics'));
  const removeStoredIdentity=()=>{try{sessionStorage.removeItem(storageKey)}catch(error){}}

  const start=()=>{
    if(runtime||!consentGranted()) return;
    const maxIdle=30*60*1000,now=Date.now();
    let state;
    try{state=JSON.parse(sessionStorage.getItem(storageKey)||'null')}catch(error){state=null}
    if(!state||!state.id||now-(state.lastActivity||0)>maxIdle) state={id:uuid(),lastActivity:now};
    state.lastActivity=now;
    try{sessionStorage.setItem(storageKey,JSON.stringify(state))}catch(error){}
    const params=new URLSearchParams(location.search),campaign={};
    ['utm_source','utm_medium','utm_campaign','utm_content','utm_term','utm_id','gclid','fbclid','msclkid'].forEach(key=>{const value=params.get(key);if(value)campaign[key]=value.slice(0,190)});
    const cleanUrl=value=>{try{const url=new URL(value,location.href),clean=new URL(url.origin+url.pathname);['utm_source','utm_medium','utm_campaign','utm_content','utm_term','utm_id','gclid','fbclid','msclkid'].forEach(key=>{if(url.searchParams.has(key))clean.searchParams.set(key,url.searchParams.get(key).slice(0,190))});return clean.toString()}catch(error){return ''}};
    const context={landing_id:(script.dataset.landing||'gloriafood').slice(0,50),language:script.dataset.language||document.documentElement.lang||'de',landing_url:cleanUrl(location.href),referrer:cleanUrl(document.referrer),device_type:innerWidth<=760?'mobile':innerWidth<=1024?'tablet':'desktop',campaign,email_click_token:(params.get('awd_click')||'').slice(0,64)};
    const queue=[],cleanups=[],viewed=new Set();
    let timer=0,stopped=false,observer=null,engagementTick=Date.now(),engagementActive=document.visibilityState==='visible'&&document.hasFocus();
    const on=(target,name,handler,options)=>{target.addEventListener(name,handler,options);cleanups.push(()=>target.removeEventListener(name,handler,options))};
    const flush=useBeacon=>{clearTimeout(timer);timer=0;if(stopped||!consentGranted()||!queue.length)return;const events=queue.splice(0,20),body=JSON.stringify({session_id:state.id,context,events});if(useBeacon&&navigator.sendBeacon){navigator.sendBeacon(endpoint,new Blob([body],{type:'application/json'}));return}fetch(endpoint,{method:'POST',headers:{'Content-Type':'application/json'},body,credentials:'same-origin',keepalive:true}).catch(()=>{})};
    const track=(name,data={})=>{if(stopped||!consentGranted())return;state.lastActivity=Date.now();try{sessionStorage.setItem(storageKey,JSON.stringify(state))}catch(error){}queue.push({event_id:uuid(),name,timestamp:Date.now(),page_url:cleanUrl(location.href),section_id:data.section_id||'',section_name:(data.section_name||'').trim().slice(0,190),element_id:data.element_id||'',element_label:(data.element_label||'').trim().slice(0,255),metadata:data.metadata||{}});if(queue.length>=10)flush(false);else if(!timer)timer=setTimeout(()=>flush(false),1200)};
    const recordEngagement=()=>{const tick=Date.now(),seconds=Math.min(30,Math.max(0,Math.round((tick-engagementTick)/1000)));engagementTick=tick;if(engagementActive&&seconds)track('engagement_ping',{metadata:{seconds}});engagementActive=document.visibilityState==='visible'&&document.hasFocus()};
    const stop=({purge=false}={})=>{if(stopped)return;stopped=true;clearTimeout(timer);clearInterval(runtime&&runtime.engagementInterval);queue.length=0;if(observer)observer.disconnect();cleanups.splice(0).forEach(cleanup=>cleanup());const hidden=document.querySelector('#wtgDemoForm input[name="analytics_session_id"]');if(hidden)hidden.remove();if(purge)removeStoredIdentity();runtime=null;window.awdLmAnalytics={track:()=>{},sessionId:'',flush:()=>{}}};
    runtime={stop,track,flush,engagementInterval:0};
    window.awdLmAnalytics={track,sessionId:state.id,flush};
    track('page_view');
    if('IntersectionObserver'in window){observer=new IntersectionObserver(entries=>entries.forEach(entry=>{if(entry.isIntersecting&&entry.intersectionRatio>=.5){const key=entry.target.dataset.awdSectionKey||entry.target.id||'',heading=entry.target.querySelector('.wtg-kicker,.wtg-h2,h2,h3'),sectionName=(entry.target.dataset.analyticsName||(heading&&heading.textContent)||key).trim();if(key&&!viewed.has(key)){viewed.add(key);track('section_view',{section_id:key,section_name:sectionName})}}}),{threshold:[.5]});document.querySelectorAll('section[id],[data-awd-section-key]').forEach(section=>observer.observe(section))}
    on(document,'click',event=>{const target=event.target.closest('a,button,.wtg-interest-option,.wtg-faq-q');if(!target||target.closest('.wtg-consent-banner,.wtg-consent-modal'))return;const section=target.closest('section[id],[data-awd-section-key]'),heading=section&&section.querySelector('.wtg-kicker,.wtg-h2,h2,h3'),data={section_id:section?(section.dataset.awdSectionKey||section.id):'',section_name:section?((section.dataset.analyticsName||(heading&&heading.textContent)||section.id||'').trim()):'',element_id:target.id||'',element_label:target.textContent||''};if(target.classList.contains('wtg-faq-q'))return track('faq_open',data);if(target.closest('.wtg-nav-links'))return track('menu_click',data);const href=target.getAttribute('href')||'';if(href.startsWith('tel:'))return track('phone_click',data);if(href.startsWith('mailto:'))return track('email_click',data);if(target.closest('.wtg-language-switch'))return track('language_change',data);if(href&&/^https?:/i.test(href)){try{if(new URL(href,location.href).origin!==location.origin)return track('external_link_click',data)}catch(error){}}if(target.matches('.wtg-btn,button[type="submit"]'))track('cta_click',data)});
    const form=document.getElementById('wtgDemoForm');
    if(form){const hidden=document.createElement('input');hidden.type='hidden';hidden.name='analytics_session_id';hidden.value=state.id;form.appendChild(hidden);let started=false;on(form,'input',()=>{if(!started){started=true;track('form_start',{section_id:'demo',element_id:'wtg-demo-form'})}},{passive:true});on(form,'change',event=>{if(event.target&&event.target.name==='interest[]')track('interest_selected',{section_id:'demo',element_id:event.target.value||''})});on(form,'submit',()=>{track('form_submit',{section_id:'demo',element_id:'wtg-demo-form'});flush(false)})}
    runtime.engagementInterval=setInterval(recordEngagement,15000);
    on(document,'visibilitychange',()=>{recordEngagement();if(document.visibilityState==='hidden')flush(true)});on(window,'focus',recordEngagement);on(window,'blur',recordEngagement);on(window,'pagehide',()=>{recordEngagement();flush(true)});
  };
  const revoke=()=>{if(runtime)runtime.stop({purge:true});else removeStoredIdentity()};
  document.addEventListener('awd:consent-ready',start);
  document.addEventListener('awd:consent-change',event=>{if(event.detail&&event.detail.consent&&event.detail.consent.analytics)start();else revoke()});
  if(window.awdLmConsent){if(consentGranted())start();else revoke()}else window.awdLmAnalytics={track:()=>{},sessionId:'',flush:()=>{}};
})();
