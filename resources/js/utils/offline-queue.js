/**
 * DBEDC Guardian Offline Action Queue Manager (IndexedDB)
 */

const DB_NAME = 'dbedc_offline_queue';
const DB_VERSION = 1;
const STORE_NAME = 'pending_actions';

function openDB() {
  return new Promise((resolve, reject) => {
    if (!window.indexedDB) {
      reject(new Error('IndexedDB not supported'));
      return;
    }

    const request = window.indexedDB.open(DB_NAME, DB_VERSION);

    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      if (!db.objectStoreNames.contains(STORE_NAME)) {
        const store = db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
        store.createIndex('timestamp', 'timestamp', { unique: false });
        store.createIndex('actionType', 'actionType', { unique: false });
      }
    };

    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
  });
}

export async function queueOfflineAction(actionType, url, method, data, headers = {}) {
  try {
    const db = await openDB();
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);

    const item = {
      actionType,
      url,
      method: method.toUpperCase(),
      data,
      headers,
      timestamp: Date.now(),
      status: 'pending'
    };

    return new Promise((resolve, reject) => {
      const req = store.add(item);
      req.onsuccess = () => {
        window.dispatchEvent(new CustomEvent('offline-queue-changed'));
        resolve(req.result);
      };
      req.onerror = () => reject(req.error);
    });
  } catch (err) {
    console.error('Failed to queue offline action:', err);
    throw err;
  }
}

export async function getPendingActions() {
  try {
    const db = await openDB();
    const tx = db.transaction(STORE_NAME, 'readonly');
    const store = tx.objectStore(STORE_NAME);

    return new Promise((resolve, reject) => {
      const req = store.getAll();
      req.onsuccess = () => resolve(req.result || []);
      req.onerror = () => reject(req.error);
    });
  } catch (err) {
    console.error('Failed to fetch pending actions:', err);
    return [];
  }
}

export async function clearPendingAction(id) {
  try {
    const db = await openDB();
    const tx = db.transaction(STORE_NAME, 'readwrite');
    const store = tx.objectStore(STORE_NAME);

    return new Promise((resolve, reject) => {
      const req = store.delete(id);
      req.onsuccess = () => {
        window.dispatchEvent(new CustomEvent('offline-queue-changed'));
        resolve(true);
      };
      req.onerror = () => reject(req.error);
    });
  } catch (err) {
    console.error('Failed to clear pending action:', err);
  }
}

export async function syncOfflineQueue() {
  if (!navigator.onLine) return { synced: 0, failed: 0 };

  const actions = await getPendingActions();
  if (actions.length === 0) return { synced: 0, failed: 0 };

  let synced = 0;
  let failed = 0;

  for (const item of actions) {
    try {
      const response = await fetch(item.url, {
        method: item.method,
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          ...item.headers
        },
        body: item.data ? JSON.stringify(item.data) : undefined
      });

      if (response.ok) {
        await clearPendingAction(item.id);
        synced++;
      } else if (response.status >= 400 && response.status < 500) {
        // Client error, discard or log
        console.warn(`Offline action ${item.id} rejected by server (${response.status})`);
        await clearPendingAction(item.id);
        failed++;
      } else {
        failed++;
      }
    } catch (err) {
      console.error(`Failed to sync offline action ${item.id}:`, err);
      failed++;
    }
  }

  window.dispatchEvent(new CustomEvent('offline-sync-success', { detail: { synced, failed } }));
  return { synced, failed };
}

// Auto-sync listener on window reconnect
if (typeof window !== 'undefined') {
  window.addEventListener('online', () => {
    console.log('Network reconnected! Syncing offline queue...');
    syncOfflineQueue();
  });
}
