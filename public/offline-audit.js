const DB_NAME = "audytor-it-offline";
const DB_VERSION = 1;
const STORE_NAME = "drafts";

function openAuditDb() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onerror = () => reject(request.error);
    request.onsuccess = () => resolve(request.result);
    request.onupgradeneeded = () => {
      const db = request.result;

      if (!db.objectStoreNames.contains(STORE_NAME)) {
        db.createObjectStore(STORE_NAME, { keyPath: "key" });
      }
    };
  });
}

async function saveDraft(form) {
  if (!("indexedDB" in window)) {
    return;
  }

  const db = await openAuditDb();
  const data = {};

  for (const [name, value] of new FormData(form).entries()) {
    if (value instanceof File) {
      data[name] = value.name;
    } else {
      data[name] = value;
    }
  }

  const draft = {
    key: form.action,
    action: form.action,
    method: form.method || "post",
    data,
    savedAt: new Date().toISOString(),
    syncStatus: navigator.onLine ? "ready" : "offline",
  };

  await new Promise((resolve, reject) => {
    const transaction = db.transaction(STORE_NAME, "readwrite");

    transaction.objectStore(STORE_NAME).put(draft);
    transaction.oncomplete = resolve;
    transaction.onerror = () => reject(transaction.error);
  });
}

async function registerSync() {
  if (!("serviceWorker" in navigator) || !("SyncManager" in window)) {
    return;
  }

  const registration = await navigator.serviceWorker.ready;

  await registration.sync.register("audytor-it-sync");
}

document.addEventListener("input", (event) => {
  const form = event.target.closest("form[data-offline-draft]");

  if (form) {
    saveDraft(form).catch(() => undefined);
  }
});

window.addEventListener("online", () => {
  registerSync().catch(() => undefined);
});
