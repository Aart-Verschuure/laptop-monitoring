const $ = (id) => document.getElementById(id);

chrome.storage.local.get(['serverUrl', 'token']).then(({ serverUrl, token }) => {
  $('serverUrl').value = serverUrl || 'http://localhost:8000';
  $('token').value = token || '';
});

chrome.extension.isAllowedIncognitoAccess().then((allowed) => {
  if (!allowed) {
    $('status').innerHTML = '⚠️ Incognito is nog <strong>niet</strong> toegestaan. Zie de uitleg hieronder.';
  }
});

$('save').addEventListener('click', async () => {
  const serverUrl = $('serverUrl').value.trim().replace(/\/+$/, '');
  const token = $('token').value.trim();
  await chrome.storage.local.set({ serverUrl, token });

  $('status').textContent = 'Testen…';
  try {
    const res = await fetch(serverUrl + '/api/report', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', Authorization: 'Bearer ' + token },
      body: JSON.stringify({}),
    });
    if (res.ok) {
      const s = await chrome.runtime.sendMessage('flush');
      $('status').textContent = '✅ Verbonden. Nog te versturen: ' + (s?.pending ?? 0);
    } else if (res.status === 401) {
      $('status').textContent = '❌ Het token is ongeldig.';
    } else {
      $('status').textContent = '❌ Server antwoordde met HTTP ' + res.status;
    }
  } catch (e) {
    $('status').textContent = '❌ Server niet bereikbaar. Draait php artisan serve?';
  }
});
