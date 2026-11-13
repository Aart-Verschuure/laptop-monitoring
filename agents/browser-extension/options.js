const $ = (id) => document.getElementById(id);

chrome.storage.local.get(['serverUrl', 'token', 'syncDevices', 'syncToken']).then((s) => {
  $('serverUrl').value = s.serverUrl || 'http://localhost:8000';
  $('token').value = s.token || '';
  $('syncDevices').checked = !!s.syncDevices;
  $('syncToken').value = s.syncToken || '';
});

if (!chrome.sessions?.getDevices) {
  $('syncSection').hidden = true;
  $('syncUnsupported').hidden = false;
}

chrome.extension.isAllowedIncognitoAccess().then((allowed) => {
  if (!allowed) {
    $('status').innerHTML = '⚠️ Incognito is nog <strong>niet</strong> toegestaan. Zie de uitleg onderaan.';
  }
});

async function test(serverUrl, token, label) {
  try {
    const res = await fetch(serverUrl + '/api/report', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: 'Bearer ' + token },
      body: JSON.stringify({}),
    });
    if (res.ok) return null;
    return res.status === 401 ? `❌ ${label} is ongeldig.` : `❌ Server antwoordde met HTTP ${res.status}`;
  } catch {
    return '❌ Server niet bereikbaar. Draait php artisan serve?';
  }
}

$('save').addEventListener('click', async () => {
  const serverUrl = $('serverUrl').value.trim().replace(/\/+$/, '');
  const token = $('token').value.trim();
  const syncDevices = $('syncDevices').checked;
  const syncToken = $('syncToken').value.trim();
  await chrome.storage.local.set({ serverUrl, token, syncDevices, syncToken });

  $('status').textContent = 'Testen…';
  const error = (await test(serverUrl, token, 'Het laptop-token'))
    || (syncToken ? await test(serverUrl, syncToken, 'Het token voor gesynchroniseerde apparaten') : null);
  if (error) {
    $('status').textContent = error;
    return;
  }

  const s = await chrome.runtime.sendMessage('flush');
  $('status').textContent = s?.lastError
    ? '⚠️ Verbonden, maar versturen mislukte: ' + s.lastError
    : '✅ Verbonden. Nog te versturen: ' + (s?.pending ?? 0);
});

$('import').addEventListener('click', async () => {
  $('import').disabled = true;
  $('importStatus').textContent = 'Bezig met importeren… dit kan even duren.';
  const r = await chrome.runtime.sendMessage({ type: 'importHistory', days: Number($('days').value) });
  $('importStatus').textContent = r?.ok
    ? `✅ ${r.count} bezoeken verstuurd.`
    : '❌ Importeren mislukt: ' + (r?.error || 'onbekende fout');
  $('import').disabled = false;
});
