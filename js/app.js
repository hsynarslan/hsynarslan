(function () {
  "use strict";

  const cfg = window.APP_CONFIG;
  const { Auth, ExcelStore, DemoStore, todayIso } = window.IsTakip;
  const $ = (sel) => document.querySelector(sel);

  const state = {
    store: null,
    auth: null,
    tasks: [],
    units: [],
    sort: { key: "due", desc: false },
    editingId: null
  };

  // ---- Yardımcılar ----------------------------------------------------------

  function esc(s) {
    return String(s ?? "").replace(/[&<>"']/g, (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }

  function fmtDate(iso) {
    if (!iso) return "";
    const [y, m, d] = iso.split("-");
    return `${d}.${m}.${y}`;
  }

  function daysBetween(a, b) {
    return Math.round((Date.parse(b) - Date.parse(a)) / 86400000);
  }

  function isLate(t) {
    return !t.done && t.status !== "İptal" && t.due && t.due < todayIso();
  }

  function isActive(t) { return t.status !== "İptal"; }

  let toastTimer;
  function toast(msg, isError) {
    const el = $("#toast");
    el.textContent = msg;
    el.className = "toast show" + (isError ? " error" : "");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => (el.className = "toast"), isError ? 6000 : 2500);
  }

  function show(id) {
    for (const s of ["#signedOut", "#appView", "#loading"]) $(s).hidden = s !== id;
  }

  function fillSelect(sel, items, allLabel) {
    const current = sel.value;
    sel.innerHTML = (allLabel ? `<option value="">${esc(allLabel)}</option>` : "") +
      items.map((i) => `<option>${esc(i)}</option>`).join("");
    if ([...sel.options].some((o) => o.value === current)) sel.value = current;
  }

  // ---- Görünüm --------------------------------------------------------------

  function renderStats() {
    const active = state.tasks.filter(isActive);
    const done = active.filter((t) => t.done).length;
    const late = active.filter(isLate).length;
    const open = active.length - done;
    const rate = active.length ? Math.round((done / active.length) * 100) : 0;
    const cards = [
      ["Toplam iş", active.length, ""],
      ["Tamamlanan", done, "ok"],
      ["Açık", open, "info"],
      ["Geciken", late, "bad"],
      ["Tamamlanma oranı", `%${rate}`, ""]
    ];
    $("#stats").innerHTML = cards.map(([label, value, cls]) =>
      `<div class="stat ${cls}"><div class="label">${label}</div><div class="value">${value}</div></div>`
    ).join("");
  }

  function renderUnitProgress() {
    const unitFilter = $("#unitFilter").value;
    const units = [...new Set([...state.units, ...state.tasks.map((t) => t.unit).filter(Boolean)])];
    $("#unitProgress").innerHTML = units.map((u) => {
      const list = state.tasks.filter((t) => t.unit === u && isActive(t));
      const done = list.filter((t) => t.done).length;
      const late = list.filter(isLate).length;
      const pct = (n) => (list.length ? (n / list.length) * 100 : 0);
      return `<div class="unit-row ${unitFilter === u ? "active" : ""}" data-unit="${esc(u)}" title="Bu birime göre filtrele">
        <div class="unit-name">${esc(u)}</div>
        <div class="bar"><span class="done" style="width:${pct(done)}%"></span><span class="late" style="width:${pct(late)}%"></span></div>
        <div class="unit-nums">${done}/${list.length} tamam${late ? ` · <b style="color:var(--bad)">${late} gecikmiş</b>` : ""}</div>
      </div>`;
    }).join("") || `<p class="no-rows">Henüz birim yok.</p>`;
  }

  function filteredTasks() {
    const q = $("#search").value.trim().toLocaleLowerCase("tr-TR");
    const unit = $("#unitFilter").value;
    const status = $("#statusFilter").value;
    const overdueOnly = $("#overdueOnly").checked;
    const hideDone = $("#hideDone").checked;
    const { key, desc } = state.sort;
    const prioRank = (p) => { const i = cfg.priorities.indexOf(p); return i < 0 ? 99 : i; };

    return state.tasks
      .filter((t) => !unit || t.unit === unit)
      .filter((t) => !status || t.status === status)
      .filter((t) => !overdueOnly || isLate(t))
      .filter((t) => !hideDone || !t.done)
      .filter((t) => !q || [t.id, t.title, t.note, t.unit].join(" ").toLocaleLowerCase("tr-TR").includes(q))
      .sort((a, b) => {
        let va = a[key] ?? "", vb = b[key] ?? "";
        if (key === "priority") { va = prioRank(va); vb = prioRank(vb); }
        if (key === "due") { va = va || "9999"; vb = vb || "9999"; }
        const r = va < vb ? -1 : va > vb ? 1 : 0;
        return desc ? -r : r;
      });
  }

  function renderTable() {
    const rows = filteredTasks();
    const today = todayIso();
    $("#taskBody").innerHTML = rows.map((t) => {
      const late = isLate(t);
      const lateText = late ? `<span class="late-text">${daysBetween(t.due, today)} gün gecikti</span>` : "";
      return `<tr class="${t.done ? "is-done" : ""} ${late ? "is-late" : ""}" data-id="${esc(t.id)}">
        <td class="c-done"><input type="checkbox" data-action="toggle" ${t.done ? "checked" : ""} aria-label="${esc(t.id)} tamamlandı"></td>
        <td class="t-id">${esc(t.id)}</td>
        <td data-label="Birim">${esc(t.unit)}</td>
        <td><div class="t-title">${esc(t.title)}</div>${t.note ? `<div class="t-note">${esc(t.note)}</div>` : ""}</td>
        <td data-label="Öncelik" class="prio-${esc(t.priority)}">${esc(t.priority)}</td>
        <td data-label="Atama" class="t-date">${fmtDate(t.assigned)}</td>
        <td data-label="Termin" class="t-date">${fmtDate(t.due)}${lateText}</td>
        <td data-label="Durum"><span class="pill s-${esc(String(t.status).replace(/\s/g, ""))}">${esc(t.status)}</span>
          ${t.done && t.doneDate ? `<div class="t-note">${fmtDate(t.doneDate)}</div>` : ""}</td>
        <td><button class="btn small ghost" data-action="edit">Düzenle</button></td>
      </tr>`;
    }).join("");
    $("#noRows").hidden = rows.length > 0;

    document.querySelectorAll(".tasks th[data-sort]").forEach((th) => {
      th.classList.toggle("sorted", th.dataset.sort === state.sort.key);
      th.classList.toggle("desc", th.dataset.sort === state.sort.key && state.sort.desc);
    });
  }

  function render() {
    fillSelect($("#unitFilter"), state.units, "Tüm birimler");
    fillSelect($("#statusFilter"), cfg.statuses, "Tüm durumlar");
    renderStats();
    renderUnitProgress();
    renderTable();
  }

  // ---- Veri işlemleri ---------------------------------------------------------

  async function load() {
    if (!state.store) return;
    show("#loading");
    try {
      const data = await state.store.load();
      state.tasks = data.tasks;
      state.units = data.units;
      render();
      show("#appView");
    } catch (e) {
      console.error(e);
      show("#appView");
      toast(e.message || String(e), true);
    }
  }

  async function saveTask(task, isNew) {
    try {
      if (isNew) {
        const saved = await state.store.addTask(task, state.tasks);
        state.tasks.push(saved);
        toast(`${saved.id} eklendi`);
      } else {
        await state.store.updateTask(task);
        const i = state.tasks.findIndex((t) => t.id === task.id);
        state.tasks[i] = task;
        toast(`${task.id} kaydedildi`);
      }
      render();
      return true;
    } catch (e) {
      console.error(e);
      toast(e.message || String(e), true);
      return false;
    }
  }

  async function toggleDone(id, checkbox) {
    const t = state.tasks.find((x) => x.id === id);
    if (!t) return;
    const updated = { ...t, done: checkbox.checked };
    if (updated.done) {
      updated.status = "Tamamlandı";
      updated.doneDate = todayIso();
    } else {
      if (updated.status === "Tamamlandı") updated.status = "Devam Ediyor";
      updated.doneDate = "";
    }
    checkbox.disabled = true;
    const ok = await saveTask(updated, false);
    if (!ok) { checkbox.checked = t.done; checkbox.disabled = false; }
  }

  // ---- Form -------------------------------------------------------------------

  function openDialog(task) {
    const form = $("#taskForm");
    state.editingId = task ? task.id : null;
    $("#dialogTitle").textContent = task ? `${task.id} — düzenle` : "Yeni görev";
    fillSelect(form.unit, state.units);
    fillSelect(form.priority, cfg.priorities);
    fillSelect(form.status, cfg.statuses);
    const t = task || {
      title: "", unit: $("#unitFilter").value || state.units[0] || "", priority: "Orta",
      assigned: todayIso(), due: "", status: cfg.statuses[0], doneDate: "", note: ""
    };
    for (const f of ["title", "unit", "priority", "assigned", "due", "status", "doneDate", "note"]) {
      form[f].value = t[f] || "";
    }
    $("#taskDialog").showModal();
    form.title.focus();
  }

  async function submitDialog(ev) {
    ev.preventDefault();
    const form = $("#taskForm");
    const base = state.editingId ? state.tasks.find((t) => t.id === state.editingId) : {};
    const task = { ...base };
    for (const f of ["title", "unit", "priority", "assigned", "due", "status", "doneDate", "note"]) {
      task[f] = form[f].value.trim();
    }
    task.done = task.status === "Tamamlandı";
    if (task.done && !task.doneDate) task.doneDate = todayIso();
    if (!task.done) task.doneDate = "";

    const btn = form.querySelector("[type=submit]");
    btn.disabled = true;
    const ok = await saveTask(task, !state.editingId);
    btn.disabled = false;
    if (ok) $("#taskDialog").close();
  }

  // ---- Olaylar ----------------------------------------------------------------

  function bindEvents() {
    for (const id of ["#search", "#unitFilter", "#statusFilter", "#overdueOnly", "#hideDone"]) {
      $(id).addEventListener("input", () => { renderUnitProgress(); renderTable(); });
    }

    $("#unitProgress").addEventListener("click", (e) => {
      const row = e.target.closest(".unit-row");
      if (!row) return;
      const sel = $("#unitFilter");
      sel.value = sel.value === row.dataset.unit ? "" : row.dataset.unit;
      renderUnitProgress();
      renderTable();
    });

    document.querySelector(".tasks thead").addEventListener("click", (e) => {
      const th = e.target.closest("th[data-sort]");
      if (!th) return;
      const key = th.dataset.sort;
      state.sort = { key, desc: state.sort.key === key ? !state.sort.desc : false };
      renderTable();
    });

    $("#taskBody").addEventListener("click", (e) => {
      const tr = e.target.closest("tr[data-id]");
      if (!tr) return;
      const action = e.target.dataset.action;
      if (action === "toggle") toggleDone(tr.dataset.id, e.target);
      if (action === "edit") openDialog(state.tasks.find((t) => t.id === tr.dataset.id));
    });

    $("#newBtn").addEventListener("click", () => openDialog(null));
    $("#taskForm").addEventListener("submit", submitDialog);
    $("#taskForm [data-action=cancel]").addEventListener("click", () => $("#taskDialog").close());
    $("#refreshBtn").addEventListener("click", load);

    const login = () => state.auth.login();
    $("#loginBtn").addEventListener("click", login);
    document.querySelector("[data-action=login]").addEventListener("click", login);
    $("#logoutBtn").addEventListener("click", () => state.auth.logout());
  }

  // ---- Başlangıç --------------------------------------------------------------

  async function start() {
    bindEvents();
    const demo = !cfg.clientId;
    const badge = $("#modeBadge");

    if (demo) {
      state.store = new DemoStore(cfg);
      badge.textContent = "Demo modu";
      badge.className = "badge demo";
      badge.title = "config.js içinde clientId tanımlanınca Excel'e bağlanır";
      await load();
      return;
    }

    badge.textContent = "Excel";
    badge.className = "badge live";
    if (typeof msal === "undefined") {
      show("#signedOut");
      toast("Microsoft giriş kütüphanesi yüklenemedi (internet bağlantısını kontrol edin).", true);
      return;
    }
    try {
      state.auth = new Auth(cfg);
      await state.auth.init();
    } catch (e) {
      console.error(e);
      show("#signedOut");
      toast("Giriş hatası: " + (e.message || e), true);
      return;
    }

    const account = state.auth.account;
    $("#loginBtn").hidden = !!account;
    $("#logoutBtn").hidden = !account;
    if (!account) { show("#signedOut"); return; }

    $("#userName").textContent = account.name || account.username;
    state.store = new ExcelStore(cfg, state.auth);
    await load();
  }

  start();
})();
