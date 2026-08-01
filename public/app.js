const audits = [
  {
    id: 1,
    title: "Audyt podstawowy IT",
    client: "Klient Testowy Sp. z o.o.",
    location: "Centrala",
    status: "W trakcie",
    syncStatus: "synced"
  },
  {
    id: 2,
    title: "Audyt bezpieczeństwa",
    client: "Klient Testowy Sp. z o.o.",
    location: "Serwerownia",
    status: "Do weryfikacji",
    syncStatus: "pending_sync"
  }
];

const modules = [
  {
    name: "UTM/firewall",
    description: "Urządzenie brzegowe, firmware, licencje, VPN i polityki firewall.",
    questions: [
      {
        question: "Podaj producenta i model urządzenia.",
        instruction: "Sprawdź etykietę urządzenia albo dashboard administracyjny.",
        fieldType: "text",
        required: true,
        requirePhoto: false,
        requireScreenshot: false
      },
      {
        question: "Czy panel administracyjny jest dostępny z WAN?",
        instruction: "Zweryfikuj reguły dostępu administracyjnego i ograniczenia źródłowe.",
        fieldType: "yes_no",
        required: true,
        requirePhoto: false,
        requireScreenshot: true,
        riskEnabled: true
      },
      {
        question: "Dodaj zdjęcie urządzenia w szafie rack.",
        instruction: "Zdjęcie powinno pokazywać urządzenie i okablowanie bez ujawniania haseł.",
        fieldType: "photo",
        required: true,
        requirePhoto: true,
        requireScreenshot: false
      },
      {
        question: "Oceń ryzyko i dodaj rekomendację.",
        instruction: "Dla ryzyka wysokiego albo krytycznego rekomendacja jest obowiązkowa.",
        fieldType: "risk",
        required: true,
        requirePhoto: false,
        requireScreenshot: false,
        riskEnabled: true
      }
    ]
  },
  {
    name: "Switche",
    description: "Topologia, VLAN, dostęp administracyjny, PoE i backup konfiguracji.",
    questions: []
  },
  {
    name: "Wi-Fi",
    description: "SSID, segmentacja, zabezpieczenia, kontroler i zasięg.",
    questions: []
  },
  {
    name: "Serwery",
    description: "Sprzęt, systemy, role, gwarancje i monitoring.",
    questions: []
  },
  {
    name: "Backup",
    description: "Zakres backupu, retencja, testy odtworzeniowe i alerty.",
    questions: []
  },
  {
    name: "Microsoft 365",
    description: "MFA, role adminów, backup, licencje i Conditional Access.",
    questions: []
  },
  {
    name: "Komputery",
    description: "Standard stacji, EDR, szyfrowanie i aktualizacje.",
    questions: []
  },
  {
    name: "Pomieszczenie serwerowe",
    description: "Zasilanie, chłodzenie, dostęp fizyczny i porządek w szafach.",
    questions: []
  }
];

const storageKey = "audytor-it-answers";
const queueKey = "audytor-it-sync-queue";
let activeModuleIndex = 0;
let activeQuestionIndex = 0;

function readJson(key, fallback) {
  try {
    return JSON.parse(localStorage.getItem(key)) || fallback;
  } catch {
    return fallback;
  }
}

function writeJson(key, value) {
  localStorage.setItem(key, JSON.stringify(value));
}

function answerId(moduleIndex, questionIndex) {
  return `${moduleIndex}:${questionIndex}`;
}

function getAnswers() {
  return readJson(storageKey, {});
}

function getQueue() {
  return readJson(queueKey, []);
}

function setQueued(payload) {
  const queue = getQueue();
  queue.push({
    ...payload,
    localUuid: crypto.randomUUID(),
    syncStatus: navigator.onLine ? "pending_sync" : "local_only",
    changedAt: new Date().toISOString()
  });
  writeJson(queueKey, queue);
}

