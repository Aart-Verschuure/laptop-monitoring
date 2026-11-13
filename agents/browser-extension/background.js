// Laptop Monitoring - legt bezochte pagina's vast (normaal en incognito)
// en stuurt ze in batches naar het dashboard.
//
// Daarnaast (optioneel):
//  - tabbladen die openstaan op andere apparaten met hetzelfde Google-account
//    (Chrome-synchronisatie, bv. Chrome op je telefoon);
//  - eenmalig de bestaande browsergeschiedenis importeren.
//
// Werkt in Chrome, Edge, Brave, Opera en Firefox.

const FLUSH_ALARM = 'flush';
const MAX_QUEUE = 5000;
const BATCH = 500;
const lastUrlByTab = new Map();
let lock = Promise.resolve();

function withLock(fn) {
  const run = lock.then(fn, fn);
  lock = run.catch(() => {});
  return run;
}

function browserName() {
  const ua = navigator.userAgent;
  if (ua.includes('Firefox/')) return 'Firefox';
  if (ua.includes('Edg/')) return 'Edge';
  if (ua.includes('OPR/')) return 'Opera';
  if (navigator.brave) return 'Brave';
  return 'Chrome';
}

async function sha1(text) {
  const buf = await crypto.subtle.digest('SHA-1', new TextEncoder().encode(text));
  return [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

async function post(serverUrl, token, visits) {
  return fetch(serverUrl.replace(/\/+$/, '') + '/api/report', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: 'Bearer ' + token,
    },
    body: JSON.stringify({ visits }),
  });
}

// queue: bezoeken op deze computer (laptop-token)
// syncQueue: bezoeken van gesynchroniseerde apparaten (telefoon-token, of anders laptop-token)
async function enqueue(visits, key = 'queue') {
  if (!visits.length) return;
  await withLock(async () => {
    const { [key]: queue = [] } = await chrome.storage.local.get(key);
    queue.push(...visits);
    await chrome.storage.local.set({ [key]: queue.slice(-MAX_QUEUE) });
  });
}

async function flushQueue(key, serverUrl, token) {
  const { [key]: queue = [] } = await chrome.storage.local.get(key);
  if (!queue.length || !token) return;

  const batch = queue.slice(0, BATCH);
  const res = await post(serverUrl, token, batch);
  if (!res.ok) throw new Error('HTTP ' + res.status + (res.status === 401 ? ' (token ongeldig)' : ''));

  const { [key]: current = [] } = await chrome.storage.local.get(key);
  await chrome.storage.local.set({ [key]: current.slice(batch.length) });
}

async function flush() {
  await withLock(async () => {
    const { serverUrl, token, syncToken } = await chrome.storage.local.get(['serverUrl', 'token', 'syncToken']);
    if (!serverUrl || !token) return;

    try {
      await flushQueue('queue', serverUrl, token);
      await flushQueue('syncQueue', serverUrl, syncToken || token);
      await chrome.storage.local.set({ lastSync: new Date().toISOString(), lastError: null });
    } catch (e) {
      await chrome.storage.local.set({ lastError: String(e.message || e) });
    }
  });
}

// ---------- Gesynchroniseerde apparaten (Google-account) ----------

// Chrome geeft via chrome.sessions.getDevices() de tabbladen die openstaan op je
// andere apparaten (zelfde Google-account, synchronisatie van "Geopende tabbladen" aan).
// Elk nieuw tabblad wordt één keer als bezoek doorgestuurd.
async function collectSyncedTabs() {
  if (!chrome.sessions?.getDevices) return; // bv. Firefox: niet beschikbaar

  const { syncDevices, seenSynced = {} } = await chrome.storage.local.get(['syncDevices', 'seenSynced']);
  if (!syncDevices) return;

  let devices;
  try {
    devices = await chrome.sessions.getDevices();
  } catch {
    return;
  }

  const seen = {};
  const visits = [];

  for (const device of devices) {
    for (const session of device.sessions) {
      const tabs = session.window?.tabs ?? (session.tab ? [session.tab] : []);
      for (const tab of tabs) {
        if (!tab.url || !/^https?:/i.test(tab.url)) continue;

        const key = device.deviceName + '|' + tab.url;
        if (seenSynced[key]) {
          seen[key] = seenSynced[key];
          continue;
        }

        const modified = (session.lastModified || Date.now() / 1000) * 1000;
        seen[key] = modified;
        visits.push({
          url: tab.url,
          title: tab.title || null,
          incognito: false,
          browser: 'Chrome · ' + device.deviceName,
          visited_at: new Date(modified).toISOString(),
          source_id: 'sync:' + (await sha1(key + '|' + modified)),
        });
      }
    }
  }

  // Alleen tabbladen die nog openstaan onthouden: wordt dezelfde pagina later
  // opnieuw geopend, dan telt dat als een nieuw bezoek.
  await chrome.storage.local.set({ seenSynced: seen });
  await enqueue(visits, 'syncQueue');
}

