(function () {
  "use strict";

  const cfg = window.APP_CONFIG;
  const { Auth, ExcelStore, SheetsStore, DemoStore, todayIso } = window.IsTakip;
  const $ = (sel) => document.querySelector(sel);

  const state = {
    store: null,
    auth: null,
    tasks: [],
    units: [],
    sort: { key: "due", desc: false },
    view: "list",
    editingId: null,
    subs: [],
    unitInfo: [],
    features: {},
    expanded: new Set(),
    pendingSubs: [],
    unitEdit: null
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

  // ---- Alt paket yardımcıları --------------------------------------------------

  function subNum(x) { return parseInt(String(x.id).split(".").pop(), 10) || 0; }

  function subsOf(taskId) {
    return state.subs.filter((x) => x.taskId === taskId).sort((a, b) => subNum(a) - subNum(b));
  }

  function subDueCls(x) {
    if (x.done) return "done";
    const diff = daysBetween(todayIso(), x.due);
    return diff < 0 ? "late" : diff <= 7 ? "soon" : "ok";
  }

  function subProgressHtml(t, asButton) {
    const list = subsOf(t.id);
    if (!state.features.subtasks || !list.length) return "";
    const d = list.filter((x) => x.done).length;
    const open = state.expanded.has(t.id);
    const inner = `${asButton ? '<span class="chev" aria-hidden="true">▸</span>' : ""}${d}/${list.length} alt paket<span class="mini"><i style="width:${pct(d, list.length)}%"></i></span>`;
    return asButton
      ? `<button type="button" class="sub-progress ${open ? "open" : ""}" data-action="expand" aria-expanded="${open}">${inner}</button>`
      : `<span class="sub-progress">${inner}</span>`;
  }

  function subItemHtml(x, opts) {
    opts = opts || {};
    return `<li class="${x.done ? "done" : ""}" data-sub="${esc(x.id || "")}"${opts.pending != null ? ` data-pending="${opts.pending}"` : ""}>
      <input class="tick" type="checkbox" data-action="sub-toggle" ${x.done ? "checked" : ""} aria-label="Alt paket tamamlandı">
      <span class="sub-title">${esc(x.title)}</span>
      ${x.due ? `<span class="due-rel ${subDueCls(x)}">${fmtDate(x.due)}</span>` : "<span></span>"}
      ${opts.deletable ? '<button type="button" class="sub-del" data-action="sub-delete" title="Alt paketi sil" aria-label="Alt paketi sil">×</button>' : "<span></span>"}
    </li>`;
  }

  let toastTimer;
  function toast(msg, isError) {
    const el = $("#toast");
    el.textContent = msg;
    el.className = "toast show" + (isError ? " error" : "");
    // Popover olarak gösterilince açık bir pencerenin (dialog) da üstünde görünür.
    if (el.showPopover) { try { el.hidePopover(); } catch (_) {} try { el.showPopover(); } catch (_) {} }
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => {
      el.className = "toast";
      if (el.hidePopover) { try { el.hidePopover(); } catch (_) {} }
    }, isError ? 6000 : 2500);
  }

  function show(id) {
    for (const s of ["#signedOut", "#noFile", "#appView", "#loading"]) $(s).hidden = s !== id;
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
    $("#taskBody").innerHTML = rows.map((t) => {
      const open = state.features.subtasks && state.expanded.has(t.id) && subsOf(t.id).length;
      return `
      <tr class="${t.done ? "is-done" : ""} ${isLate(t) ? "is-late" : ""}" data-id="${esc(t.id)}">
        <td class="c-done"><input class="tick" type="checkbox" data-action="toggle" ${t.done ? "checked" : ""} aria-label="${esc(t.id)} tamamlandı"></td>
        <td class="t-id">${esc(t.id)}</td>
        <td class="c-title"><div class="t-title">${esc(t.title)}</div>${t.note ? `<div class="t-note">${esc(t.note)}</div>` : ""}${subProgressHtml(t, true)}</td>
        <td><span class="unit-cell">${avatar(t.unit, true)}${esc(t.unit)}</span></td>
        <td>${prioHtml(t.priority)}</td>
        <td>${dueHtml(t)}</td>
        <td>${statusHtml(t.status)}</td>
        <td class="c-act"><button class="btn small ghost" data-action="edit">Düzenle</button></td>
      </tr>${open ? `
      <tr class="sub-row" data-parent="${esc(t.id)}">
        <td class="c-done"></td>
        <td colspan="7">
          <ul class="sub-list">${subsOf(t.id).map((x) => subItemHtml(x)).join("")}</ul>
          <form class="sub-inline" data-task="${esc(t.id)}">
            <input name="title" type="text" placeholder="Yeni alt paket ekle…" aria-label="Yeni alt paket">
            <button type="submit" class="btn small">Ekle</button>
          </form>
        </td>
      </tr>` : ""}`;
    }).join("");

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
              <div class="card-title">${esc(t.title)}${subProgressHtml(t, false) ? `<div>${subProgressHtml(t, false)}</div>` : ""}</div>
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
      state.unitInfo = data.unitInfo || data.units.map((name) => ({ name, owner: "", email: "" }));
      state.subs = data.subs || [];
      state.features = state.store.features || {};
      $("#unitsBtn").hidden = !state.features.units;
      $("#updateBanner").hidden = !state.features.needsUpdate;
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
      let saved = task;
      if (isNew) {
        saved = await state.store.addTask(task, state.tasks);
        state.tasks.push(saved);
        toast(`${saved.id} eklendi`);
      } else {
        await state.store.updateTask(task);
        const i = state.tasks.findIndex((t) => t.id === task.id);
        state.tasks[i] = task;
        toast(`${task.id} kaydedildi`);
      }
      render();
      return saved;
    } catch (e) {
      console.error(e);
      toast(e.message || String(e), true);
      render();
      return null;
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

  // ---- Alt paket işlemleri --------------------------------------------------------

  async function addSubFor(taskId, sub) {
    const title = String(sub.title || "").trim();
    if (!title) return null;
    try {
      const saved = await state.store.addSub({ taskId, title, due: sub.due || "", done: !!sub.done, doneDate: sub.done ? todayIso() : "" });
      state.subs.push(saved);
      return saved;
    } catch (e) {
      console.error(e);
      toast(e.message || String(e), true);
      return null;
    }
  }

  async function toggleSub(id, checkbox) {
    const x = state.subs.find((y) => y.id === id);
    if (!x) return;
    checkbox.disabled = true;
    const updated = { ...x, done: checkbox.checked, doneDate: checkbox.checked ? todayIso() : "" };
    try {
      await state.store.updateSub(updated);
      Object.assign(x, updated);
      const parent = state.tasks.find((t) => t.id === x.taskId);
      if (updated.done && parent && !parent.done && subsOf(x.taskId).every((y) => y.done)) {
        toast(`Tüm alt paketler bitti. ${parent.id} numaralı görevi de tamamlandı olarak işaretleyebilirsiniz.`);
      }
    } catch (e) {
      console.error(e);
      toast(e.message || String(e), true);
    }
    renderFiltered();
    renderDialogSubs();
  }

  async function deleteSub(id) {
    try {
      await state.store.deleteSub(id);
      state.subs = state.subs.filter((x) => x.id !== id);
    } catch (e) {
      console.error(e);
      toast(e.message || String(e), true);
    }
    renderFiltered();
    renderDialogSubs();
  }

  function renderDialogSubs() {
    const sec = $("#subsSection");
    sec.hidden = !state.features.subtasks;
    if (sec.hidden || !$("#taskDialog").open) return;
    const list = state.editingId ? subsOf(state.editingId) : state.pendingSubs;
    $("#subsList").innerHTML = list.map((x, i) =>
      subItemHtml(x, { deletable: true, pending: state.editingId ? null : i })).join("") ||
      `<li class="sub-empty">Henüz alt paket yok. Aşağıdan ekleyebilirsiniz.</li>`;
    const d = list.filter((x) => x.done).length;
    $("#subsCount").textContent = list.length ? `${d}/${list.length} tamamlandı` : "";
  }

  async function addSubFromDialog() {
    const titleEl = $("#subTitle");
    const title = titleEl.value.trim();
    if (!title) { titleEl.focus(); return; }
    const sub = { title, due: $("#subDue").value, done: false };
    if (state.editingId) {
      const btn = $("#subAddBtn");
      btn.disabled = true;
      const saved = await addSubFor(state.editingId, sub);
      btn.disabled = false;
      if (!saved) return;
      renderFiltered();
    } else {
      state.pendingSubs.push(sub);
    }
    titleEl.value = "";
    $("#subDue").value = "";
    renderDialogSubs();
    titleEl.focus();
  }

  async function onDialogSubsClick(e) {
    const li = e.target.closest("li[data-sub]");
    const action = e.target.dataset.action;
    if (!li || !action) return;
    if (li.dataset.pending !== undefined) {
      const i = Number(li.dataset.pending);
      if (action === "sub-toggle") state.pendingSubs[i].done = e.target.checked;
      if (action === "sub-delete") state.pendingSubs.splice(i, 1);
      renderDialogSubs();
      return;
    }
    if (action === "sub-toggle") return toggleSub(li.dataset.sub, e.target);
    if (action === "sub-delete") {
      // İki adımlı silme: ilk tıklama onay ister
      if (!e.target.classList.contains("confirm")) {
        e.target.classList.add("confirm");
        e.target.textContent = "Sil?";
        e.target.title = "Silmek için tekrar tıklayın";
        return;
      }
      await deleteSub(li.dataset.sub);
    }
  }

  // ---- Birim yönetimi ----------------------------------------------------------

  function unitsError(msg) {
    const el = $("#unitsError");
    el.textContent = msg || "";
    el.hidden = !msg;
  }

  function renderUnits() {
    $("#unitsBody").innerHTML = state.unitInfo.map((u) => {
      const n = state.tasks.filter((t) => t.unit === u.name).length;
      return `<tr class="${state.unitEdit === u.name ? "editing" : ""}" data-unit="${esc(u.name)}">
        <td><span class="unit-cell">${avatar(u.name, true)}${esc(u.name)}</span></td>
        <td>${u.owner ? esc(u.owner) : '<span class="muted">—</span>'}</td>
        <td>${u.email ? esc(u.email) : '<span class="muted">—</span>'}</td>
        <td class="num">${n}</td>
        <td class="acts">
          <button type="button" class="btn small ghost" data-action="unit-edit">Düzenle</button>
          <button type="button" class="btn small ghost danger" data-action="unit-delete"${n ? ` disabled title="Bu birime bağlı ${n} iş var"` : ""}>Sil</button>
        </td>
      </tr>`;
    }).join("") || `<tr><td colspan="5" class="muted">Henüz birim yok. Aşağıdan ekleyin.</td></tr>`;
  }

  function resetUnitForm() {
    state.unitEdit = null;
    $("#unitName").value = "";
    $("#unitOwner").value = "";
    $("#unitEmail").value = "";
    $("#unitFormTitle").textContent = "Yeni birim ekle";
    $("#unitSubmit").textContent = "Birimi ekle";
    $("#unitCancelEdit").hidden = true;
    unitsError("");
  }

  function editUnit(name) {
    const u = state.unitInfo.find((x) => x.name === name);
    if (!u) return;
    state.unitEdit = name;
    $("#unitName").value = u.name;
    $("#unitOwner").value = u.owner || "";
    $("#unitEmail").value = u.email || "";
    $("#unitFormTitle").textContent = `"${u.name}" birimini düzenle`;
    $("#unitSubmit").textContent = "Değişiklikleri kaydet";
    $("#unitCancelEdit").hidden = false;
    unitsError("");
    renderUnits();
    $("#unitName").focus();
  }

  function openUnits() {
    resetUnitForm();
    renderUnits();
    $("#unitsDialog").showModal();
  }

  async function submitUnit(ev) {
    ev.preventDefault();
    unitsError("");
    const unit = { name: $("#unitName").value.trim(), owner: $("#unitOwner").value.trim(), email: $("#unitEmail").value.trim() };
    if (!unit.name) { unitsError("Birim adını girin."); return; }
    const oldName = state.unitEdit;
    const btn = $("#unitSubmit");
    btn.disabled = true;
    try {
      const r = await state.store.saveUnit(oldName, unit);
      if (oldName) {
        const i = state.unitInfo.findIndex((u) => u.name === oldName);
        state.unitInfo[i] = r.unit;
        if (oldName !== r.unit.name) state.tasks.forEach((t) => { if (t.unit === oldName) t.unit = r.unit.name; });
      } else {
        state.unitInfo.push(r.unit);
      }
      state.units = state.unitInfo.map((u) => u.name);
      toast(oldName
        ? (r.renamed ? `Birim güncellendi; ${r.renamed} iş yeni birim adına taşındı` : "Birim güncellendi")
        : `"${r.unit.name}" eklendi`);
      resetUnitForm();
      renderUnits();
      render();
    } catch (e) {
      unitsError(e.message || String(e));
    } finally {
      btn.disabled = false;
    }
  }

  async function onUnitsClick(e) {
    const btn = e.target.closest("button[data-action]");
    const row = e.target.closest("tr[data-unit]");
    if (!btn || !row) return;
    const name = row.dataset.unit;
    if (btn.dataset.action === "unit-edit") return editUnit(name);
    if (btn.dataset.action === "unit-delete") {
      if (!btn.classList.contains("confirm")) {
        btn.classList.add("confirm");
        btn.textContent = "Emin misiniz?";
        return;
      }
      btn.disabled = true;
      try {
        await state.store.deleteUnit(name);
        state.unitInfo = state.unitInfo.filter((u) => u.name !== name);
        state.units = state.unitInfo.map((u) => u.name);
        if (state.unitEdit === name) resetUnitForm();
        toast(`"${name}" silindi`);
        render();
      } catch (err) {
        unitsError(err.message || String(err));
      }
      renderUnits();
    }
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
    state.pendingSubs = [];
    $("#subTitle").value = "";
    $("#subDue").value = "";
    $("#taskDialog").showModal();
    renderDialogSubs();
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
    const isNew = !state.editingId;
    const saved = await saveTask(final, isNew);
    if (saved && isNew && state.pendingSubs.length) {
      for (const sub of state.pendingSubs) await addSubFor(saved.id, sub);
      state.expanded.add(saved.id);
      renderFiltered();
    }
    btn.disabled = false;
    if (saved) $("#taskDialog").close();
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

    $("#taskBody").addEventListener("click", (e) => {
      const subLi = e.target.closest("li[data-sub]");
      if (subLi && e.target.dataset.action === "sub-toggle") toggleSub(subLi.dataset.sub, e.target);
      const exp = e.target.closest("[data-action=expand]");
      const row = e.target.closest("tr[data-id]");
      if (exp && row) {
        const id = row.dataset.id;
        if (state.expanded.has(id)) state.expanded.delete(id); else state.expanded.add(id);
        renderFiltered();
      }
    });
    $("#taskBody").addEventListener("submit", async (e) => {
      const form = e.target.closest("form.sub-inline");
      if (!form) return;
      e.preventDefault();
      const input = form.querySelector("input");
      if (!input.value.trim()) return;
      input.disabled = true;
      const saved = await addSubFor(form.dataset.task, { title: input.value });
      input.disabled = false;
      if (saved) {
        renderFiltered();
        const again = document.querySelector(`form.sub-inline[data-task="${CSS.escape(form.dataset.task)}"] input`);
        if (again) again.focus();
      }
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
    $("#subAddBtn").addEventListener("click", addSubFromDialog);
    $("#subTitle").addEventListener("keydown", (e) => { if (e.key === "Enter") { e.preventDefault(); addSubFromDialog(); } });
    $("#subsList").addEventListener("click", onDialogSubsClick);
    $("#unitsBtn").addEventListener("click", openUnits);
    $("#unitsBody").addEventListener("click", onUnitsClick);
    $("#unitForm").addEventListener("submit", submitUnit);
    $("#unitCancelEdit").addEventListener("click", () => { resetUnitForm(); renderUnits(); });
    $("#unitsDialog [data-action=close-units]").addEventListener("click", () => $("#unitsDialog").close());
    $("#taskForm").addEventListener("submit", submitDialog);
    $("#taskForm [data-action=cancel]").addEventListener("click", () => $("#taskDialog").close());
    $("#refreshBtn").addEventListener("click", load);
    $("#settingsBtn").addEventListener("click", openSettings);
    document.querySelectorAll("[data-action=settings]").forEach((b) => b.addEventListener("click", openSettings));
    $("#settingsForm").addEventListener("submit", submitSettings);
    $("#disconnectBtn").addEventListener("click", disconnect);
    $("#settingsForm [data-action=cancel]").addEventListener("click", () => $("#settingsDialog").close());

    const login = () => state.auth.login();
    $("#loginBtn").addEventListener("click", login);
    document.querySelector("[data-action=login]").addEventListener("click", login);
    $("#logoutBtn").addEventListener("click", () => state.auth.logout());

    bindTooltip();
    bindBoardDnD();
  }

  // ---- Ayarlar (bağlantı bilgileri yalnızca bu tarayıcıda saklanır) ------------

  const SETTINGS_KEY = "istakip-ayarlar";

  function readSettings() {
    try { return JSON.parse(localStorage.getItem(SETTINGS_KEY)) || {}; } catch (_) { return {}; }
  }

  function writeSettings(settings) {
    try { localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings)); return true; } catch (_) { return false; }
  }

  function openSettings() {
    const saved = readSettings();
    $("#scriptUrlInput").value = saved.scriptUrl || "";
    $("#tokenInput").value = saved.token || "";
    $("#shareUrlInput").value = cfg.shareUrl || "";
    $("#shareUrlRow").hidden = !cfg.clientId;
    $("#disconnectBtn").hidden = !saved.scriptUrl;
    settingsError("");
    $("#settingsDialog").showModal();
    (saved.scriptUrl ? $("#tokenInput") : $("#scriptUrlInput")).focus();
  }

  function settingsError(msg) {
    const el = $("#settingsError");
    el.textContent = msg || "";
    el.hidden = !msg;
  }

  async function submitSettings(ev) {
    ev.preventDefault();
    settingsError("");
    const scriptUrl = $("#scriptUrlInput").value.trim();
    const token = $("#tokenInput").value.trim();
    const shareUrl = $("#shareUrlInput").value.trim();
    const settings = readSettings();

    if (scriptUrl || token) {
      if (!/^https:\/\/script\.google(usercontent)?\.com\/.+\/exec$/i.test(scriptUrl)) {
        settingsError("Web uygulaması adresi https://script.google.com/macros/s/…/exec biçiminde olmalı. Apps Script'te Dağıt > Dağıtımları yönet ekranından kopyalayın.");
        return;
      }
      if (!token) { settingsError("Bağlantı anahtarını girin. E-Tabloda İş Takip > Bağlantı anahtarını göster menüsünden alabilirsiniz."); return; }
      const store = new SheetsStore(scriptUrl, token);
      const btn = $("#settingsForm [type=submit]");
      btn.disabled = true;
      btn.textContent = "Bağlanıyor…";
      try {
        await store.load();
      } catch (e) {
        settingsError(e.message || String(e));
        return;
      } finally {
        btn.disabled = false;
        btn.textContent = "Kaydet ve bağlan";
      }
      Object.assign(settings, { scriptUrl, token });
    } else if (cfg.clientId && shareUrl) {
      if (!/^https:\/\/[^/]+\.(sharepoint\.com|1drv\.ms|onedrive\.live\.com)\//i.test(shareUrl)) {
        settingsError("Bu bir OneDrive / SharePoint linki gibi görünmüyor. Excel'de Paylaş > Bağlantıyı kopyala ile alınan linki yapıştırın.");
        return;
      }
      settings.shareUrl = shareUrl;
    } else {
      settingsError("Web uygulaması adresini ve bağlantı anahtarını girin.");
      return;
    }

    if (!writeSettings(settings)) {
      toast("Tarayıcı ayarları kaydetmeye izin vermedi; bağlantı yalnızca bu oturumda geçerli.", true);
    }
    $("#settingsDialog").close();
    await start(settings);
  }

  async function disconnect() {
    const settings = readSettings();
    delete settings.scriptUrl;
    delete settings.token;
    writeSettings(settings);
    $("#settingsDialog").close();
    toast("Google E-Tablolar bağlantısı kaldırıldı");
    await start(settings);
  }

  async function connectExcel() {
    if (!state.auth || !state.auth.account) return;
    if (!cfg.shareUrl && !cfg.filePath) { show("#noFile"); return; }
    state.store = new ExcelStore(cfg, state.auth);
    await load();
  }

  function setBadge(text, cls, title) {
    const badge = $("#modeBadge");
    badge.textContent = text;
    badge.className = "badge " + cls;
    badge.title = title || "";
  }

  // ---- Başlangıç --------------------------------------------------------------

  async function start(saved) {
    saved = saved || readSettings();
    $("#settingsBtn").hidden = false;
    $("#demoBanner").hidden = true;

    // 1) Google E-Tablolar bağlıysa onu kullan
    if (saved.scriptUrl && saved.token) {
      state.store = new SheetsStore(saved.scriptUrl, saved.token);
      setBadge("Google E-Tablolar'a bağlı", "live");
      await load();
      return;
    }

    // 2) Office 365 yapılandırılmışsa Microsoft girişi
    if (cfg.clientId) {
      if (saved.shareUrl) cfg.shareUrl = saved.shareUrl;
      setBadge("Excel'e bağlı", "live");
      if (typeof msal === "undefined") {
        show("#signedOut");
        toast("Microsoft giriş kütüphanesi yüklenemedi. İnternet bağlantınızı kontrol edip sayfayı yenileyin.", true);
        return;
      }
      try {
        if (!state.auth) { state.auth = new Auth(cfg); await state.auth.init(); }
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
      await connectExcel();
      return;
    }

    // 3) Hiçbiri yoksa örnek verilerle demo
    state.store = new DemoStore(cfg);
    setBadge("Demo modu", "demo", "Ayarlar'dan Google E-Tablonuzu bağlayın");
    $("#demoBanner").hidden = false;
    await load();
  }

  function init() {
    bindEvents();
    try {
      const v = localStorage.getItem("istakip-view");
      if (v === "board" || v === "list") {
        state.view = v;
        document.querySelectorAll(".seg-btn").forEach((b) => b.classList.toggle("active", b.dataset.view === v));
      }
    } catch (_) {}
    start();
  }

  init();
})();
