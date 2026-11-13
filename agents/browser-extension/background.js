// Laptop Monitoring - legt bezochte pagina's vast (normaal en incognito)
// en stuurt ze in batches naar het dashboard.

const FLUSH_ALARM = 'flush';
const MAX_QUEUE = 5000;
const lastUrlByTab = new Map();
let lock = Promise.resolve();

function withLock(fn) {
  const run = lock.then(fn, fn);
  lock = run.catch(() => {});
  return run;
}

function browserName() {
  const ua = navigator.userAgent;
  if (ua.includes('Edg/')) return 'Edge';
  if (ua.includes('OPR/')) return 'Opera';
  if (navigator.brave) return 'Brave';
  return 'Chrome';
}

async function enqueue(visit) {
  await withLock(async () => {
    const { queue = [] } = await chrome.storage.local.get('queue');
    queue.push(visit);
    await chrome.storage.local.set({ queue: queue.slice(-MAX_QUEUE) });
  });
}

async function flush() {
  await withLock(async () => {
    const { queue = [], serverUrl, token } = await chrome.storage.local.get(['queue', 'serverUrl', 'token']);
    if (!queue.length || !serverUrl || !token) return;

    const batch = queue.slice(0, 500);
    try {
      const res = await fetch(serverUrl.replace(/\/+$/, '') + '/api/report', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          Authorization: 'Bearer ' + token,
        },
        body: JSON.stringify({ visits: batch }),
      });

      if (res.ok) {
        const { queue: current = [] } = await chrome.storage.local.get('queue');
        await chrome.storage.local.set({ queue: current.slice(batch.length), lastSync: new Date().toISOString(), lastError: null });
      } else {
        await chrome.storage.local.set({ lastError: 'HTTP ' + res.status });
      }
    } catch (e) {
      await chrome.storage.local.set({ lastError: String(e) });
    }
  });
}

chrome.tabs.onUpdated.addListener((tabId, changeInfo, tab) => {
  if (changeInfo.status !== 'complete' || !tab.url || !/^https?:/i.test(tab.url)) return;

  // Sla dubbele meldingen voor dezelfde pagina in hetzelfde tabblad over
  const last = lastUrlByTab.get(tabId);
  if (last && last.url === tab.url && Date.now() - last.at < 10000) return;
  lastUrlByTab.set(tabId, { url: tab.url, at: Date.now() });

  enqueue({
    url: tab.url,
    title: tab.title || null,
    incognito: tab.incognito,
    browser: browserName(),
    visited_at: new Date().toISOString(),
  }).then(flush);
});

chrome.tabs.onRemoved.addListener((tabId) => lastUrlByTab.delete(tabId));

chrome.runtime.onInstalled.addListener(() => {
  chrome.alarms.create(FLUSH_ALARM, { periodInMinutes: 1 });
  chrome.runtime.openOptionsPage();
});

chrome.runtime.onStartup.addListener(() => {
  chrome.alarms.create(FLUSH_ALARM, { periodInMinutes: 1 });
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === FLUSH_ALARM) flush();
});

chrome.action.onClicked.addListener(() => chrome.runtime.openOptionsPage());

chrome.runtime.onMessage.addListener((msg, _sender, sendResponse) => {
  if (msg === 'flush') {
    flush().then(() => chrome.storage.local.get(['lastSync', 'lastError', 'queue'])).then((s) =>
      sendResponse({ lastSync: s.lastSync, lastError: s.lastError, pending: (s.queue || []).length })
    );
    return true;
  }
});
