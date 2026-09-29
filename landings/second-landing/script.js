(function () {
	'use strict';
	var landing = document.querySelector('.awd-landing--second-landing');
	if (!landing) {
		return;
	}
	landing.classList.add('is-ready');

	var pageLanguage = (document.documentElement.lang || 'en').toLowerCase().split('-')[0];
	var goTopLabels = {
		de: 'Nach oben',
		en: 'Go to top',
		fr: 'Retour en haut'
	};
	var goTopLabel = goTopLabels[pageLanguage] || goTopLabels.en;
	var goTop = document.createElement('a');
	goTop.className = 'wtg-go-top';
	goTop.href = '#start';
	goTop.setAttribute('aria-label', goTopLabel);
	goTop.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 19V5M6 11l6-6 6 6"/></svg><span>' + goTopLabel + '</span>';
	landing.appendChild(goTop);
	function updateGoTopVisibility() {
		goTop.classList.toggle('is-visible', window.scrollY > 520);
	}
	window.addEventListener('scroll', updateGoTopVisibility, { passive: true });
	updateGoTopVisibility();

	var assistantSection = landing.querySelector('.wtg-ai-assistant');
	var assistantMessages = assistantSection ? assistantSection.querySelector('.wtg-bullets') : null;
	if (!assistantMessages) {
		return;
	}

	assistantMessages.classList.add('wtg-assistant-chat');
	var messages = Array.prototype.slice.call(assistantMessages.querySelectorAll(':scope > .wtg-bullet'));
	var avatar = assistantMessages.querySelector('.wtg-assistant-avatar');
	var demoPrompt = avatar && avatar.getAttribute('data-chat-prompt') ? avatar.getAttribute('data-chat-prompt') : 'Want to see me in action?';
	messages.forEach(function (message) {
		message.classList.add('wtg-assistant-message');
		message.setAttribute('aria-hidden', 'true');
	});

	var typing = document.createElement('div');
	typing.className = 'wtg-assistant-typing';
	typing.setAttribute('aria-label', 'Assistant is typing');
	typing.innerHTML = '<span></span><span></span><span></span>';
	typing.hidden = true;
	assistantMessages.appendChild(typing);

	var composer = document.createElement('form');
	composer.className = 'wtg-assistant-composer';
	composer.action = '#demo';
	composer.innerHTML = '<input type="text" value="" aria-label="Chat message"><button type="button" aria-label="Send and open the contact form"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg></button>';
	assistantMessages.appendChild(composer);
	composer.classList.add('is-shown');
	var composerInput = composer.querySelector('input');
	function openContactForm(event) {
		event.preventDefault();
		var formElement = document.getElementById('wtgDemoForm');
		var contactForm = formElement ? formElement.closest('section') : document.getElementById('demo');
		if (contactForm) {
			contactForm.scrollIntoView({ behavior: 'auto', block: 'start' });
			window.history.replaceState(null, '', '#demo');
		}
	}
	composer.addEventListener('submit', openContactForm);
	composer.querySelector('button').addEventListener('click', openContactForm);

	var hasPlayed = false;
	var reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
	function revealConversation() {
		if (hasPlayed) {
			return;
		}
		hasPlayed = true;
		if (reducedMotion) {
			messages.forEach(function (message) {
				message.classList.add('is-shown');
				message.removeAttribute('aria-hidden');
			});
			composerInput.value = demoPrompt;
			if (avatar) { avatar.classList.add('is-shown'); }
			return;
		}

		var index = 0;
		function typeDemoPrompt() {
			var prompt = demoPrompt;
			var character = 0;
			var promptTimer = window.setInterval(function () {
				character += 1;
				composerInput.value = prompt.slice(0, character);
				if (character >= prompt.length) { window.clearInterval(promptTimer); }
			}, 55);
		}
		function showNext() {
			messages[index].classList.add('is-shown');
			messages[index].removeAttribute('aria-hidden');
			if (avatar) {
				avatar.classList.remove('is-shown');
				void avatar.offsetWidth;
				avatar.classList.add('is-shown');
			}
			index += 1;
			if (index >= messages.length) {
				window.setTimeout(typeDemoPrompt, 450);
				return;
			}
			window.setTimeout(function () {
				typing.hidden = false;
				window.setTimeout(function () {
					typing.hidden = true;
					showNext();
				}, 1500);
			}, 600);
		}
		showNext();
	}

	if ('IntersectionObserver' in window) {
		var observer = new IntersectionObserver(function (entries) {
			if (entries.some(function (entry) { return entry.isIntersecting; })) {
				revealConversation();
				observer.disconnect();
			}
		}, { threshold: 0.25 });
		observer.observe(assistantMessages);
	} else {
		revealConversation();
	}
}());