function renderNavigation() {
  document.querySelectorAll(".nav-button").forEach((button) => {
    button.addEventListener("click", () => {
      document.querySelectorAll(".nav-button").forEach((item) => item.classList.remove("active"));
      document.querySelectorAll(".view").forEach((view) => view.classList.remove("active-view"));
      button.classList.add("active");
      document.getElementById(button.dataset.view).classList.add("active-view");
      document.getElementById("viewTitle").textContent = button.textContent;
    });
  });
}

function renderAudits() {
  const list = document.getElementById("auditList");
  list.innerHTML = audits
    .map(
      (audit) => `
        <article class="audit-row">
          <div>
            <strong>${audit.title}</strong>
            <span>${audit.client} / ${audit.location}</span>
          </div>
          <span class="pill">${audit.status}</span>
        </article>
      `
    )
    .join("");
}

function renderModules() {
  const tabs = document.getElementById("moduleTabs");
  tabs.innerHTML = modules
    .map(
      (module, index) =>
        `<button class="${index === activeModuleIndex ? "active" : ""}" data-module="${index}">${module.name}</button>`
    )
    .join("");

  tabs.querySelectorAll("button").forEach((button) => {
    button.addEventListener("click", () => {
      activeModuleIndex = Number(button.dataset.module);
      activeQuestionIndex = 0;
      renderAuditorView();
    });
  });
}

function renderTemplateGrid() {
  const grid = document.getElementById("templateGrid");
  grid.innerHTML = modules
    .map(
      (module) => `
        <article class="module-card">
          <h3>${module.name}</h3>
          <p>${module.description}</p>
        </article>
      `
    )
    .join("");
}

function renderQuestion() {
  const module = modules[activeModuleIndex];
  const question = module.questions[activeQuestionIndex];
  const card = document.getElementById("questionCard");

  if (!question) {
    card.innerHTML = `
      <h2>${module.name}</h2>
      <p>Ten moduł jest przygotowany w szablonie. Pytania zostaną dodane w panelu administracyjnym.</p>
    `;
    return;
  }

  const answers = getAnswers();
  const current = answers[answerId(activeModuleIndex, activeQuestionIndex)] || {};
  const inputHtml =
    question.fieldType === "yes_no"
      ? `<select id="answerValue"><option value="">Wybierz</option><option ${current.value === "Tak" ? "selected" : ""}>Tak</option><option ${current.value === "Nie" ? "selected" : ""}>Nie</option></select>`
      : question.fieldType === "risk"
        ? `<select id="answerValue"><option value="">Wybierz</option><option>Niskie</option><option>Średnie</option><option>Wysokie</option><option>Krytyczne</option></select>`
        : question.fieldType === "photo"
          ? `<input id="answerValue" type="file" accept="image/*" capture="environment">`
          : `<input id="answerValue" value="${current.value || ""}" placeholder="Wpisz odpowiedź">`;

  card.innerHTML = `
    <h2>${question.question}</h2>
    <p>${question.instruction}</p>
    <label>Odpowiedź ${question.required ? "(wymagana)" : ""}
      ${inputHtml}
    </label>
    <label>Komentarz techniczny
      <textarea id="answerComment" placeholder="Dodaj kontekst dla lidera technicznego">${current.comment || ""}</textarea>
    </label>
    <label>Rekomendacja
      <textarea id="answerRecommendation" placeholder="Wybierz albo wpisz rekomendację">${current.recommendation || ""}</textarea>
    </label>
    <div class="question-actions">
      <button id="saveAnswer">Zapisz lokalnie</button>
      <button class="secondary" id="markNa">Nie dotyczy</button>
      <button class="secondary" id="nextQuestion">Następny krok</button>
    </div>
  `;

  document.getElementById("saveAnswer").addEventListener("click", saveCurrentAnswer);
  document.getElementById("markNa").addEventListener("click", markNotApplicable);
  document.getElementById("nextQuestion").addEventListener("click", nextQuestion);
}

