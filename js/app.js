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
    view: "list",
    editingId: null
  };

  const MONTHS = ["Oca", "Şub", "Mar", "Nis", "May", "Haz", "Tem", "Ağu", "Eyl", "Eki", "Kas", "Ara"];

  // Durum dağılımı grafiğindeki dilimler. Sıra renk körlüğüne göre seçildi: yeşil ile kırmızı yan yana gelmez.
  const BUCKETS = [
    { key: "done", label: "Tamamlandı", color: "var(--good)" },
    { key: "prog", label: "Devam ediyor", color: "var(--progress)" },
    { key: "late", label: "Gecikmiş", color: "var(--critical)" },
    { key: "todo", label: "Başlanmadı", color: "var(--neutral)" }
  ];

  const ICONS = {
    total: '<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
    done: '<path d="M20 6 9 17l-5-5"/>',
    open: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    late: '<path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>'
  };

  // ---- Yardımcılar ----------------------------------------------------------

  function esc(s) {
    return String(s ?? "").replace(/[&<>"']/g, (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  }

  function icon(name, size = 22) {
    return `<svg viewBox="0 0 24 24" width="${size}" height="${size}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${ICONS[name]}</svg>`;
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

  function bucketOf(t) {
    if (t.done) return "done";
    if (isLate(t)) return "late";
    if (t.status === "Devam Ediyor") return "prog";
    return "todo";
  }

  function pct(n, total) { return total ? Math.round((n / total) * 100) : 0; }

  function unitColor(unit) {
    const i = state.units.indexOf(unit);
    return i >= 0 && i < 8 ? `var(--c${i + 1})` : "var(--neutral)";
  }

  function initials(name) {
    return String(name || "?").split(/\s+/).filter(Boolean).slice(0, 2)
      .map((w) => w[0].toLocaleUpperCase("tr-TR")).join("");
  }

  function avatar(unit, small) {
    return `<span class="avatar${small ? " sm" : ""}" style="--u:${unitColor(unit)}" aria-hidden="true">${esc(initials(unit))}</span>`;
  }

  function dueInfo(t) {
    if (t.done) return { cls: "done", text: t.doneDate ? `${fmtDate(t.doneDate)} bitti` : "Bitti" };
    if (!t.due) return null;
    const diff = daysBetween(todayIso(), t.due);
    if (t.status === "İptal") return null;
    if (diff < 0) return { cls: "late", text: `${-diff} gün gecikti` };
    if (diff === 0) return { cls: "soon", text: "Bugün" };
    if (diff === 1) return { cls: "soon", text: "Yarın" };
    return { cls: diff <= 7 ? "soon" : "ok", text: `${diff} gün kaldı` };
  }

  function dueHtml(t) {
    const info = dueInfo(t);
    return `<div class="due"><span>${fmtDate(t.due) || "—"}</span>${info ? `<span class="due-rel ${info.cls}">${info.text}</span>` : ""}</div>`;
  }

  function prioHtml(p) {
    return `<span class="prio p-${esc(p)}"><i aria-hidden="true"><b></b><b></b><b></b></i>${esc(p)}</span>`;
  }

  function statusHtml(s) {
    return `<span class="pill st-${esc(String(s).replace(/\s/g, ""))}">${esc(s)}</span>`;
  }

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
    $("#heroBody").hidden = id !== "#appView";
  }

  function fillSelect(sel, items, allLabel) {
    const current = sel.value;
    sel.innerHTML = (allLabel ? `<option value="">${esc(allLabel)}</option>` : "") +
      items.map((i) => `<option>${esc(i)}</option>`).join("");
    if ([...sel.options].some((o) => o.value === current)) sel.value = current;
  }

  function setFilters({ unit = "", status = "", overdue = false, hideDone = false } = {}) {
    $("#unitFilter").value = unit;
    $("#statusFilter").value = status;
    $("#overdueOnly").checked = overdue;
    $("#hideDone").checked = hideDone;
    $("#search").value = "";
    renderFiltered();
  }

  // ---- Görünüm: üst bant ----------------------------------------------------

  function renderHero() {
    const now = new Date();
    const h = now.getHours();
    const greet = h < 5 ? "İyi geceler" : h < 12 ? "Günaydın" : h < 18 ? "İyi günler" : h < 22 ? "İyi akşamlar" : "İyi geceler";
    const who = state.auth && state.auth.account ? ", " + String(state.auth.account.name || "").split(" ")[0] : "";
    $("#heroDate").textContent = now.toLocaleDateString("tr-TR", { day: "numeric", month: "long", year: "numeric", weekday: "long" });
    $("#heroGreeting").textContent = greet + who;

    const active = state.tasks.filter(isActive);
    const open = active.filter((t) => !t.done);
    const late = open.filter(isLate).length;
    const week = open.filter((t) => t.due && !isLate(t) && daysBetween(todayIso(), t.due) <= 7).length;
    const unitCount = new Set(open.map((t) => t.unit)).size;
    let s = `<b>${unitCount} birimde ${open.length} açık iş</b> var. `;
    s += late ? `<b class="late">${late} iş gecikmiş</b>` : "Geciken iş yok";
    s += week ? `, ${week} işin termini bu hafta doluyor.` : ".";
    $("#heroSummary").innerHTML = s;

    const done = active.length - open.length;
    const p = pct(done, active.length);
    const r = 52, c = 2 * Math.PI * r;
    $("#heroRing").innerHTML = `
      <svg width="132" height="132" viewBox="0 0 132 132" role="img" aria-label="Tamamlanma oranı yüzde ${p}">
        <circle class="ring-track" cx="66" cy="66" r="${r}" fill="none" stroke-width="12"/>
        <circle class="ring-value" cx="66" cy="66" r="${r}" fill="none" stroke-width="12" stroke-linecap="round"
          stroke-dasharray="${c}" stroke-dashoffset="${c * (1 - p / 100)}" transform="rotate(-90 66 66)"/>
        <text class="ring-num" x="66" y="68" text-anchor="middle">%${p}</text>
        <text class="ring-cap" x="66" y="86" text-anchor="middle">TAMAMLANDI</text>
      </svg>
      <div class="ring-legend">
        <span><b>${done}</b> / ${active.length} iş bitti</span>
        <span><b>${state.units.length}</b> birim</span>
      </div>`;
  }

  // ---- Görünüm: kartlar ve içgörüler ------------------------------------------

  function renderStats() {
    const active = state.tasks.filter(isActive);
    const done = active.filter((t) => t.done).length;
    const open = active.filter((t) => !t.done);
    const lateList = open.filter(isLate);
    const today = todayIso();
    const maxLate = lateList.reduce((m, t) => Math.max(m, daysBetween(t.due, today)), 0);
    const soon = open.filter((t) => t.due && !isLate(t) && daysBetween(today, t.due) <= 7).length;
    const high = open.filter((t) => t.priority === "Yüksek").length;

    const f = { status: $("#statusFilter").value, overdue: $("#overdueOnly").checked, hide: $("#hideDone").checked };
    const cards = [
      { k: "total", cls: "s-total", label: "Toplam iş", value: active.length,
        hint: `${state.units.length} birime dağıtıldı`, active: false },
      { k: "done", cls: "s-done", label: "Tamamlanan", value: done,
        hint: `Tüm işlerin %${pct(done, active.length)}'i`, active: f.status === "Tamamlandı" },
      { k: "open", cls: "s-open", label: "Açık", value: open.length,
        hint: high ? `${high} tanesi yüksek öncelikli` : `${soon} tanesi 7 gün içinde`, active: f.hide && !f.overdue },
      { k: "late", cls: "s-late", label: "Geciken", value: lateList.length,
        hint: lateList.length ? `En uzun gecikme ${maxLate} gün` : "Termini geçen iş yok", active: f.overdue }
    ];
    $("#stats").innerHTML = cards.map((c) => `
      <button class="stat ${c.cls} ${c.active ? "active" : ""}" data-stat="${c.k}" title="Listeyi süz">
        <span class="stat-icon">${icon(c.k)}</span>
        <span class="label">${c.label}</span>
        <span class="value">${c.value}</span>
        <span class="hint">${c.hint}</span>
      </button>`).join("");
  }

  function renderUnitProgress() {
    const unitFilter = $("#unitFilter").value;
    const units = [...new Set([...state.units, ...state.tasks.map((t) => t.unit).filter(Boolean)])];
    const rows = units.map((u) => {
      const list = state.tasks.filter((t) => t.unit === u && isActive(t));
      const n = { done: 0, prog: 0, late: 0, todo: 0 };
      list.forEach((t) => n[bucketOf(t)]++);
      const w = (x) => (list.length ? (x / list.length) * 100 : 0);
      const tip = `<b>${esc(u)}</b><br>${n.done} tamamlandı · ${n.prog} devam ediyor<br>${n.late} gecikmiş · ${n.todo} başlanmadı`;
      return `<button class="unit-row ${unitFilter === u ? "active" : ""}" data-unit="${esc(u)}" data-tip="${esc(tip)}">
        ${avatar(u)}
        <span class="unit-name">${esc(u)}</span>
        <span class="unit-pct">%${pct(n.done, list.length)}</span>
        <span class="unit-meta">
          <span class="bar">
            ${n.done ? `<span class="done" style="width:${w(n.done)}%"></span>` : ""}
            ${n.prog ? `<span class="prog" style="width:${w(n.prog)}%"></span>` : ""}
            ${n.late ? `<span class="late" style="width:${w(n.late)}%"></span>` : ""}
          </span>
          <span class="unit-nums">${n.done}/${list.length}${n.late ? ` · <span class="late-n">${n.late} gecikmiş</span>` : ""}</span>
        </span>
      </button>`;
    });
    $("#unitProgress").innerHTML = rows.join("") || `<p class="no-rows">Henüz birim yok.</p>`;
  }

  function renderStatusChart() {
    const active = state.tasks.filter(isActive);
    const counts = Object.fromEntries(BUCKETS.map((b) => [b.key, 0]));
    active.forEach((t) => counts[bucketOf(t)]++);
    const total = active.length;
    const r = 58, c = 2 * Math.PI * r, gap = total > 1 ? 3 : 0;
    let offset = 0;
    const segs = BUCKETS.filter((b) => counts[b.key]).map((b) => {
      const len = (counts[b.key] / total) * c;
      const dash = Math.max(len - gap, 0.5);
      const tip = `<b>${b.label}</b><br>${counts[b.key]} iş · %${pct(counts[b.key], total)}`;
      const s = `<circle class="seg" data-bucket="${b.key}" data-tip="${esc(tip)}" cx="80" cy="80" r="${r}" fill="none"
        stroke="${b.color}" stroke-width="18" stroke-dasharray="${dash} ${c - dash}" stroke-dashoffset="${-offset}"
        transform="rotate(-90 80 80)"/>`;
      offset += len;
      return s;
    }).join("");
    $("#statusChart").innerHTML = `
      <svg class="donut" width="160" height="160" viewBox="0 0 160 160" role="img" aria-label="Durum dağılımı">
        <circle cx="80" cy="80" r="${r}" fill="none" stroke="var(--surface-2)" stroke-width="18"/>
        ${segs}
        <text class="donut-num" x="80" y="84" text-anchor="middle">${total}</text>
        <text class="donut-cap" x="80" y="102" text-anchor="middle">iş</text>
      </svg>
      <ul class="legend">
        ${BUCKETS.map((b) => `<li data-bucket="${b.key}" title="Listeyi süz">
          <span class="sw" style="background:${b.color}"></span><span>${b.label}</span>
          <span class="n">${counts[b.key]}</span><span class="p">%${pct(counts[b.key], total)}</span></li>`).join("")}
      </ul>`;
  }

  function renderUpcoming() {
    const today = todayIso();
    const list = state.tasks
      .filter((t) => !t.done && isActive(t) && t.due && daysBetween(today, t.due) <= 7)
      .sort((a, b) => (a.due < b.due ? -1 : 1));
    $("#upcoming").innerHTML = list.map((t) => {
      const [, m, d] = t.due.split("-");
      const info = dueInfo(t);
      const calCls = isLate(t) ? "late" : t.due === today ? "today" : "";
      return `<li data-id="${esc(t.id)}" title="Düzenlemek için tıklayın">
        <div class="cal ${calCls}"><div class="m">${MONTHS[+m - 1]}</div><div class="d">${+d}</div></div>
        <div style="min-width:0">
          <div class="up-title">${esc(t.title)}</div>
          <div class="up-meta">${avatar(t.unit, true)}<span>${esc(t.unit)}</span>
            <span class="due-rel ${info.cls}">${info.text}</span></div>
        </div>
      </li>`;
    }).join("") || `<li class="up-empty">Önümüzdeki 7 günde termini dolan iş yok.</li>`;
  }

  // ---- Görünüm: görev listesi ve pano -----------------------------------------

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

  function renderTable(rows) {
    $("#taskBody").innerHTML = rows.map((t) => `
      <tr class="${t.done ? "is-done" : ""} ${isLate(t) ? "is-late" : ""}" data-id="${esc(t.id)}">
        <td class="c-done"><input class="tick" type="checkbox" data-action="toggle" ${t.done ? "checked" : ""} aria-label="${esc(t.id)} tamamlandı"></td>
        <td class="t-id">${esc(t.id)}</td>
        <td class="c-title"><div class="t-title">${esc(t.title)}</div>${t.note ? `<div class="t-note">${esc(t.note)}</div>` : ""}</td>
        <td><span class="unit-cell">${avatar(t.unit, true)}${esc(t.unit)}</span></td>
        <td>${prioHtml(t.priority)}</td>
        <td>${dueHtml(t)}</td>
        <td>${statusHtml(t.status)}</td>
        <td class="c-act"><button class="btn small ghost" data-action="edit">Düzenle</button></td>
      </tr>`).join("");

    document.querySelectorAll(".tasks th[data-sort]").forEach((th) => {
      th.classList.toggle("sorted", th.dataset.sort === state.sort.key);
      th.classList.toggle("desc", th.dataset.sort === state.sort.key && state.sort.desc);
    });
  }

  function renderBoard(rows) {
    const cols = cfg.statuses.filter((s) => s !== "İptal" || rows.some((t) => t.status === "İptal"));
    $("#boardView").innerHTML = cols.map((s) => {
      const items = rows.filter((t) => t.status === s);
      return `<section class="col" data-status="${esc(s)}">
        <div class="col-head">${statusHtml(s)}<span class="n">${items.length}</span></div>
        ${items.map((t) => {
          const info = dueInfo(t);
          return `<article class="card ${t.done ? "is-done" : ""} ${isLate(t) ? "is-late" : ""}" draggable="true" data-id="${esc(t.id)}">
            <div class="card-top">
              <input class="tick" type="checkbox" data-action="toggle" ${t.done ? "checked" : ""} aria-label="${esc(t.id)} tamamlandı">
              <div class="card-title">${esc(t.title)}</div>
            </div>
            <div class="card-foot">
              <span class="unit-cell">${avatar(t.unit, true)}${esc(t.unit)}</span>
              ${prioHtml(t.priority)}
            </div>
            <div class="card-foot">
              <span>${esc(t.id)}</span>
              ${info ? `<span class="due-rel ${info.cls}">${info.text}</span>` : ""}
            </div>
          </article>`;
        }).join("") || `<div class="col-empty">Buraya sürükleyin</div>`}
      </section>`;
    }).join("");
  }

  function renderFiltered() {
    const rows = filteredTasks();
    $("#resultCount").textContent = `${rows.length} / ${state.tasks.length} görev`;
    $("#listView").hidden = state.view !== "list";
    $("#boardView").hidden = state.view !== "board";
    if (state.view === "list") renderTable(rows); else renderBoard(rows);
    $("#noRows").hidden = rows.length > 0 || state.view === "board";
    renderStats();
    renderUnitProgress();
  }

  function render() {
    fillSelect($("#unitFilter"), state.units, "Tüm birimler");
    fillSelect($("#statusFilter"), cfg.statuses, "Tüm durumlar");
    renderHero();
    renderStatusChart();
    renderUpcoming();
    renderFiltered();
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
      render();
      return false;
    }
  }

  function withStatus(t, status) {
    const u = { ...t, status, done: status === "Tamamlandı" };
    if (u.done && !u.doneDate) u.doneDate = todayIso();
    if (!u.done) u.doneDate = "";
    return u;
  }

  async function toggleDone(id, checkbox) {
    const t = state.tasks.find((x) => x.id === id);
    if (!t) return;
    checkbox.disabled = true;
    const status = checkbox.checked ? "Tamamlandı" : (t.status === "Tamamlandı" ? "Devam Ediyor" : t.status);
    await saveTask(withStatus({ ...t, doneDate: checkbox.checked ? todayIso() : "" }, status), false);
  }

  async function moveTo(id, status) {
    const t = state.tasks.find((x) => x.id === id);
    if (!t || t.status === status) return;
    await saveTask(withStatus(t, status), false);
  }

  // ---- Form -------------------------------------------------------------------

  const FORM_FIELDS = ["title", "unit", "priority", "assigned", "due", "status", "doneDate", "note"];

  function openDialog(task) {
    const form = $("#taskForm");
    state.editingId = task ? task.id : null;
    $("#dialogTitle").textContent = task ? `${task.id} numaralı görevi düzenle` : "Yeni görev";
    fillSelect(form.unit, state.units);
    fillSelect(form.priority, cfg.priorities);
    fillSelect(form.status, cfg.statuses);
    const t = task || {
      title: "", unit: $("#unitFilter").value || state.units[0] || "", priority: "Orta",
      assigned: todayIso(), due: "", status: cfg.statuses[0], doneDate: "", note: ""
    };
    for (const f of FORM_FIELDS) form[f].value = t[f] || "";
    $("#taskDialog").showModal();
    form.title.focus();
  }

  async function submitDialog(ev) {
    ev.preventDefault();
    const form = $("#taskForm");
    const base = state.editingId ? state.tasks.find((t) => t.id === state.editingId) : {};
    const task = { ...base };
    for (const f of FORM_FIELDS) task[f] = form[f].value.trim();
    const final = withStatus(task, task.status);

    const btn = form.querySelector("[type=submit]");
    btn.disabled = true;
    const ok = await saveTask(final, !state.editingId);
    btn.disabled = false;
    if (ok) $("#taskDialog").close();
  }

  // ---- Olaylar ----------------------------------------------------------------

  function bindTooltip() {
    const tip = $("#tooltip");
    document.addEventListener("mouseover", (e) => {
      const el = e.target.closest("[data-tip]");
      if (!el) { tip.hidden = true; return; }
      tip.innerHTML = el.dataset.tip;
      tip.hidden = false;
    });
    document.addEventListener("mousemove", (e) => {
      if (tip.hidden) return;
      const x = Math.min(e.clientX + 14, window.innerWidth - tip.offsetWidth - 8);
      const y = Math.min(e.clientY + 16, window.innerHeight - tip.offsetHeight - 8);
      tip.style.left = x + "px";
      tip.style.top = y + "px";
    });
  }

  function bindBoardDnD() {
    const board = $("#boardView");
    board.addEventListener("dragstart", (e) => {
      const card = e.target.closest(".card");
      if (!card) return;
      card.classList.add("dragging");
      e.dataTransfer.setData("text/plain", card.dataset.id);
      e.dataTransfer.effectAllowed = "move";
    });
    board.addEventListener("dragend", (e) => {
      const card = e.target.closest(".card");
      if (card) card.classList.remove("dragging");
      board.querySelectorAll(".col.drop").forEach((c) => c.classList.remove("drop"));
    });
    board.addEventListener("dragover", (e) => {
      const col = e.target.closest(".col");
      if (!col) return;
      e.preventDefault();
      board.querySelectorAll(".col.drop").forEach((c) => c !== col && c.classList.remove("drop"));
      col.classList.add("drop");
    });
    board.addEventListener("drop", (e) => {
      const col = e.target.closest(".col");
      if (!col) return;
      e.preventDefault();
      col.classList.remove("drop");
      moveTo(e.dataTransfer.getData("text/plain"), col.dataset.status);
    });
  }

  function bucketFilter(key) {
    if (key === "done") setFilters({ status: "Tamamlandı" });
    if (key === "prog") setFilters({ status: "Devam Ediyor" });
    if (key === "late") setFilters({ overdue: true });
    if (key === "todo") setFilters({ status: "Atandı" });
  }

  function bindEvents() {
    for (const id of ["#search", "#unitFilter", "#statusFilter", "#overdueOnly", "#hideDone"]) {
      $(id).addEventListener("input", renderFiltered);
    }

    $("#stats").addEventListener("click", (e) => {
      const card = e.target.closest("[data-stat]");
      if (!card) return;
      const k = card.dataset.stat;
      if (card.classList.contains("active") || k === "total") return setFilters();
      if (k === "done") setFilters({ status: "Tamamlandı" });
      if (k === "open") setFilters({ hideDone: true });
      if (k === "late") setFilters({ overdue: true });
    });

    $("#unitProgress").addEventListener("click", (e) => {
      const row = e.target.closest(".unit-row");
      if (!row) return;
      const sel = $("#unitFilter");
      sel.value = sel.value === row.dataset.unit ? "" : row.dataset.unit;
      renderFiltered();
    });

    $("#statusChart").addEventListener("click", (e) => {
      const el = e.target.closest("[data-bucket]");
      if (el) bucketFilter(el.dataset.bucket);
    });

    $("#upcoming").addEventListener("click", (e) => {
      const li = e.target.closest("li[data-id]");
      if (li) openDialog(state.tasks.find((t) => t.id === li.dataset.id));
    });

    document.querySelector(".tasks thead").addEventListener("click", (e) => {
      const th = e.target.closest("th[data-sort]");
      if (!th) return;
      const key = th.dataset.sort;
      state.sort = { key, desc: state.sort.key === key ? !state.sort.desc : false };
      renderFiltered();
    });

    for (const id of ["#taskBody", "#boardView"]) {
      $(id).addEventListener("click", (e) => {
        const item = e.target.closest("[data-id]");
        if (!item) return;
        const action = e.target.dataset.action;
        if (action === "toggle") return toggleDone(item.dataset.id, e.target);
        if (action === "edit" || (id === "#boardView" && !e.target.closest("input"))) {
          openDialog(state.tasks.find((t) => t.id === item.dataset.id));
        }
      });
    }

    document.querySelector(".seg").addEventListener("click", (e) => {
      const btn = e.target.closest("[data-view]");
      if (!btn) return;
      state.view = btn.dataset.view;
      document.querySelectorAll(".seg-btn").forEach((b) => b.classList.toggle("active", b === btn));
      try { localStorage.setItem("istakip-view", state.view); } catch (_) {}
      renderFiltered();
    });

    $("#newBtn").addEventListener("click", () => openDialog(null));
    $("#taskForm").addEventListener("submit", submitDialog);
    $("#taskForm [data-action=cancel]").addEventListener("click", () => $("#taskDialog").close());
    $("#refreshBtn").addEventListener("click", load);

    const login = () => state.auth.login();
    $("#loginBtn").addEventListener("click", login);
    document.querySelector("[data-action=login]").addEventListener("click", login);
    $("#logoutBtn").addEventListener("click", () => state.auth.logout());

    bindTooltip();
    bindBoardDnD();
  }

  // ---- Başlangıç --------------------------------------------------------------

  async function start() {
    bindEvents();
    try {
      const v = localStorage.getItem("istakip-view");
      if (v === "board" || v === "list") {
        state.view = v;
        document.querySelectorAll(".seg-btn").forEach((b) => b.classList.toggle("active", b.dataset.view === v));
      }
    } catch (_) {}

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

    badge.textContent = "Excel'e bağlı";
    badge.className = "badge live";
    if (typeof msal === "undefined") {
      show("#signedOut");
      toast("Microsoft giriş kütüphanesi yüklenemedi. İnternet bağlantınızı kontrol edip sayfayı yenileyin.", true);
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