// ---------- Bestaande geschiedenis importeren ----------

// Importeert de geschiedenis van de afgelopen `days` dagen tot het moment dat de
// extensie werd geïnstalleerd (daarna wordt alles al live bijgehouden).
// In Firefox bevat de geschiedenis ook bezoeken van je andere apparaten via Firefox Sync.
async function importHistory(days) {
  const { serverUrl, token, installedAt = Date.now() } = await chrome.storage.local.get(['serverUrl', 'token', 'installedAt']);
  if (!serverUrl || !token) throw new Error('Vul eerst server-URL en token in.');

  const startTime = Date.now() - days * 86400000;
  const endTime = installedAt;
  if (startTime >= endTime) return 0;

  const items = await chrome.history.search({ text: '', startTime, endTime, maxResults: 100000 });
  const browser = browserName();
  let batch = [];
  let sent = 0;

  const send = async () => {
    if (!batch.length) return;
    const res = await post(serverUrl, token, batch);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    sent += batch.length;
    batch = [];
  };

  for (const item of items) {
    if (!item.url || !/^https?:/i.test(item.url)) continue;

    const visits = await chrome.history.getVisits({ url: item.url });
    for (const v of visits) {
      if (v.visitTime < startTime || v.visitTime > endTime) continue;
      batch.push({
        url: item.url,
        title: item.title || null,
        incognito: false,
        browser,
        visited_at: new Date(v.visitTime).toISOString(),
        source_id: 'hist:' + browser + ':' + item.id + ':' + v.visitId,
      });
      if (batch.length >= BATCH) await send();
    }
  }
  await send();

  return sent;
}

// ---------- Live bijhouden ----------

chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
  if (changeInfo.status !== 'complete' || !tab.url || !/^https?:/i.test(tab.url)) return;

  // Sla dubbele meldingen voor dezelfde pagina in hetzelfde tabblad over
  const last = lastUrlByTab.get(tabId);
  if (last && last.url === tab.url && Date.now() - last.at < 10000) return;
  lastUrlByTab.set(tabId, { url: tab.url, at: Date.now() });

  enqueue([{
    url: tab.url,
    title: tab.title || null,
    incognito: tab.incognito,
    browser: browserName(),
    visited_at: new Date().toISOString(),
  }]).then(flush);
});

chrome.tabs.onRemoved.addListener((tabId) => lastUrlByTab.delete(tabId));

chrome.runtime.onInstalled.addListener(async () => {
  const { installedAt } = await chrome.storage.local.get('installedAt');
  if (!installedAt) await chrome.storage.local.set({ installedAt: Date.now() });
  chrome.alarms.create(FLUSH_ALARM, { periodInMinutes: 1 });
  chrome.runtime.openOptionsPage();
});

chrome.runtime.onStartup.addListener(() => {
  chrome.alarms.create(FLUSH_ALARM, { periodInMinutes: 1 });
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === FLUSH_ALARM) collectSyncedTabs().then(flush);
});

chrome.action.onClicked.addListener(() => chrome.runtime.openOptionsPage());

chrome.runtime.onMessage.addListener((msg, _sender, sendResponse) => {
  if (msg === 'flush') {
    collectSyncedTabs()
      .then(flush)
      .then(() => chrome.storage.local.get(['lastSync', 'lastError', 'queue', 'syncQueue']))
      .then((s) => sendResponse({
        lastSync: s.lastSync,
        lastError: s.lastError,
        pending: (s.queue || []).length + (s.syncQueue || []).length,
      }));
    return true;
  }

  if (msg?.type === 'importHistory') {
    importHistory(msg.days)
      .then((count) => sendResponse({ ok: true, count }))
      .catch((e) => sendResponse({ ok: false, error: String(e.message || e) }));
    return true;
  }
});
