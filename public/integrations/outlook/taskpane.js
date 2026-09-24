(() => {
  const state = { csrf: '', session: null, current: null, checkId: null };
  const view = (name) => document.querySelector(`[data-view="${name}"]`);
  const show = (name) => document.querySelectorAll('[data-view]').forEach((element) => { element.hidden = element.dataset.view !== name; });
  const json = async (url, options = {}) => {
    const response = await fetch(url, { credentials: 'same-origin', headers: { 'Content-Type': 'application/json', ...(options.headers || {}) }, ...options });
    const payload = await response.json();
    if (!response.ok) throw new Error(payload.error || 'Er ging iets mis.');
    return payload;
  };
  const readItem = () => new Promise((resolve, reject) => {
    const item = Office.context.mailbox.item;
    item.body.getAsync(Office.CoercionType.Text, (result) => {
      if (result.status !== Office.AsyncResultStatus.Succeeded) return reject(new Error('De inhoud van deze e-mail kon niet worden gelezen.'));
      resolve({ subject: item.subject || '', sender_email: item.from?.emailAddress || '', body: result.value || '' });
    });
  });
  const loadSession = async () => {
    try {
      const payload = await json('/api/v1/business/session', { headers: {} });
      state.session = payload.user;
      state.csrf = payload.csrf || '';
      if (!state.session) return show('login');
      state.current = await readItem();
      document.querySelector('[data-subject]').textContent = state.current.subject || 'Huidige e-mail';
      document.querySelector('[data-sender]').textContent = state.current.sender_email || 'Afzender onbekend';
      show('ready');
    } catch (error) {
      document.querySelector('[data-login-error]').textContent = error.message;
      show('login');
    }
  };
  document.querySelector('[data-login-form]').addEventListener('submit', async (event) => {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    try {
      const payload = await json('/api/v1/business/login', { method: 'POST', body: JSON.stringify({ email: form.get('email'), password: form.get('password') }) });
      state.session = payload.user;
      state.csrf = payload.csrf || '';
      state.current = await readItem();
      show('ready');
    } catch (error) {
      document.querySelector('[data-login-error]').textContent = error.message;
    }
  });
  document.querySelector('[data-check]').addEventListener('click', async (event) => {
    event.currentTarget.disabled = true;
    try {
      const payload = await json('/api/v1/business/check', { method: 'POST', headers: { 'X-CSRF-Token': state.csrf }, body: JSON.stringify({ input_type: 'email', ...state.current }) });
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
      document.querySelector('[data-result-note]').textContent = error.message;
    } finally { event.currentTarget.disabled = false; }
  });
  document.querySelector('[data-report]').addEventListener('click', async (event) => {
    event.currentTarget.disabled = true;
    try {
      await json('/api/v1/business/report', { method: 'POST', headers: { 'X-CSRF-Token': state.csrf }, body: JSON.stringify({ check_id: state.checkId, description: 'Gemeld vanuit Outlook.' }) });
      document.querySelector('[data-result-note]').textContent = 'De melding is naar je organisatie gestuurd.';
    } catch (error) { document.querySelector('[data-result-note]').textContent = error.message; }
  });
  document.querySelectorAll('[data-feedback]').forEach((button) => button.addEventListener('click', async (event) => {
    event.currentTarget.disabled = true;
    try {
      await json('/api/v1/business/feedback', { method: 'POST', headers: { 'X-CSRF-Token': state.csrf }, body: JSON.stringify({ check_id: state.checkId, feedback: event.currentTarget.dataset.feedback }) });
      document.querySelector('[data-result-note]').textContent = 'Bedankt voor je feedback.';
    } catch (error) { document.querySelector('[data-result-note]').textContent = error.message; }
  }));
  document.querySelector('[data-reset]').addEventListener('click', () => { show('ready'); });
  const escapeHtml = (value) => String(value).replace(/[&<>'"]/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character]));
  if (window.Office) Office.onReady(loadSession); else loadSession();
})();
