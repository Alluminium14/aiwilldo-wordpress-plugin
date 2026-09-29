(()=>{
  'use strict';

  const script=document.currentScript;
  if(!script||document.body.classList.contains('awd-lm-visual')) return;

  const language=(script.dataset.language||document.documentElement.lang||'de').slice(0,2).toLowerCase();
  let supplied={};
  try{supplied=JSON.parse(script.dataset.config||'{}')}catch(error){supplied={}}
  const config=Object.assign({
    cookieName:'awd_consent',
    version:'1',
    lifetimeDays:180,
    policyUrl:'',
    showFooterControl:true,
    categories:{analytics:true,marketing:true}
  },window.awdLmConsentConfig||{},supplied);

  const translations={
    de:{
      title:'Ihre Privatsphäre. Ihre Entscheidung.',
      intro:'Wir verwenden notwendige Technologien für den sicheren Betrieb. Analyse und Marketing aktivieren wir nur mit Ihrer Zustimmung.',
      accept:'Alle akzeptieren',reject:'Optionale ablehnen',settings:'Einstellungen',
      settingsTitle:'Cookie-Einstellungen',settingsIntro:'Sie entscheiden, welche optionalen Technologien wir verwenden dürfen.',
      necessary:'Notwendig',necessaryText:'Erforderlich für Sicherheit, Formulare und das Speichern Ihrer Auswahl. Diese Kategorie kann nicht deaktiviert werden.',
      analytics:'Analyse',analyticsText:'Hilft uns zu verstehen, welche Seitenbereiche besucht werden und wie Besucher mit der Website interagieren.',
      marketing:'Marketing',marketingText:'Erlaubt zukünftige Werbe- und Retargeting-Dienste. Ohne Zustimmung werden diese Dienste nicht geladen.',
      always:'Immer aktiv',save:'Auswahl speichern',back:'Zurück',close:'Schließen',policy:'Cookie-Richtlinie',footer:'Cookie-Einstellungen'
    },
    en:{
      title:'Your privacy. Your choice.',
      intro:'We use necessary technologies to operate this website securely. Analytics and marketing are activated only with your consent.',
      accept:'Accept all',reject:'Reject optional',settings:'Settings',
      settingsTitle:'Cookie settings',settingsIntro:'Choose which optional technologies we may use.',
      necessary:'Necessary',necessaryText:'Required for security, forms and remembering your choice. This category cannot be disabled.',
      analytics:'Analytics',analyticsText:'Helps us understand which sections are viewed and how visitors interact with the website.',
      marketing:'Marketing',marketingText:'Allows future advertising and retargeting services. These services are not loaded without consent.',
      always:'Always active',save:'Save selection',back:'Back',close:'Close',policy:'Cookie policy',footer:'Cookie settings'
    },
    fr:{
      title:'Votre vie privée. Votre choix.',
      intro:'Nous utilisons les technologies nécessaires au fonctionnement sécurisé du site. L’analyse et le marketing ne sont activés qu’avec votre accord.',
      accept:'Tout accepter',reject:'Refuser les options',settings:'Paramètres',
      settingsTitle:'Paramètres des cookies',settingsIntro:'Choisissez les technologies optionnelles que nous pouvons utiliser.',
      necessary:'Nécessaires',necessaryText:'Requis pour la sécurité, les formulaires et la mémorisation de votre choix. Cette catégorie ne peut pas être désactivée.',
      analytics:'Analyse',analyticsText:'Nous aide à comprendre les sections consultées et les interactions des visiteurs avec le site.',
      marketing:'Marketing',marketingText:'Autorise les futurs services publicitaires et de reciblage. Ces services ne sont pas chargés sans consentement.',
      always:'Toujours actifs',save:'Enregistrer',back:'Retour',close:'Fermer',policy:'Politique de cookies',footer:'Paramètres des cookies'
    }
  };
  const text=Object.assign({},translations[language]||translations.de,config.text||{});
  const categories=['necessary','analytics','marketing'];
  const categoryEnabled=category=>category==='necessary'||!config.categories||config.categories[category]!==false;
  const cookieOptions=`Path=/; Max-Age=${Math.max(1,Number(config.lifetimeDays)||180)*86400}; SameSite=Lax${location.protocol==='https:'?'; Secure':''}`;
  let current=null,lastFocus=null,banner=null,modal=null;

  const readCookie=()=>{
    const prefix=`${encodeURIComponent(config.cookieName)}=`;
    const raw=document.cookie.split('; ').find(item=>item.startsWith(prefix));
    if(!raw) return null;
    try{
      const value=JSON.parse(decodeURIComponent(raw.slice(prefix.length)));
      if(!value||String(value.v)!==String(config.version)||!value.c||value.e<Date.now()) return null;
      return {necessary:true,analytics:categoryEnabled('analytics')&&!!value.c.analytics,marketing:categoryEnabled('marketing')&&!!value.c.marketing,version:String(value.v),created:value.t,expires:value.e};
    }catch(error){return null}
  };
  const publicState=()=>current?Object.freeze(Object.assign({},current)):null;
  const announce=(name,previous)=>document.dispatchEvent(new CustomEvent(name,{detail:{consent:publicState(),previous:previous?Object.assign({},previous):null}}));
  const announceCategoryChanges=previous=>{
    ['analytics','marketing'].forEach(category=>{
      const before=!!(previous&&previous[category]),after=!!(current&&current[category]);
      if(before===after)return;
      const detail={category,granted:after,consent:publicState()};
      document.dispatchEvent(new CustomEvent('awd:consent-category-change',{detail}));
      document.dispatchEvent(new CustomEvent(`awd:${category}-consent-${after?'granted':'revoked'}`,{detail}));
    });
  };
  const store=selection=>{
    const previous=current;
    const now=Date.now(),expires=now+Math.max(1,Number(config.lifetimeDays)||180)*86400000;
    current={necessary:true,analytics:categoryEnabled('analytics')&&!!selection.analytics,marketing:categoryEnabled('marketing')&&!!selection.marketing,version:String(config.version),created:now,expires};
    const value={v:String(config.version),t:now,e:expires,c:{analytics:current.analytics,marketing:current.marketing}};
    document.cookie=`${encodeURIComponent(config.cookieName)}=${encodeURIComponent(JSON.stringify(value))}; ${cookieOptions}`;
    closeAll();
    announce('awd:consent-change',previous);
    announceCategoryChanges(previous);
    return publicState();
  };
  const erase=()=>{
    const previous=current;
    document.cookie=`${encodeURIComponent(config.cookieName)}=; Path=/; Max-Age=0; SameSite=Lax${location.protocol==='https:'?'; Secure':''}`;
    current=null;
    announce('awd:consent-change',previous);
    announceCategoryChanges(previous);
    showBanner();
  };
  const button=(label,className,handler)=>{const node=document.createElement('button');node.type='button';node.className=className;node.textContent=label;node.addEventListener('click',handler);return node};
  const policyLink=()=>{
    if(!config.policyUrl) return null;
    const link=document.createElement('a');link.className='wtg-consent-policy';link.href=config.policyUrl;link.textContent=text.policy;return link;
  };
  const removeNode=node=>{if(node&&node.parentNode)node.parentNode.removeChild(node)};
  const restoreFocus=()=>{if(lastFocus&&document.contains(lastFocus))lastFocus.focus();lastFocus=null};
  const closeAll=()=>{removeNode(banner);removeNode(modal);banner=null;modal=null;document.body.classList.remove('wtg-consent-open');restoreFocus()};
  const trapFocus=(container,event)=>{
    if(event.key==='Escape'){event.preventDefault();if(container===modal&&current){closeAll()}else if(container===modal){showBanner()}return}
    if(event.key!=='Tab') return;
    const focusable=Array.from(container.querySelectorAll('button:not([disabled]),a[href],input:not([disabled])'));
    if(!focusable.length) return;
    const first=focusable[0],last=focusable[focusable.length-1];
    if(event.shiftKey&&document.activeElement===first){event.preventDefault();last.focus()}
    else if(!event.shiftKey&&document.activeElement===last){event.preventDefault();first.focus()}
  };
  const showBanner=()=>{
    closeAll();lastFocus=document.activeElement;
    banner=document.createElement('section');banner.className='wtg-consent-banner';banner.setAttribute('role','dialog');banner.setAttribute('aria-modal','true');banner.setAttribute('aria-labelledby','wtg-consent-title');
    const content=document.createElement('div');content.className='wtg-consent-banner__content';
    const copy=document.createElement('div');copy.className='wtg-consent-copy';copy.innerHTML=`<h2 id="wtg-consent-title">${escapeHtml(text.title)}</h2><p>${escapeHtml(text.intro)}</p>`;
    const policy=policyLink();if(policy)copy.appendChild(policy);
    const actions=document.createElement('div');actions.className='wtg-consent-actions';
    actions.append(button(text.reject,'wtg-consent-button wtg-consent-button--decision',()=>store({analytics:false,marketing:false})));
    actions.append(button(text.accept,'wtg-consent-button wtg-consent-button--decision wtg-consent-button--accent',()=>store({analytics:true,marketing:true})));
    actions.append(button(text.settings,'wtg-consent-button wtg-consent-button--settings',showSettings));
    content.append(copy,actions);banner.appendChild(content);document.body.appendChild(banner);document.body.classList.add('wtg-consent-open');
    banner.addEventListener('keydown',event=>trapFocus(banner,event));
    requestAnimationFrame(()=>banner.querySelector('button').focus());
  };
  const categoryRow=(key,checked,disabled)=>{
    const row=document.createElement('label');row.className='wtg-consent-category';
    const copy=document.createElement('span');copy.className='wtg-consent-category__copy';copy.innerHTML=`<strong>${escapeHtml(text[key])}</strong><span>${escapeHtml(text[`${key}Text`])}</span>`;
    const control=document.createElement('span');control.className='wtg-consent-category__control';
    const input=document.createElement('input');input.type='checkbox';input.name=key;input.checked=checked;input.disabled=disabled;input.setAttribute('aria-label',text[key]);
    const slider=document.createElement('span');slider.className='wtg-consent-switch';slider.setAttribute('aria-hidden','true');control.append(input,slider);
    if(disabled){const always=document.createElement('small');always.textContent=text.always;control.appendChild(always)}
    row.append(copy,control);return row;
  };
  function showSettings(){
    // Preselect all available optional categories for a new visitor, while
    // keeping them inactive until the visitor explicitly saves or accepts.
    // When settings are reopened, always show the visitor's stored choices.
    const selection=current||{necessary:true,analytics:categoryEnabled('analytics'),marketing:categoryEnabled('marketing')};
    closeAll();lastFocus=document.activeElement;
    modal=document.createElement('div');modal.className='wtg-consent-modal';modal.setAttribute('role','dialog');modal.setAttribute('aria-modal','true');modal.setAttribute('aria-labelledby','wtg-consent-settings-title');
    const panel=document.createElement('div');panel.className='wtg-consent-modal__panel';
    const heading=document.createElement('div');heading.className='wtg-consent-modal__heading';heading.innerHTML=`<div><h2 id="wtg-consent-settings-title">${escapeHtml(text.settingsTitle)}</h2><p>${escapeHtml(text.settingsIntro)}</p></div>`;
    heading.appendChild(button('×','wtg-consent-close',()=>current?closeAll():showBanner()));
    const choices=document.createElement('div');choices.className='wtg-consent-categories';choices.append(categoryRow('necessary',true,true));
    if(categoryEnabled('analytics')) choices.append(categoryRow('analytics',selection.analytics,false));
    if(categoryEnabled('marketing')) choices.append(categoryRow('marketing',selection.marketing,false));
    const actions=document.createElement('div');actions.className='wtg-consent-modal__actions';
    actions.append(button(text.reject,'wtg-consent-button wtg-consent-button--decision',()=>store({analytics:false,marketing:false})));
    actions.append(button(text.accept,'wtg-consent-button wtg-consent-button--decision wtg-consent-button--accent',()=>store({analytics:true,marketing:true})));
    actions.append(button(text.save,'wtg-consent-button wtg-consent-button--save',()=>store({analytics:!!(panel.querySelector('[name="analytics"]')||{}).checked,marketing:!!(panel.querySelector('[name="marketing"]')||{}).checked})));
    const policy=policyLink();if(policy)actions.appendChild(policy);
    panel.append(heading,choices,actions);modal.appendChild(panel);document.body.appendChild(modal);document.body.classList.add('wtg-consent-open');
    modal.addEventListener('keydown',event=>trapFocus(modal,event));
    requestAnimationFrame(()=>modal.querySelector('input:not([disabled]),button').focus());
  }
  function escapeHtml(value){const node=document.createElement('span');node.textContent=String(value||'');return node.innerHTML}
  const addFooterControl=()=>{
    if(!config.showFooterControl||document.querySelector('[data-awd-cookie-settings]')) return;
    const footer=document.querySelector('.wtg-footer-links');if(!footer)return;
    const control=button(text.footer,'wtg-footer-cookie-settings',showSettings);control.dataset.awdCookieSettings='';footer.appendChild(control);
  };
  document.addEventListener('click',event=>{
    const trigger=event.target.closest('[data-awd-cookie-settings],a[href="#cookie-settings"]');
    if(trigger){event.preventDefault();showSettings()}
  });

  current=readCookie();
  window.awdLmConsent={
    get:publicState,
    has:category=>category==='necessary'||!!(current&&categories.includes(category)&&categoryEnabled(category)&&current[category]),
    open:showSettings,
    acceptAll:()=>store({analytics:true,marketing:true}),
    rejectOptional:()=>store({analytics:false,marketing:false}),
    save:selection=>store(selection||{}),
    reset:erase
  };
  addFooterControl();
  if(!current) showBanner();
  announce('awd:consent-ready');
})();
