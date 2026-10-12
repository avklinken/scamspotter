(() => {
  const state = { csrf: '', session: null, current: null, checkId: null, checkInFlight: false, ssoEnabled: false, ssoBusy: false };
  const view = (name) => document.querySelector(`[data-view="${name}"]`);
  const show = (name) => document.querySelectorAll('[data-view]').forEach((element) => { element.hidden = element.dataset.view !== name; });
  const json = async (url, options = {}) => {
    const response = await fetch(url, { credentials: 'same-origin', headers: { 'Content-Type': 'application/json', ...(options.headers || {}) }, ...options });
    const payload = await response.json();
    if (!response.ok) {
      const error = new Error(payload.message || payload.error || 'Er ging iets mis.');
      error.status = response.status;
      throw error;
    }
    return payload;
  };
  const readItem = () => new Promise((resolve, reject) => {
    const item = Office.context.mailbox.item;
    item.body.getAsync(Office.CoercionType.Text, (result) => {
      if (result.status !== Office.AsyncResultStatus.Succeeded) return reject(new Error('De inhoud van deze e-mail kon niet worden gelezen.'));
      resolve({ subject: item.subject || '', sender_email: item.from?.emailAddress || '', body: result.value || '' });
    });
  });
  const prepareReady = async () => {
    state.current = await readItem();
    document.querySelector('[data-subject]').textContent = state.current.subject || 'Huidige e-mail';
    document.querySelector('[data-sender]').textContent = state.current.sender_email || 'Afzender onbekend';
    show('ready');
  };
  const setLoginMode = () => {
    document.querySelector('[data-login-title]').textContent = state.ssoEnabled ? 'Microsoft 365-aanmelding' : 'Inloggen';
    document.querySelector('[data-sso-message]').hidden = !state.ssoEnabled;
    document.querySelector('[data-sso-login]').hidden = !state.ssoEnabled;
    document.querySelector('[data-login-form]').hidden = state.ssoEnabled;
  };
  const officeAccessToken = () => new Promise((resolve, reject) => {
    if (window.Office?.auth?.getAccessToken) {
      window.Office.auth.getAccessToken({ allowSignInPrompt: true, allowConsentPrompt: true }).then(resolve).catch(reject);
      return;
    }
    if (window.Office?.context?.auth?.getAccessTokenAsync) {
      window.Office.context.auth.getAccessTokenAsync({ allowSignInPrompt: true, allowConsentPrompt: true }, (result) => {
        if (result.status === window.Office.AsyncResultStatus.Succeeded) resolve(result.value);
        else reject(new Error('Microsoft 365-aanmelding is niet beschikbaar in deze Outlook-versie.'));
      });
      return;
    }
    reject(new Error('Microsoft 365-aanmelding is niet beschikbaar in deze Outlook-versie.'));
  });
  const microsoftSso = async () => {
    const token = await officeAccessToken();
    const payload = await json('/api/v1/business/sso/microsoft', { method: 'POST', headers: { Authorization: `Bearer ${token}` } });
    state.session = payload.user;
    state.csrf = payload.csrf || '';
    return payload;
  };
  const authenticatedJson = async (url, options = {}) => {
    try {
      return await json(url, options);
    } catch (error) {
      if (error.status === 401 && state.ssoEnabled) {
        await microsoftSso();
        return json(url, options);
      }
      throw error;
    }
  };
  const loadSession = async () => {
    try {
      const payload = await json('/api/v1/business/session', { headers: {} });
      state.session = payload.user;
      state.csrf = payload.csrf || '';
      state.ssoEnabled = Boolean(payload.sso_enabled);
      setLoginMode();
      if (!state.session && state.ssoEnabled) await microsoftSso();
      if (!state.session) return show('login');
      await prepareReady();
    } catch (error) {
      document.querySelector('[data-login-error]').textContent = error.message;
      setLoginMode();
      show('login');
    }
  };
  document.querySelector('[data-sso-login]').addEventListener('click', async (event) => {
    if (state.ssoBusy) return;
    state.ssoBusy = true;
    event.currentTarget.disabled = true;
    document.querySelector('[data-login-error]').textContent = '';
    try {
      await microsoftSso();
      await prepareReady();
    } catch (error) {
      document.querySelector('[data-login-error]').textContent = error.message;
    } finally {
      state.ssoBusy = false;
      event.currentTarget.disabled = false;
    }
  });
  document.querySelector('[data-login-form]').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      const payload = await json('/api/v1/business/login', { method: 'POST', body: JSON.stringify({ email: form.get('email'), password: form.get('password') }) });
      state.session = payload.user;
      state.csrf = payload.csrf || '';
      await prepareReady();
    } catch (error) {
      document.querySelector('[data-login-error]').textContent = error.message;
    }
  });
  const checkButton = document.querySelector('[data-check]');
  const checkStatus = document.querySelector('[data-check-status]');
  const checkStatusText = document.querySelector('[data-check-status-text]');
  const setChecking = (checking) => {
    checkButton.disabled = checking;
    checkButton.classList.toggle('is-loading', checking);
    checkButton.setAttribute('aria-busy', checking ? 'true' : 'false');
    document.querySelector('[data-check-label]').hidden = checking;
    document.querySelector('[data-check-loading]').hidden = !checking;
    if (checking) {
      checkStatus.hidden = false;
      checkStatus.classList.remove('is-error');
      checkStatusText.textContent = 'We analyseren deze e-mail. Dit kan even duren.';
    }
  };
  checkButton.addEventListener('click', async () => {
    if (state.checkInFlight || !state.current) return;
    state.checkInFlight = true;
    setChecking(true);
    try {
      const payload = await authenticatedJson('/api/v1/business/check', { method: 'POST', headers: { 'X-CSRF-Token': state.csrf }, body: JSON.stringify({ input_type: 'email', ...state.current }) });
      const result = payload.result || {};
      state.checkId = result.check_id;
      document.querySelector('[data-result-title]').textContent = result.status?.label || 'Resultaat';
      document.querySelector('[data-result-explanation]').textContent = result.status?.explanation || '';
      const match = result.top_match;
      const box = document.querySelector('[data-match]');
      if (match) {
        box.hidden = false;
        document.querySelector('[data-match-name]').textContent = match.variant?.name || 'Bekende scam';
        document.querySelector('[data-match-type]').textContent = `${match.variant?.type_name || ''} · ${match.variant?.family_name || ''}`;
        document.querySelector('[data-signals]').innerHTML = (match.indicators || []).slice(0, 5).map((item) => `<li>${escapeHtml(item.value || '')}</li>`).join('');
      } else box.hidden = true;
      const organizationSignals = result.organization_signals || [];
      const organizationBox = document.querySelector('[data-org-match]');
      organizationBox.hidden = organizationSignals.length === 0;
      document.querySelector('[data-org-signals]').innerHTML = organizationSignals.slice(0, 5).map((item) => `<li>${escapeHtml(item.label || item.value || '')}</li>`).join('');
      show('result');
    } catch (error) {
      checkStatus.hidden = false;
      checkStatus.classList.add('is-error');
      checkStatusText.textContent = `Controle mislukt: ${error.message}`;
    } finally {
      state.checkInFlight = false;
      setChecking(false);
    }
  });
  document.querySelector('[data-report]').addEventListener('click', async (event) => {
    event.currentTarget.disabled = true;
    try {
      await authenticatedJson('/api/v1/business/report', { method: 'POST', headers: { 'X-CSRF-Token': state.csrf }, body: JSON.stringify({ check_id: state.checkId, description: 'Gemeld vanuit Outlook.' }) });
      document.querySelector('[data-result-note]').textContent = 'De melding is naar je organisatie gestuurd.';
    } catch (error) { document.querySelector('[data-result-note]').textContent = error.message; }
  });
  document.querySelectorAll('[data-feedback]').forEach((button) => button.addEventListener('click', async (event) => {
    event.currentTarget.disabled = true;
    try {
      await authenticatedJson('/api/v1/business/feedback', { method: 'POST', headers: { 'X-CSRF-Token': state.csrf }, body: JSON.stringify({ check_id: state.checkId, feedback: event.currentTarget.dataset.feedback }) });
      document.querySelector('[data-result-note]').textContent = 'Bedankt voor je feedback.';
    } catch (error) { document.querySelector('[data-result-note]').textContent = error.message; }
  }));
  document.querySelector('[data-reset]').addEventListener('click', () => { checkStatus.hidden = true; show('ready'); });
  const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
  if (window.Office) Office.onReady(loadSession); else loadSession();
})();
