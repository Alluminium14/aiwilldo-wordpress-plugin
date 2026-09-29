document.addEventListener('DOMContentLoaded', function () {
  var tabs = document.querySelectorAll('.awd-lm-tab');
  function switchLanguage(lang) {
    tabs.forEach(function (tab) { tab.classList.toggle('is-active', tab.dataset.lang === lang); });
    document.querySelectorAll('.awd-lm-lang').forEach(function (panel) { panel.hidden = panel.dataset.langPanel !== lang; });
    document.querySelectorAll('.awd-lm-preview').forEach(function (link) { link.classList.toggle('is-hidden', link.dataset.previewLang !== lang); });
  }
  tabs.forEach(function (tab) { tab.addEventListener('click', function () { switchLanguage(tab.dataset.lang); }); });
  function completeness() {
    ['de', 'en', 'fr'].forEach(function (lang) {
      var fields = document.querySelectorAll('[data-lang-panel="' + lang + '"] [data-required-content]');
      var complete = Array.prototype.filter.call(fields, function (field) { return field.value.trim() !== ''; }).length;
      var target = document.querySelector('[data-completeness="' + lang + '"]');
      if (target) target.textContent = complete === fields.length ? '✓' : complete + '/' + fields.length;
    });
  }
  document.addEventListener('input', completeness); completeness(); switchLanguage('de');
  document.querySelectorAll('.awd-lm-select-image').forEach(function (button) {
    button.addEventListener('click', function () {
      var control = button.closest('.awd-lm-image');
      var frame = wp.media({ title: 'Select landing image', button: { text: 'Use image' }, multiple: false });
      frame.on('select', function () { var item = frame.state().get('selection').first().toJSON(); control.querySelector('input').value = item.id; var image = control.querySelector('img'); image.src = item.sizes && item.sizes.medium ? item.sizes.medium.url : item.url; image.hidden = false; });
      frame.open();
    });
  });
  document.querySelectorAll('.awd-lm-remove-image').forEach(function (button) { button.addEventListener('click', function () { var control = button.closest('.awd-lm-image'); control.querySelector('input').value = '0'; control.querySelector('img').hidden = true; }); });
});

