(() => {
  const config = window.scamSpotterConfig || {};
  const loadAnalytics = () => {
    if (window.__scamspotterAnalyticsLoaded) return;
    window.__scamspotterAnalyticsLoaded = true;
    if (config.gtmId) {
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' });
      const script = document.createElement('script');
      script.async = true;
      script.src = `https://www.googletagmanager.com/gtm.js?id=${encodeURIComponent(config.gtmId)}`;
      document.head.appendChild(script);
    } else if (config.ga4Id) {
      const script = document.createElement('script');
      script.async = true;
      script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(config.ga4Id)}`;
      document.head.appendChild(script);
      window.dataLayer = window.dataLayer || [];
      window.gtag = (...args) => window.dataLayer.push(args);
      window.gtag('js', new Date());
      window.gtag('config', config.ga4Id, { anonymize_ip: true });
    }
  };
  window.scamSpotterTrack = (eventName, parameters = {}) => {
    window.dispatchEvent(new CustomEvent('scamspotter:analytics', { detail: { eventName, parameters } }));
    if (window.dataLayer) window.dataLayer.push({ event: eventName, ...parameters });
    if (window.gtag) window.gtag('event', eventName, parameters);
  };
  document.querySelectorAll('[data-analytics-event]').forEach((element) => element.addEventListener('click', () => window.scamSpotterTrack(element.dataset.analyticsEvent)));

  const menu = document.querySelector('.menu-toggle');
  const nav = document.querySelector('#site-nav');
  if (menu && nav) {
    menu.addEventListener('click', () => {
      const open = nav.classList.toggle('is-open');
      menu.setAttribute('aria-expanded', String(open));
    });
  }

  const checker = document.querySelector('[data-checker]');
  if (checker) {
    const attributionForm = checker.querySelector('[data-preserve-attribution]');
    if (attributionForm) {
      const existing = new Set([...attributionForm.elements].map((element) => element.name));
      new URLSearchParams(window.location.search).forEach((value, key) => {
        if ((key.startsWith('utm_') || key === 'gclid' || key === 'msclkid') && !existing.has(key)) {
          const field = document.createElement('input');
          field.type = 'hidden';
          field.name = key;
          field.value = value.slice(0, 250);
          attributionForm.appendChild(field);
        }
      });
    }
    const tabs = checker.querySelectorAll('[data-check-type]');
    const typeInput = checker.querySelector('[name="input_type"]');
    const input = checker.querySelector('[name="input"]');
    const label = checker.querySelector('[data-input-label]');
    const help = checker.querySelector('[data-input-help]');
    const presets = {
      message: { label: 'Plak hier de tekst of link', placeholder: 'Bijvoorbeeld: “Uw pakket kon niet worden bezorgd. Betaal €1,99 om opnieuw te plannen…”', help: 'Verwijder persoonsgegevens als je die niet nodig hebt voor de check.' },
      url: { label: 'Vul de volledige website-link in', placeholder: 'https://verdacht-voorbeeld.nl/…', help: 'Open de link liever niet voordat je hem controleert.' },
      email: { label: 'Vul het e-mailadres in', placeholder: 'naam@voorbeeld.nl', help: 'Controleer ook de naam en het domein van de afzender.' },
      phone: { label: 'Vul het telefoonnummer in', placeholder: '+31 6 12 34 56 78', help: 'Beschrijf in het berichtveld eventueel wat de beller vroeg.' }
    };
    const select = (type) => {
      const preset = presets[type] || presets.message;
      if (typeInput) typeInput.value = type;
      tabs.forEach((tab) => tab.setAttribute('aria-selected', String(tab.dataset.checkType === type)));
      if (label) label.textContent = preset.label;
      if (input) input.placeholder = preset.placeholder;
      if (help) help.textContent = preset.help;
    };
    tabs.forEach((tab) => tab.addEventListener('click', () => {
      if (tab.disabled || tab.classList.contains('is-disabled')) return;
      select(tab.dataset.checkType);
    }));
    select(typeInput?.value || 'message');
  }

  const notice = document.querySelector('[data-cookie-notice]');
  const consent = localStorage.getItem('scamspotter-consent');
  if (consent === 'accept') loadAnalytics();
  if (notice && !consent) notice.hidden = false;
  notice?.querySelectorAll('[data-consent]').forEach((button) => button.addEventListener('click', () => {
    const choice = button.dataset.consent || 'decline';
    localStorage.setItem('scamspotter-consent', choice);
    if (choice === 'accept') loadAnalytics();
    notice.hidden = true;
  }));

  const reportForm = document.querySelector('[data-business-report]');
  if (reportForm) {
    reportForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const status = reportForm.querySelector('[data-business-report-status]');
      const data = new FormData(reportForm);
      try {
        const response = await fetch(reportForm.action, {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': data.get('_csrf') || '' },
          body: JSON.stringify({ check_id: Number(data.get('check_id')), description: data.get('description') || '' })
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.error || 'Melden mislukt.');
        if (status) status.textContent = 'De melding is ontvangen door je organisatie.';
        reportForm.querySelector('button')?.setAttribute('disabled', 'disabled');
      } catch (error) {
        if (status) status.textContent = error.message || 'Melden mislukt.';
      }
    });
  }
})();