function saveCurrentAnswer() {
  const answers = getAnswers();
  const id = answerId(activeModuleIndex, activeQuestionIndex);
  const valueInput = document.getElementById("answerValue");
  const file = valueInput?.files?.[0];
  const value = file ? file.name : valueInput?.value || "";

  answers[id] = {
    value,
    comment: document.getElementById("answerComment").value,
    recommendation: document.getElementById("answerRecommendation").value,
    notApplicable: false,
    syncStatus: navigator.onLine ? "pending_sync" : "local_only",
    updatedAt: new Date().toISOString()
  };

  writeJson(storageKey, answers);
  setQueued({ type: "audit_answer", id, payload: answers[id] });
  renderAuditorView();
}

function markNotApplicable() {
  const answers = getAnswers();
  const id = answerId(activeModuleIndex, activeQuestionIndex);
  answers[id] = {
    value: null,
    comment: document.getElementById("answerComment").value || "Oznaczono jako nie dotyczy.",
    recommendation: document.getElementById("answerRecommendation").value,
    notApplicable: true,
    syncStatus: navigator.onLine ? "pending_sync" : "local_only",
    updatedAt: new Date().toISOString()
  };
  writeJson(storageKey, answers);
  setQueued({ type: "audit_answer", id, payload: answers[id] });
  renderAuditorView();
}

function nextQuestion() {
  const module = modules[activeModuleIndex];
  activeQuestionIndex = Math.min(activeQuestionIndex + 1, Math.max(module.questions.length - 1, 0));
  renderAuditorView();
}

function renderSteps() {
  const steps = document.getElementById("stepList");
  const answers = getAnswers();
  const module = modules[activeModuleIndex];

  steps.innerHTML = module.questions
    .map((question, index) => {
      const answer = answers[answerId(activeModuleIndex, index)];
      const state = answer ? "done" : question.required ? "missing" : "";
      return `
        <button class="step-row ${state}" data-question="${index}">
          <span class="step-index">${index + 1}</span>
          <span>${question.question}</span>
        </button>
      `;
    })
    .join("");

  steps.querySelectorAll("button").forEach((button) => {
    button.addEventListener("click", () => {
      activeQuestionIndex = Number(button.dataset.question);
      renderAuditorView();
    });
  });
}

function renderProgress() {
  const answers = getAnswers();
  const allQuestions = modules.flatMap((module, moduleIndex) =>
    module.questions.map((question, questionIndex) => ({ moduleIndex, questionIndex, question }))
  );
  const required = allQuestions.filter((item) => item.question.required);
  const completed = required.filter((item) => answers[answerId(item.moduleIndex, item.questionIndex)]);
  const percent = required.length ? Math.round((completed.length / required.length) * 100) : 0;
  const missing = required.length - completed.length;

  document.getElementById("progressLabel").textContent = `${percent}% ukończenia`;
  document.getElementById("missingLabel").textContent = `${missing} braków`;
  document.getElementById("progressBar").style.width = `${percent}%`;
}

function renderAuditorView() {
  renderModules();
  renderQuestion();
  renderSteps();
  renderProgress();
  updateSyncStatus();
}

function updateSyncStatus() {
  const queue = getQueue();
  document.getElementById("pendingCount").textContent = String(queue.length);
  document.getElementById("networkLabel").textContent = navigator.onLine ? "Online" : "Offline";
  document.getElementById("networkDot").classList.toggle("offline", !navigator.onLine);
  document.getElementById("syncLabel").textContent = queue.length
    ? `${queue.length} elementów oczekuje na synchronizację`
    : "Wszystko zsynchronizowane";
}

function syncNow() {
  if (!navigator.onLine) {
    updateSyncStatus();
    return;
  }

  writeJson(queueKey, []);
  const answers = getAnswers();
  Object.keys(answers).forEach((key) => {
    answers[key].syncStatus = "synced";
  });
  writeJson(storageKey, answers);
  updateSyncStatus();
}

function registerServiceWorker() {
  if ("serviceWorker" in navigator) {
    navigator.serviceWorker.register("./service-worker.js");
  }
}

renderNavigation();
renderAudits();
renderTemplateGrid();
renderAuditorView();
registerServiceWorker();

document.getElementById("syncNow").addEventListener("click", syncNow);
window.addEventListener("online", updateSyncStatus);
window.addEventListener("offline", updateSyncStatus);
