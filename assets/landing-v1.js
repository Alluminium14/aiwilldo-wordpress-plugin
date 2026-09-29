(function(){
  const page = document.querySelector('.wtg-page');
  if(!page) return;

  /* Sticky navigation */
  const nav = document.getElementById('wtgNav');
  const onScroll = () => { if(nav) nav.classList.toggle('is-scrolled', window.scrollY > 24); };
  onScroll();
  window.addEventListener('scroll', onScroll, {passive:true});

  /* Mobile menu */
  const menuBtn = document.getElementById('wtgMenuBtn');
  const navLinks = document.getElementById('wtgNavLinks');
  if(menuBtn && navLinks){
	const navBar = nav.querySelector('.wtg-nav');
	let navFitFrame;
	const getAnchorScrollTop = target => {
	  const computedPosition = getComputedStyle(target).position;
	  const inlinePosition = target.style.getPropertyValue('position');
	  const inlinePositionPriority = target.style.getPropertyPriority('position');
	  const inlineTop = target.style.getPropertyValue('top');
	  const inlineTopPriority = target.style.getPropertyPriority('top');
	  if(computedPosition === 'sticky'){
		target.style.setProperty('position','relative','important');
		target.style.setProperty('top','auto','important');
	  }
	  const documentTop = target.getBoundingClientRect().top + window.scrollY;
	  if(computedPosition === 'sticky'){
		if(inlinePosition) target.style.setProperty('position',inlinePosition,inlinePositionPriority);
		else target.style.removeProperty('position');
		if(inlineTop) target.style.setProperty('top',inlineTop,inlineTopPriority);
		else target.style.removeProperty('top');
	  }
	  const navHeight = nav ? Math.ceil(nav.getBoundingClientRect().height) : 0;
	  return Math.max(0,Math.round(documentTop - navHeight - 12));
	};
	const closeMenu = () => {
	  navLinks.classList.remove('is-open');
	  document.body.classList.remove('wtg-menu-open');
	  menuBtn.classList.remove('is-open');
	  menuBtn.setAttribute('aria-expanded','false');
	};
	const updateNavFit = () => {
	  cancelAnimationFrame(navFitFrame);
	  navFitFrame = requestAnimationFrame(() => {
		if(!navBar) return;
		const wasCollapsed = navBar.classList.contains('is-auto-collapsed');
		navBar.classList.remove('is-auto-collapsed');
		const logo = navBar.querySelector('.wtg-logo');
		const navGap = parseFloat(getComputedStyle(navBar).columnGap) || 0;
		const linksGap = parseFloat(getComputedStyle(navLinks).columnGap) || 0;
		const items = Array.from(navLinks.children);
		const requiredLinksWidth = items.reduce((width,item)=>width + Math.ceil(Math.max(item.scrollWidth,item.getBoundingClientRect().width)),0) + Math.max(0,items.length - 1) * linksGap;
		const availableLinksWidth = navBar.clientWidth - (logo ? Math.ceil(logo.getBoundingClientRect().width) : 0) - navGap;
		const needsCollapse = requiredLinksWidth > availableLinksWidth || navBar.scrollWidth > navBar.clientWidth + 1;
		navBar.classList.toggle('is-auto-collapsed',needsCollapse);
		if(wasCollapsed && !needsCollapse) closeMenu();
	  });
	};
    menuBtn.addEventListener('click', () => {
      const isOpen = navLinks.classList.toggle('is-open');
      document.body.classList.toggle('wtg-menu-open', isOpen);
      menuBtn.classList.toggle('is-open', isOpen);
      menuBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    });
    navLinks.querySelectorAll('a').forEach(a => a.addEventListener('click', event => {
	  const href = a.getAttribute('href') || '';
	  const target = href.length > 1 && href.charAt(0) === '#' ? document.getElementById(href.slice(1)) : null;
	  if(!target){ closeMenu(); return; }
	  event.preventDefault();
	  closeMenu();
	  requestAnimationFrame(() => requestAnimationFrame(() => {
		window.scrollTo({top:getAnchorScrollTop(target),behavior:window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth'});
		if(window.history && window.history.pushState){
		  const method = window.location.hash === href ? 'replaceState' : 'pushState';
		  window.history[method](null,'',href);
		}else{
		  window.location.hash = href;
		}
	  }));
    }));
    document.addEventListener('keydown', (event) => { if(event.key === 'Escape' && navLinks.classList.contains('is-open')) { navLinks.classList.remove('is-open'); document.body.classList.remove('wtg-menu-open'); menuBtn.classList.remove('is-open'); menuBtn.setAttribute('aria-expanded', 'false'); menuBtn.focus(); } });
	window.addEventListener('resize',updateNavFit,{passive:true});
	window.addEventListener('load',updateNavFit,{once:true});
	if(document.fonts && document.fonts.ready) document.fonts.ready.then(updateNavFit);
	updateNavFit();
  }

  /* Hero slider */
  const slides = [...document.querySelectorAll('.wtg-slide')];
  const dots = [...document.querySelectorAll('.wtg-dot')];
  const mocks = [...document.querySelectorAll('.wtg-mock')];
  const heroCopy = document.querySelector('.wtg-hero-copy');
  const hero = heroCopy && heroCopy.closest('.wtg-hero');
  const durationValue = hero ? getComputedStyle(hero).getPropertyValue('--wtg-hero-slide-duration').trim() : '';
  const durationNumber = parseFloat(durationValue);
  const slideDuration = Number.isFinite(durationNumber) && durationNumber > 0
    ? durationNumber * (durationValue.endsWith('ms') ? 1 : 1000)
    : 6000;
  const trustStrip = hero && hero.nextElementSibling && hero.nextElementSibling.classList.contains('wtg-trust')
	? hero.nextElementSibling
	: document.querySelector('.wtg-trust');
  let current = 0;
  let timer;
  let motionPaused = false;
	let heroInView = true;
	const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

  /* Slides are layered with position:absolute. Reserve enough space for the
     tallest translated slide so the trust strip below the hero can never
     overlap a heading, paragraph, or CTA. */
  function fitHeroToSlides(){
    if(!heroCopy || !slides.length) return;
    const tallestSlide = Math.max(...slides.map(slide => Math.ceil(slide.getBoundingClientRect().height)));
    const controls = heroCopy.querySelector('.wtg-slider-controls');
    const controlsSpace = controls ? Math.ceil(controls.getBoundingClientRect().height) + 28 : 0;
	const minimumHeight = 0;
	heroCopy.style.setProperty('--wtg-hero-copy-height', Math.max(minimumHeight, tallestSlide + controlsSpace) + 'px');
  }

  /* On desktop the hero starts underneath the fixed header visually, while
	 the header itself overlays the page and does not consume document height.
	 Therefore hero + benefits strip (without subtracting the header again)
	 must equal the viewport height. */
  function fitOpeningFold(){
	if(!hero || !page) return;
	if(window.matchMedia('(max-width: 980px)').matches){
		page.style.removeProperty('--wtg-fold-hero-height');
		page.style.removeProperty('--wtg-fold-header-height');
		page.classList.remove('is-compact-fold');
		return;
	}
	const navBar = document.querySelector('.wtg-nav');
	const headerHeight = navBar ? Math.ceil(navBar.getBoundingClientRect().height) : 104;
	 const trustHeight = trustStrip ? Math.ceil(trustStrip.getBoundingClientRect().height) : 0;
	 /* The Restaurants Ordering System opens on the complete hero photograph.
		 Its benefits strip deliberately starts below the first viewport. Other
		 landing profiles retain the historical hero-plus-trust opening fold. */
	 const trustInsideOpeningFold = !document.querySelector('.awd-landing--second-landing');
	 const available = Math.max(360, Math.floor(window.innerHeight - (trustInsideOpeningFold ? trustHeight : 0)));
	page.style.setProperty('--wtg-fold-hero-height', available + 'px');
	page.style.setProperty('--wtg-fold-header-height', headerHeight + 'px');
	page.classList.toggle('is-compact-fold', available < 680);
  }

  function activateSlide(index){
    current = index;
    slides.forEach((el,i)=>el.classList.toggle('is-active',i===index));
    dots.forEach((el,i)=>{
      el.classList.remove('is-active');
      void el.offsetWidth;
      if(i===index) el.classList.add('is-active');
    });
    mocks.forEach(el=>el.classList.toggle('is-active',Number(el.dataset.mock)===index));
  }
  function startSlider(){
	clearInterval(timer);
	if(motionPaused || !heroInView || reducedMotion.matches || slides.length < 2) return;
    timer = setInterval(()=>activateSlide((current+1)%slides.length),slideDuration);
  }
  dots.forEach((dot,i)=>dot.addEventListener('click',()=>{activateSlide(i);startSlider()}));
  activateSlide(0);
  fitHeroToSlides();
	fitOpeningFold();
  window.addEventListener('resize', ()=>{ fitHeroToSlides(); fitOpeningFold(); }, {passive:true});
	window.addEventListener('orientationchange', fitOpeningFold, {passive:true});
	window.addEventListener('load', ()=>{ fitHeroToSlides(); fitOpeningFold(); }, {once:true});
	if(document.fonts && document.fonts.ready) document.fonts.ready.then(()=>{ fitHeroToSlides(); fitOpeningFold(); });
	if(trustStrip && 'ResizeObserver' in window){
		const foldObserver = new ResizeObserver(fitOpeningFold);
		foldObserver.observe(trustStrip);
	}
	const heroSection = heroCopy && heroCopy.closest('.wtg-hero');
	if(heroSection && 'IntersectionObserver' in window){
	  const heroObserver = new IntersectionObserver(entries=>{
		heroInView = entries.some(entry=>entry.isIntersecting);
		startSlider();
	  },{threshold:.05});
	  heroObserver.observe(heroSection);
	}
	if(reducedMotion.addEventListener) reducedMotion.addEventListener('change',startSlider);
  startSlider();

  window.awdLandingMotion = {
    pause(){ motionPaused = true; clearInterval(timer); },
    resume(){ if(!motionPaused) return; motionPaused = false; startSlider(); }
  };
  window.addEventListener('awd-lm-motion-pause',()=>window.awdLandingMotion.pause());
  window.addEventListener('awd-lm-motion-resume',()=>window.awdLandingMotion.resume());

  /* Scroll reveal */
  document.querySelectorAll('.wtg-bullets').forEach(group=>{
    group.querySelectorAll(':scope > .wtg-bullet').forEach((card,index)=>{
      card.setAttribute('data-wtg-reveal','bullet');
      card.style.setProperty('--wtg-bullet-delay',`${Math.min(index,5) * 110}ms`);
    });
  });
  const revealItems = document.querySelectorAll('[data-wtg-reveal]');
  if('IntersectionObserver' in window){
    const observer = new IntersectionObserver(entries=>{
      entries.forEach(entry=>{
        if(entry.isIntersecting){
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    },{threshold:.12});
    revealItems.forEach(el=>observer.observe(el));
  } else {
    revealItems.forEach(el=>el.classList.add('is-visible'));
  }

  /* Before/after cards on the Restaurants Ordering landing. Hover handles
     pointer devices; tap and keyboard keep the same interaction accessible. */
  document.querySelectorAll('.wtg-problem-flip-card').forEach(card=>{
	const toggle = () => {
	  const flipped = card.classList.toggle('is-flipped');
	  card.setAttribute('aria-pressed',flipped ? 'true' : 'false');
	};
	card.addEventListener('click',event=>{
	  if(event.target.closest('[contenteditable="true"]')) return;
	  toggle();
	});
	card.addEventListener('keydown',event=>{
	  if(event.target.closest('[contenteditable="true"]')) return;
	  if(event.key==='Enter' || event.key===' '){ event.preventDefault(); toggle(); }
	});
  });

  /* FAQ */
  document.querySelectorAll('.wtg-faq-q').forEach(btn=>{
    btn.addEventListener('click',event=>{
      if(event.target.closest('[contenteditable="true"]')) return;
      btn.closest('.wtg-faq-item').classList.toggle('is-open');
    });
  });

  /* Contact form feedback. AJAX keeps the visitor in place; the URL status
     remains as a no-JavaScript fallback for older browsers. */
	/* Turnstile's flexible widget needs roughly 300px. Render Cloudflare's
	   supported compact variant when the actual form column is narrower so the
	   challenge never protrudes past the fields on small phones. */
	const turnstileElements = document.querySelectorAll('.cf-turnstile[data-sitekey]');
	if(turnstileElements.length){
	  let turnstileAttempts=0;
	  const renderTurnstile = () => {
		if(!window.turnstile || typeof window.turnstile.render!=='function'){
		  if(turnstileAttempts++ < 40) window.setTimeout(renderTurnstile,250);
		  return;
		}
		turnstileElements.forEach(element=>{
		  if(element.dataset.wtgRendered==='1') return;
		  const wrap=element.closest('.wtg-turnstile-wrap');
		  const compact=!!wrap && wrap.clientWidth < 300;
		  if(wrap) wrap.classList.toggle('is-compact',compact);
		  window.turnstile.render(element,{
			sitekey:element.dataset.sitekey,
			action:element.dataset.action || 'demo_form',
			theme:element.dataset.theme || 'light',
			size:compact ? 'compact' : 'flexible'
		  });
		  element.dataset.wtgRendered='1';
		});
	  };
	  renderTurnstile();
	}
  const demoForm = document.getElementById('wtgDemoForm');
  const demoMessage = document.getElementById('wtgFormMessage');
  if(demoForm && demoMessage){
	const submitButton = demoForm.querySelector('button[type="submit"]');
	const systemSelect = demoForm.querySelector('#wtgSystem');
	const systemOtherField = demoForm.querySelector('#wtgSystemOtherField');
	const systemOtherInput = demoForm.querySelector('#wtgSystemOther');
	const phoneCountry = demoForm.querySelector('#wtgPhoneCountry');
	const phoneNational = demoForm.querySelector('#wtgPhoneNational');
	const phoneCanonical = demoForm.querySelector('#wtgPhone');
	if(phoneCountry && phoneNational && phoneCanonical && window.libphonenumber){
	  const phoneApi = window.libphonenumber;
	  const priorityCountries = ['CH','LU','DE','FR','AT','IT'];
	  const language = (document.documentElement.lang || 'de').split('-')[0];
	  let regionNames = null;
	  try{ regionNames = new Intl.DisplayNames([language],{type:'region'}); }catch(error){}
	  const flag = country => country.replace(/./g,char=>String.fromCodePoint(127397 + char.charCodeAt(0)));
	  const countries = phoneApi.getCountries().map(country=>({
		country,
		callingCode:phoneApi.getCountryCallingCode(country),
		name:regionNames ? regionNames.of(country) : country
	  }));
	  countries.sort((a,b)=>{
		const ai=priorityCountries.indexOf(a.country), bi=priorityCountries.indexOf(b.country);
		if(ai!==-1 || bi!==-1) return (ai===-1 ? 999 : ai) - (bi===-1 ? 999 : bi);
		return a.name.localeCompare(b.name,language);
	  });
	  phoneCountry.replaceChildren(...countries.map(item=>{
		const option=document.createElement('option');
		option.value=item.country;
		option.textContent=`${flag(item.country)} +${item.callingCode}`;
		option.title=`${item.name} (+${item.callingCode})`;
		option.selected=item.country==='CH';
		return option;
	  }));
	  const normalisePhone = () => {
		const raw=phoneNational.value.trim();
		phoneNational.setCustomValidity('');
		phoneNational.removeAttribute('aria-invalid');
		phoneCanonical.value='';
		if(!raw) return true;
		let parsed;
		try{ parsed=phoneApi.parsePhoneNumberFromString(raw,phoneCountry.value); }catch(error){}
		if(!parsed || !parsed.isValid()){
		  phoneNational.setCustomValidity(phoneNational.dataset.invalidPhone || 'Invalid phone number.');
		  phoneNational.setAttribute('aria-invalid','true');
		  return false;
		}
		phoneCanonical.value=parsed.number;
		return true;
	  };
	  phoneNational.addEventListener('input',normalisePhone);
	  phoneCountry.addEventListener('change',normalisePhone);
	  phoneNational.addEventListener('blur',()=>{
		if(normalisePhone() && phoneCanonical.value){
		  const parsed=phoneApi.parsePhoneNumberFromString(phoneCanonical.value);
		  if(parsed) phoneNational.value=parsed.formatNational();
		}
	  });
	  demoForm.addEventListener('submit',normalisePhone,{capture:true});
	}
	if(systemSelect && systemOtherField && systemOtherInput){
	  const toggleSystemOther = () => {
		const showOther = systemSelect.value === 'other';
		systemOtherField.hidden = !showOther;
		systemOtherInput.disabled = !showOther;
		systemOtherInput.required = showOther;
		if(!showOther) systemOtherInput.value = '';
	  };
	  systemSelect.addEventListener('change', toggleSystemOther);
	  toggleSystemOther();
	}
    const formStatus = new URLSearchParams(window.location.search).get('awd_form');
	const messageKey = formStatus === 'success' ? 'success' : (formStatus === 'invalid' ? 'invalid' : (formStatus === 'honeypot' ? 'honeypot' : (formStatus === 'phone' ? 'phone' : (formStatus === 'failed' ? 'failed' : (formStatus === 'rate' ? 'rate' : (formStatus === 'captcha' ? 'captcha' : (formStatus === 'privacy' ? 'privacy' : '')))))));
    if(messageKey){
      demoMessage.textContent = demoMessage.dataset[messageKey] || '';
      demoMessage.style.display = 'block';
      demoMessage.classList.toggle('is-error', messageKey !== 'success');
      if(window.history && window.history.replaceState){
        const cleanUrl = new URL(window.location.href);
        cleanUrl.searchParams.delete('awd_form');
        window.history.replaceState({}, '', cleanUrl.pathname + cleanUrl.search + cleanUrl.hash);
      }
    }
	demoForm.addEventListener('submit',async event=>{
		event.preventDefault();
		if(!demoForm.reportValidity()) return;
		const button = event.submitter || submitButton;
		if(button) button.disabled = true;
		demoMessage.style.display = 'none';
		demoMessage.classList.remove('is-error');
		try{
		  const body = new FormData(demoForm);
		  body.set('awd_ajax','1');
		  const endpoint = demoForm.getAttribute('action');
		  if(!endpoint) throw new Error('The form endpoint is missing.');
		  const response = await fetch(endpoint,{method:'POST',body,credentials:'same-origin',headers:{'X-Requested-With':'XMLHttpRequest'}});
		  const result = await response.json();
		  const status = result && result.status ? result.status : 'failed';
		  if(window.awdLmAnalytics && status !== 'success') window.awdLmAnalytics.track('form_validation_error',{section_id:'demo',element_id:'wtg-demo-form',metadata:{status}});
		  const detailedErrors = status === 'invalid' && Array.isArray(result && result.errors)
			? result.errors.filter(error => typeof error === 'string' && error.trim() !== '')
			: [];
		  demoMessage.textContent = detailedErrors.length
			? detailedErrors.join(' ')
			: (demoMessage.dataset[status] || demoMessage.dataset.failed || '');
		  demoMessage.classList.toggle('is-error',status !== 'success');
		  demoMessage.style.display = 'block';
		  if(status !== 'success' && window.turnstile && typeof window.turnstile.reset === 'function') window.turnstile.reset();
		}catch(error){
		  if(window.awdLmAnalytics) window.awdLmAnalytics.track('form_validation_error',{section_id:'demo',element_id:'wtg-demo-form',metadata:{status:'network'}});
		  demoMessage.textContent = demoMessage.dataset.failed || '';
		  demoMessage.classList.add('is-error');
		  demoMessage.style.display = 'block';
		  if(window.turnstile && typeof window.turnstile.reset === 'function') window.turnstile.reset();
		}finally{
		  if(button) button.disabled = false;
		}
	});
  }

})();

/*
 * Tall cards in the Restaurants Ordering stack must scroll completely before
 * the following card covers them. Their sticky offset therefore depends on
 * the rendered height: short cards stop below the header; tall cards stop only
 * after their bottom edge has entered the viewport.
 */
(function () {
	'use strict';
	if (!document.body.classList.contains('awd-landing--second-landing')) { return; }
	if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }

	var sections = Array.prototype.slice.call(document.querySelectorAll('main > .wtg-section:not(#faq):not(#demo)'));
	if (!sections.length) { return; }
	var scheduled = false;

	function measureStack() {
		scheduled = false;
		var nav = document.querySelector('.wtg-nav');
		var headerHeight = nav ? Math.round(nav.getBoundingClientRect().height) : (window.innerWidth <= 767 ? 74 : 104);
		var viewportHeight = window.innerHeight || document.documentElement.clientHeight;

		sections.forEach(function (section) {
			var sectionHeight = Math.ceil(section.getBoundingClientRect().height);
			var top = Math.min(headerHeight, viewportHeight - sectionHeight);
			section.style.setProperty('--awd-stack-top', Math.round(top) + 'px');
		});
	}

	function scheduleMeasure() {
		if (scheduled) { return; }
		scheduled = true;
		window.requestAnimationFrame(measureStack);
	}

	window.addEventListener('resize', scheduleMeasure, { passive: true });
	window.addEventListener('orientationchange', scheduleMeasure, { passive: true });
	if ('ResizeObserver' in window) {
		var observer = new ResizeObserver(scheduleMeasure);
		sections.forEach(function (section) { observer.observe(section); });
		var stackNav = document.querySelector('.wtg-nav');
		if (stackNav) { observer.observe(stackNav); }
	}
	if (document.fonts && document.fonts.ready) { document.fonts.ready.then(scheduleMeasure); }
	measureStack();
}());
