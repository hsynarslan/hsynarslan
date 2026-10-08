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
    unitEdit: null,
    dialogBase: null,
    actor: "",
    loadedAt: 0,
    loading: false,
    ganttZoom: "week",
    urlFilters: null
  };

  const NAME_KEY = "istakip-ad";
  const REFRESH_MS = 5 * 60 * 1000;
  const GANTT_ZOOM = { day: 32, week: 14, month: 5 };

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

  function addDays(iso, n) {
    const [y, m, d] = iso.split("-").map(Number);
    const dt = new Date(Date.UTC(y, m - 1, d + n));
    return dt.toISOString().slice(0, 10);
  }

  // Ay sonu taşmasını önler: 31 Ocak + 1 ay = 28/29 Şubat
  function addMonths(iso, n) {
    const [y, m, d] = iso.split("-").map(Number);
    const last = new Date(Date.UTC(y, m - 1 + n + 1, 0)).getUTCDate();
    return new Date(Date.UTC(y, m - 1 + n, Math.min(d, last))).toISOString().slice(0, 10);
  }

  function shiftByRepeat(iso, repeat) {
    if (!iso) return "";
    if (repeat === "Haftalık") return addDays(iso, 7);
    if (repeat === "Aylık") return addMonths(iso, 1);
    if (repeat === "Yıllık") return addMonths(iso, 12);
    return iso;
  }

  function safeLink(url) {
    return /^https?:\/\/[^\s"'<>]+$/i.test(String(url || "").trim()) ? String(url).trim() : "";
  }

  function linkHtml(t) {
    const url = safeLink(t.link);
    return url ? `<a class="t-link" href="${esc(url)}" target="_blank" rel="noopener" title="Bağlantıyı aç" aria-label="Bağlantıyı aç"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></a>` : "";
  }

  function repeatHtml(t) {
    return t.repeat ? `<span class="t-repeat" title="${esc(t.repeat)} tekrar eder"><svg viewBox="0 0 24 24" width="12" height="12" aria-hidden="true"><path d="M17 2l3 3-3 3M4 11V9a4 4 0 0 1 4-4h12M7 22l-3-3 3-3M20 13v2a4 4 0 0 1-4 4H4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>${esc(t.repeat)}</span>` : "";
  }

  // Zamanında bitirme oranı ve ortalama gecikme (biten geç işler + hâlâ geciken işler)
  function metrics(list) {
    const today = todayIso();
    const finished = list.filter((t) => t.done && t.due && t.doneDate);
    const onTime = finished.filter((t) => t.doneDate <= t.due).length;
    const delays = [
      ...finished.filter((t) => t.doneDate > t.due).map((t) => daysBetween(t.due, t.doneDate)),
      ...list.filter(isLate).map((t) => daysBetween(t.due, today))
    ];
    return {
      onTimePct: finished.length ? pct(onTime, finished.length) : null,
      finished: finished.length,
      avgDelay: delays.length ? Math.round((delays.reduce((a, b) => a + b, 0) / delays.length) * 10) / 10 : 0,
      delayed: delays.length
    };
  }

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
    for (const s of ["#lockView", "#signedOut", "#noFile", "#appView", "#loading"]) $(s).hidden = s !== id;
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
    const m = metrics(active);
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
        ${m.onTimePct !== null ? `<span title="Termini olan ve biten ${m.finished} işten zamanında bitenlerin oranı">Zamanında bitirme <b>%${m.onTimePct}</b></span>` : ""}
        ${m.delayed ? `<span title="Geç biten ve hâlâ geciken ${m.delayed} işin ortalaması">Ort. gecikme <b>${String(m.avgDelay).replace(".", ",")} gün</b></span>` : ""}
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
      const um = metrics(list);
      const tip = `<b>${esc(u)}</b><br>${n.done} tamamlandı · ${n.prog} devam ediyor<br>${n.late} gecikmiş · ${n.todo} başlanmadı` +
        (um.onTimePct !== null ? `<br>Zamanında bitirme %${um.onTimePct}` : "") +
        (um.delayed ? ` · ort. gecikme ${String(um.avgDelay).replace(".", ",")} gün` : "");
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
        <td class="c-title"><div class="t-title">${esc(t.title)}${linkHtml(t)}${repeatHtml(t)}</div>${t.note ? `<div class="t-note">${esc(t.note)}</div>` : ""}${subProgressHtml(t, true)}</td>
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
              <div class="card-title">${esc(t.title)}${linkHtml(t)}${repeatHtml(t)}${subProgressHtml(t, false) ? `<div>${subProgressHtml(t, false)}</div>` : ""}</div>
            </div>
            <div class="card-foot">
              <span class="unit-cell">${avatar(t.unit, true)}${esc(t.unit)}</span>
              ${prioHtml(t.priority)}
            </div>
            <div class="card-foot">
              <span>${esc(t.id)}</span>
              ${info ? `<span class="due-rel ${info.cls}">${info.text}</span>` : ""}
            </div>
            <label class="card-move">Taşı:
              <select data-action="move" aria-label="${esc(t.id)} durumunu değiştir">
                ${cfg.statuses.map((x) => `<option${x === t.status ? " selected" : ""}>${esc(x)}</option>`).join("")}
              </select>
            </label>
          </article>`;
        }).join("") || `<div class="col-empty">Buraya sürükleyin</div>`}
      </section>`;
    }).join("");
  }

  // ---- Görünüm: zaman çizelgesi (Gantt) ------------------------------------------
  // Çubuk: atama tarihinden termine planlanan süre, rengi duruma göre. Kırmızı uzantı: termini aşan kısım
  // (biten işte tamamlanma tarihine, açık işte bugüne kadar). Elmas: alt paket terminleri.

  function ganttItems(rows) {
    const today = todayIso();
    return rows.filter((t) => t.assigned || t.due).map((t) => {
      let start = t.assigned || t.due, end = t.due || t.assigned;
      if (end < start) [start, end] = [end, start];
      let over = "";
      if (t.done && t.doneDate && t.due && t.doneDate > t.due) over = t.doneDate;
      else if (isLate(t)) over = today;
      return { t, start, end, over };
    }).sort((a, b) => (a.start < b.start ? -1 : a.start > b.start ? 1 : a.end < b.end ? -1 : 1));
  }

  function renderGantt(rows) {
    const el = $("#ganttView");
    const items = ganttItems(rows);
    const zoom = state.ganttZoom;
    const controls = `<div class="g-tools">
        <div class="seg" role="group" aria-label="Ölçek">
          ${[["day", "Gün"], ["week", "Hafta"], ["month", "Ay"]].map(([k, l]) =>
            `<button type="button" class="seg-btn ${zoom === k ? "active" : ""}" data-zoom="${k}">${l}</button>`).join("")}
        </div>
        <button type="button" class="btn small" data-gantt="today">Bugüne git</button>
        <ul class="g-legend" aria-label="Açıklama">
          <li><i class="g-sw done"></i>Tamamlandı</li><li><i class="g-sw prog"></i>Devam ediyor</li>
          <li><i class="g-sw todo"></i>Başlanmadı</li><li><i class="g-sw over"></i>Termin aşımı</li>
          <li><i class="g-sw sub"></i>Alt paket</li>
        </ul>
      </div>`;
    if (!items.length) {
      el.innerHTML = controls + `<p class="no-rows">Atama ya da termin tarihi olan görev yok.</p>`;
      return;
    }
    const today = todayIso();
    let min = items.reduce((m, i) => (i.start < m ? i.start : m), today);
    let max = items.reduce((m, i) => [i.end, i.over || i.end].reduce((a, b) => (b > a ? b : a), m), today);
    min = addDays(min, -3);
    const dow = (new Date(min + "T00:00:00Z").getUTCDay() + 6) % 7; // 0 = Pazartesi
    min = addDays(min, -dow);
    max = addDays(max, 10);
    const day = GANTT_ZOOM[zoom];
    const days = daysBetween(min, max) + 1;
    const width = days * day;
    const x = (iso) => daysBetween(min, iso) * day;

    // Üst ölçek: aylar ve günler / haftalar
    let months = "", ticks = "";
    for (let d = min; d <= max; ) {
      const [y, m] = d.split("-").map(Number);
      const next = new Date(Date.UTC(y, m, 1)).toISOString().slice(0, 10);
      const end = next > max ? addDays(max, 1) : next;
      const w = daysBetween(d, end) * day;
      months += `<span class="g-month" style="left:${x(d)}px;width:${w}px">${w > 44 ? `${MONTHS[m - 1]} ${y}` : ""}</span>`;
      d = next;
    }
    for (let d = min, i = 0; d <= max; d = addDays(d, 1), i++) {
      const wd = i % 7;
      if (zoom === "day") ticks += `<span class="g-tick${wd > 4 ? " we" : ""}${d === today ? " now" : ""}" style="left:${x(d)}px;width:${day}px">${+d.slice(8)}</span>`;
      else if (wd === 0) ticks += `<span class="g-tick" style="left:${x(d)}px;width:${day * 7}px">${zoom === "week" ? `${+d.slice(8)} ${MONTHS[+d.slice(5, 7) - 1]}` : +d.slice(8)}</span>`;
    }

    const body = items.map(({ t, start, end, over }) => {
      const b = bucketOf(t) === "late" ? (t.status === "Devam Ediyor" ? "prog" : "todo") : bucketOf(t);
      const left = x(start), w = Math.max((daysBetween(start, end) + 1) * day, 6);
      const tip = `<b>${esc(t.id)} · ${esc(t.title)}</b><br>${esc(t.unit)}<br>${fmtDate(start)} → ${fmtDate(end)}` +
        (t.done ? `<br>Bitti: ${fmtDate(t.doneDate) || "—"}` : "") + (dueInfo(t) ? `<br>${dueInfo(t).text}` : "");
      const overHtml = over ? `<span class="g-over" style="left:${left + w + 2}px;width:${Math.max(daysBetween(end, over) * day - 2, 3)}px"></span>` : "";
      const subs = state.features.subtasks ? subsOf(t.id).filter((s) => s.due && s.due >= min && s.due <= max) : [];
      const subHtml = subs.map((s) => `<span class="g-sub${s.done ? " done" : ""}" style="left:${x(s.due) + day / 2 - 5}px" data-tip="${esc(`<b>${s.id}</b> ${s.title}<br>${fmtDate(s.due)}${s.done ? " · bitti" : ""}`)}"></span>`).join("");
      return `<div class="g-row${t.done ? " is-done" : ""}" data-id="${esc(t.id)}">
        <div class="g-label">${avatar(t.unit, true)}<span class="g-title">${esc(t.title)}</span></div>
        <div class="g-track"><span class="g-bar ${b}" style="left:${left}px;width:${w}px" data-tip="${esc(tip)}"></span>${overHtml}${subHtml}</div>
      </div>`;
    }).join("");

    const prevScroll = el.querySelector(".g-scroll");
    const keep = prevScroll && prevScroll.dataset.scale === zoom ? prevScroll.scrollLeft : null;
    el.innerHTML = controls + `<div class="g-scroll" data-scale="${zoom}" style="--day:${day}px;--w:${width}px">
      <div class="g-inner">
        <div class="g-head"><div class="g-corner">Görev</div><div class="g-scale"><div class="g-months">${months}</div><div class="g-ticks">${ticks}</div></div></div>
        <div class="g-body">${body}<span class="g-today" style="left:calc(var(--label) + ${x(today) + day / 2}px)" title="Bugün"></span></div>
      </div>
    </div>`;
    const sc = el.querySelector(".g-scroll");
    sc.dataset.todayX = x(today);
    sc.scrollLeft = keep !== null ? keep : Math.max(x(today) - sc.clientWidth / 3, 0);
  }

  // ---- Adres çubuğundaki filtreler: görünüm bağlantı olarak paylaşılabilir --------------

  function readUrlFilters() {
    const p = new URLSearchParams(location.search);
    return {
      unit: p.get("birim") || "", status: p.get("durum") || "", q: p.get("ara") || "",
      overdue: p.get("geciken") === "1", hideDone: p.get("gizle") === "1", view: p.get("gorunum") || ""
    };
  }

  function syncUrl() {
    const p = new URLSearchParams();
    const set = (k, v) => { if (v) p.set(k, v); };
    set("birim", $("#unitFilter").value);
    set("durum", $("#statusFilter").value);
    set("ara", $("#search").value.trim());
    set("geciken", $("#overdueOnly").checked ? "1" : "");
    set("gizle", $("#hideDone").checked ? "1" : "");
    set("gorunum", state.view !== "list" ? { board: "pano", gantt: "zaman" }[state.view] : "");
    const qs = p.toString();
    const url = location.pathname + (qs ? "?" + qs : "") + location.hash;
    if (url !== location.pathname + location.search + location.hash) {
      try { history.replaceState(null, "", url); } catch (_) {}
    }
  }

  function setView(view) {
    state.view = ["list", "board", "gantt"].includes(view) ? view : "list";
    document.querySelectorAll(".toolbar .seg-btn").forEach((b) => b.classList.toggle("active", b.dataset.view === state.view));
  }

  function renderFiltered() {
    const rows = filteredTasks();
    $("#resultCount").textContent = `${rows.length} / ${state.tasks.length} görev`;
    $("#listView").hidden = state.view !== "list";
    $("#boardView").hidden = state.view !== "board";
    $("#ganttView").hidden = state.view !== "gantt";
    if (state.view === "list") renderTable(rows);
    else if (state.view === "board") renderBoard(rows);
    else renderGantt(rows);
    $("#noRows").hidden = rows.length > 0 || state.view !== "list";
    renderStats();
    renderUnitProgress();
    syncUrl();
  }

  function render() {
    fillSelect($("#unitFilter"), state.units, "Tüm birimler");
    fillSelect($("#statusFilter"), cfg.statuses, "Tüm durumlar");
    if (state.urlFilters) {
      const f = state.urlFilters;
      state.urlFilters = null;
      if (state.units.includes(f.unit)) $("#unitFilter").value = f.unit;
      if (cfg.statuses.includes(f.status)) $("#statusFilter").value = f.status;
      $("#search").value = f.q;
      $("#overdueOnly").checked = f.overdue;
      $("#hideDone").checked = f.hideDone;
    }
    renderHero();
    renderStatusChart();
    renderUpcoming();
    renderFiltered();
  }

  // ---- Veri işlemleri ---------------------------------------------------------

  // quiet: arka planda yenile (yükleniyor ekranı gösterme, hata olursa sessiz kal)
  async function load(quiet) {
    if (!state.store || state.loading) return;
    state.loading = true;
    state.store.actor = state.actor;
    if (!quiet) show("#loading");
    try {
      const data = await state.store.load();
      state.loadedAt = Date.now();
      state.tasks = data.tasks;
      state.units = data.units;
      state.unitInfo = data.unitInfo || data.units.map((name) => ({ name, owner: "", email: "" }));
      state.subs = data.subs || [];
      state.features = state.store.features || {};
      $("#unitsBtn").hidden = !state.features.units;
      $("#updateBanner").hidden = !state.features.needsUpdate;
      document.querySelectorAll("[data-feature]").forEach((el) => { el.hidden = !state.features[el.dataset.feature]; });
      if (state.auth && state.auth.account) state.store.actor = state.auth.account.name || state.auth.account.username;
      renderWho();
      render();
      show("#appView");
      if (state.features.history && !state.actor && !(state.auth && state.auth.account)) askName();
    } catch (e) {
      console.error(e);
      if (!quiet) { show("#appView"); toast(e.message || String(e), true); }
    } finally {
      state.loading = false;
    }
  }

  // Sekmeye dönünce ve açıkken 5 dakikada bir başkalarının değişikliklerini getir
  function startAutoRefresh() {
    const due = () => document.visibilityState === "visible" && state.store && !$("#appView").hidden &&
      !document.querySelector("dialog[open]") && Date.now() - state.loadedAt > 60000;
    document.addEventListener("visibilitychange", () => { if (due()) load(true); });
    setInterval(() => { if (due() && Date.now() - state.loadedAt > REFRESH_MS) load(true); }, 30000);
  }

  // ---- Kim düzenliyor ----------------------------------------------------------------

  function setActor(name) {
    state.actor = String(name || "").trim().slice(0, 60);
    try { localStorage.setItem(NAME_KEY, state.actor); } catch (_) {}
    if (state.store) state.store.actor = state.actor;
    renderWho();
  }

  function renderWho() {
    const msAccount = state.auth && state.auth.account;
    $("#whoBtn").hidden = !!msAccount || !state.features.history;
    $("#whoName").textContent = state.actor || "Adınızı girin";
  }

  function askName() {
    $("#nameInput").value = state.actor;
    $("#nameDialog").showModal();
    $("#nameInput").focus();
  }

  // base: görevin düzenlemeye başlandığı andaki hali (aynı anda düzenleme kontrolü için)
  async function saveTask(task, isNew, base) {
    try {
      let saved = task;
      if (isNew) {
        saved = await state.store.addTask(task, state.tasks);
        state.tasks.push(saved);
        toast(`${saved.id} eklendi`);
      } else {
        const i = state.tasks.findIndex((t) => t.id === task.id);
        const prev = base || state.tasks[i];
        saved = { ...task, ...(await state.store.updateTask(task, state.features.conflicts ? (prev.updatedAt || "") : undefined)) };
        state.tasks[i] = saved;
        toast(`${task.id} kaydedildi`);
        if (!prev.done && saved.done && saved.repeat) await spawnNext(saved);
      }
      render();
      return saved;
    } catch (e) {
      console.error(e);
      toast(e.message || String(e), true);
      if (e.conflict) {
        await load(true);
        if ($("#taskDialog").open) $("#taskDialog").close();
      } else {
        render();
      }
      return null;
    }
  }

  // Tekrar eden görev bitince bir sonraki dönemin görevini oluştur
  async function spawnNext(t) {
    const due = shiftByRepeat(t.due || t.doneDate || todayIso(), t.repeat);
    const assigned = t.assigned ? shiftByRepeat(t.assigned, t.repeat) : todayIso();
    if (state.tasks.some((x) => x.id !== t.id && x.title === t.title && x.unit === t.unit && x.due === due)) return;
    const next = {
      title: t.title, unit: t.unit, priority: t.priority, assigned, due, status: cfg.statuses[0],
      done: false, doneDate: "", note: t.note || "", repeat: t.repeat, link: t.link || ""
    };
    try {
      const saved = await state.store.addTask(next, state.tasks);
      state.tasks.push(saved);
      toast(`Tekrar eden görev: ${saved.id} oluşturuldu (termin ${fmtDate(due)})`);
    } catch (e) {
      console.error(e);
      toast("Sonraki dönemin görevi oluşturulamadı: " + (e.message || e), true);
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

  const FORM_FIELDS = ["title", "unit", "priority", "assigned", "due", "status", "doneDate", "note", "repeat", "link"];

  function openDialog(task) {
    const form = $("#taskForm");
    state.editingId = task ? task.id : null;
    $("#dialogTitle").textContent = task ? `${task.id} numaralı görevi düzenle` : "Yeni görev";
    fillSelect(form.unit, state.units);
    fillSelect(form.priority, cfg.priorities);
    fillSelect(form.status, cfg.statuses);
    const t = task || {
      title: "", unit: $("#unitFilter").value || state.units[0] || "", priority: "Orta",
      assigned: todayIso(), due: "", status: cfg.statuses[0], doneDate: "", note: "", repeat: "", link: ""
    };
    for (const f of FORM_FIELDS) form[f].value = t[f] || "";
    state.dialogBase = task ? { ...task } : null;
    state.pendingSubs = [];
    $("#subTitle").value = "";
    $("#subDue").value = "";
    $("#taskDialog").showModal();
    renderDialogSubs();
    loadHistory();
    form.title.focus();
  }

  // ---- Yorumlar ve geçmiş -----------------------------------------------------------

  function fmtStamp(at) {
    const d = new Date(at);
    return isNaN(d) ? "" : d.toLocaleString("tr-TR", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" });
  }

  function renderHistory(entries) {
    $("#histCount").textContent = entries.length ? `${entries.filter((h) => h.action === "Yorum").length} yorum · ${entries.length} kayıt` : "";
    $("#histList").innerHTML = entries.slice().reverse().map((h) => `
      <li class="${h.action === "Yorum" ? "is-comment" : ""}">
        <div class="hist-meta"><b>${esc(h.by || "Bilinmeyen")}</b> · ${esc(h.action)}${h.id && h.id !== state.editingId ? ` · ${esc(h.id)}` : ""}<span>${fmtStamp(h.at)}</span></div>
        ${h.detail ? `<div class="hist-detail">${esc(h.detail)}</div>` : ""}
      </li>`).join("") || `<li class="sub-empty">Henüz kayıt yok.</li>`;
  }

  async function loadHistory() {
    const sec = $("#histSection");
    sec.hidden = !state.features.history || !state.editingId;
    if (sec.hidden) return;
    const id = state.editingId;
    $("#histList").innerHTML = `<li class="sub-empty">Yükleniyor…</li>`;
    $("#commentInput").value = "";
    try {
      const entries = await state.store.history(id);
      if (state.editingId === id) { state.history = entries; renderHistory(entries); }
    } catch (e) {
      $("#histList").innerHTML = `<li class="sub-empty">Geçmiş yüklenemedi: ${esc(e.message || e)}</li>`;
    }
  }

  async function addComment() {
    const input = $("#commentInput");
    const text = input.value.trim();
    if (!text || !state.editingId) { input.focus(); return; }
    if (!state.actor && !(state.auth && state.auth.account)) { askName(); return; }
    const btn = $("#commentBtn");
    btn.disabled = true;
    try {
      const entry = await state.store.addComment(state.editingId, text);
      state.history = [...(state.history || []), entry];
      renderHistory(state.history);
      input.value = "";
    } catch (e) {
      toast(e.message || String(e), true);
    } finally {
      btn.disabled = false;
      input.focus();
    }
  }

  // ---- Rapor: yazdır, CSV, bağlantı ----------------------------------------------------

  function exportCsv() {
    const rows = filteredTasks();
    const cols = [["id", "GörevNo"], ["assigned", "AtamaTarihi"], ["unit", "Birim"], ["title", "GörevTanımı"],
      ["priority", "Öncelik"], ["due", "TerminTarihi"], ["status", "Durum"], ["doneDate", "TamamlanmaTarihi"],
      ["late", "GecikmeGün"], ["subs", "AltPaket"], ["repeat", "Tekrar"], ["note", "Not"], ["link", "Bağlantı"]];
    const today = todayIso();
    const cell = (v) => {
      const s = String(v ?? "");
      return /[;"\n\r]/.test(s) || /^[=+\-@]/.test(s) ? `"${(/^[=+\-@]/.test(s) ? "'" : "") + s.replace(/"/g, '""')}"` : s;
    };
    const value = (t, k) => {
      if (k === "assigned" || k === "due" || k === "doneDate") return fmtDate(t[k]);
      if (k === "late") return isLate(t) ? daysBetween(t.due, today) : t.done && t.due && t.doneDate > t.due ? daysBetween(t.due, t.doneDate) : "";
      if (k === "subs") { const l = subsOf(t.id); return l.length ? `${l.filter((x) => x.done).length}/${l.length}` : ""; }
      return t[k];
    };
    // Türkçe Excel ; ayırıcı ve BOM ile UTF-8'i doğru açar
    const csv = "\ufeff" + [cols.map((c) => c[1]).join(";"), ...rows.map((t) => cols.map(([k]) => cell(value(t, k))).join(";"))].join("\r\n");
    const a = document.createElement("a");
    a.href = URL.createObjectURL(new Blob([csv], { type: "text/csv;charset=utf-8" }));
    a.download = `is-takip-${today}.csv`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    toast(`${rows.length} görev CSV olarak indirildi`);
  }

  function printReport() {
    // Yazdırmada her zaman liste görünümü kullanılır
    renderTable(filteredTasks());
    document.body.classList.add("printing");
    $("#printDate").textContent = new Date().toLocaleString("tr-TR", { dateStyle: "long", timeStyle: "short" });
    window.print();
  }

  async function copyViewLink() {
    syncUrl();
    try {
      await navigator.clipboard.writeText(location.href);
      toast("Bu görünümün bağlantısı kopyalandı");
    } catch (_) {
      window.prompt("Bağlantıyı kopyalayın:", location.href);
    }
  }

  async function submitDialog(ev) {
    ev.preventDefault();
    const form = $("#taskForm");
    const base = state.editingId ? state.tasks.find((t) => t.id === state.editingId) : {};
    const task = { ...base };
    for (const f of FORM_FIELDS) task[f] = form[f].value.trim();
    if (task.link && !safeLink(task.link)) { toast("Bağlantı http:// ya da https:// ile başlamalı.", true); form.link.focus(); return; }
    const final = withStatus(task, task.status);

    const btn = form.querySelector("[type=submit]");
    btn.disabled = true;
    const isNew = !state.editingId;
    const saved = await saveTask(final, isNew, state.dialogBase);
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
        if (e.target.closest("a, select, .card-move")) return;
        if (action === "edit" || (id === "#boardView" && !e.target.closest("input"))) {
          openDialog(state.tasks.find((t) => t.id === item.dataset.id));
        }
      });
    }

    $("#boardView").addEventListener("change", (e) => {
      if (e.target.dataset.action !== "move") return;
      const card = e.target.closest("[data-id]");
      e.target.disabled = true;
      moveTo(card.dataset.id, e.target.value);
    });

    document.querySelector(".toolbar .seg").addEventListener("click", (e) => {
      const btn = e.target.closest("[data-view]");
      if (!btn) return;
      setView(btn.dataset.view);
      try { localStorage.setItem("istakip-view", state.view); } catch (_) {}
      renderFiltered();
    });

    $("#ganttView").addEventListener("click", (e) => {
      const z = e.target.closest("[data-zoom]");
      if (z) {
        state.ganttZoom = z.dataset.zoom;
        try { localStorage.setItem("istakip-gantt", state.ganttZoom); } catch (_) {}
        return renderFiltered();
      }
      if (e.target.closest("[data-gantt=today]")) {
        const sc = $("#ganttView .g-scroll");
        if (sc) sc.scrollTo({ left: Math.max(Number(sc.dataset.todayX) - sc.clientWidth / 3, 0), behavior: "smooth" });
        return;
      }
      const row = e.target.closest(".g-row[data-id]");
      if (row) openDialog(state.tasks.find((t) => t.id === row.dataset.id));
    });

    $("#exportMenu").addEventListener("click", (e) => {
      const b = e.target.closest("[data-export]");
      if (!b) return;
      $("#exportMenu").open = false;
      if (b.dataset.export === "csv") exportCsv();
      if (b.dataset.export === "print") printReport();
      if (b.dataset.export === "link") copyViewLink();
    });
    document.addEventListener("click", (e) => {
      if (!e.target.closest("#exportMenu")) $("#exportMenu").open = false;
    });
    window.addEventListener("afterprint", () => { document.body.classList.remove("printing"); renderFiltered(); });

    $("#commentBtn").addEventListener("click", addComment);
    $("#commentInput").addEventListener("keydown", (e) => { if (e.key === "Enter") { e.preventDefault(); addComment(); } });
    $("#whoBtn").addEventListener("click", askName);
    $("#nameForm").addEventListener("submit", (e) => {
      const v = $("#nameInput").value.trim();
      if (!v) { e.preventDefault(); return; }
      setActor(v);
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
    $("#refreshBtn").addEventListener("click", () => load());
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

  // ---- Şifreli kasa ---------------------------------------------------------------
  // Google bağlantı bilgileri (adres + anahtar) bu tarayıcıda kullanıcının şifresiyle
  // şifrelenmiş olarak saklanır: PBKDF2 (SHA-256) ile anahtar türetilir, AES-GCM ile şifrelenir.
  // Şifrenin kendisi hiçbir yerde saklanmaz. Kilit açıkken bilgiler yalnızca bu sekmenin
  // oturum belleğinde tutulur; sekme kapanınca ya da 30 dakika işlem yapılmayınca silinir.

  const SETTINGS_KEY = "istakip-ayarlar";
  const VAULT_KEY = "istakip-kasa";
  const SESSION_KEY = "istakip-oturum";
  const DEMO_KEY = "istakip-demo";
  const IDLE_MS = 30 * 60 * 1000;
  const PBKDF2_ITERATIONS = 310000;
  const MIN_PW = 6;

  const toB64 = (buf) => btoa(String.fromCharCode(...new Uint8Array(buf)));
  const fromB64 = (str) => Uint8Array.from(atob(str), (c) => c.charCodeAt(0));

  async function deriveKey(password, salt, iterations) {
    const base = await crypto.subtle.importKey("raw", new TextEncoder().encode(password), "PBKDF2", false, ["deriveKey"]);
    return crypto.subtle.deriveKey(
      { name: "PBKDF2", salt, iterations, hash: "SHA-256" },
      base, { name: "AES-GCM", length: 256 }, false, ["encrypt", "decrypt"]);
  }

  async function sealVault(secret, password) {
    const salt = crypto.getRandomValues(new Uint8Array(16));
    const iv = crypto.getRandomValues(new Uint8Array(12));
    const key = await deriveKey(password, salt, PBKDF2_ITERATIONS);
    const data = await crypto.subtle.encrypt({ name: "AES-GCM", iv }, key, new TextEncoder().encode(JSON.stringify(secret)));
    return { v: 1, iter: PBKDF2_ITERATIONS, salt: toB64(salt), iv: toB64(iv), data: toB64(data) };
  }

  async function openVault(vault, password) {
    try {
      const key = await deriveKey(password, fromB64(vault.salt), vault.iter || PBKDF2_ITERATIONS);
      const plain = await crypto.subtle.decrypt({ name: "AES-GCM", iv: fromB64(vault.iv) }, key, fromB64(vault.data));
      return JSON.parse(new TextDecoder().decode(plain));
    } catch (_) {
      throw new Error("Şifre hatalı.");
    }
  }

  function readJson(storage, key) {
    try { return JSON.parse(storage.getItem(key)); } catch (_) { return null; }
  }

  function writeJson(storage, key, value) {
    try { storage.setItem(key, JSON.stringify(value)); return true; } catch (_) { return false; }
  }

  function removeKey(storage, key) {
    try { storage.removeItem(key); } catch (_) {}
  }

  function readSettings() { return readJson(localStorage, SETTINGS_KEY) || {}; }
  function writeSettings(settings) { return writeJson(localStorage, SETTINGS_KEY, settings); }

  function readVault() {
    const v = readJson(localStorage, VAULT_KEY);
    return v && v.data && v.salt && v.iv ? v : null;
  }

  function readSession() {
    const s = readJson(sessionStorage, SESSION_KEY);
    if (!s || !s.scriptUrl || !s.token) return null;
    if (Date.now() - (s.at || 0) > IDLE_MS) { removeKey(sessionStorage, SESSION_KEY); return null; }
    return s;
  }

  function writeSession(secret) {
    writeJson(sessionStorage, SESSION_KEY, { scriptUrl: secret.scriptUrl, token: secret.token, at: Date.now() });
  }

  let lastTouch = 0;
  function touchSession() {
    if (Date.now() - lastTouch < 30000) return;
    lastTouch = Date.now();
    const s = readSession();
    if (s) writeSession(s);
  }

  function isDemo() {
    try { return sessionStorage.getItem(DEMO_KEY) === "1"; } catch (_) { return false; }
  }

  function setDemo(on) {
    try { if (on) sessionStorage.setItem(DEMO_KEY, "1"); else sessionStorage.removeItem(DEMO_KEY); } catch (_) {}
  }

  function validUrl(url) { return /^https:\/\/script\.google(usercontent)?\.com\/.+\/exec$/i.test(url); }
  const URL_HELP = "Web uygulaması adresi https://script.google.com/macros/s/…/exec biçiminde olmalı. Apps Script'te Dağıt > Dağıtımları yönet ekranından kopyalayın.";

  function checkNewPassword(pw, pw2) {
    if (pw.length < MIN_PW) return `Şifre en az ${MIN_PW} karakter olmalı.`;
    if (pw2 !== undefined && pw !== pw2) return "Şifreler birbiriyle aynı değil.";
    return "";
  }

  // ---- Üst çubuk ve rozet ------------------------------------------------------------

  function setBadge(text, cls, title) {
    const badge = $("#modeBadge");
    badge.textContent = text;
    badge.className = "badge " + cls;
    badge.title = title || "";
  }

  function setChrome({ refresh = false, settings = false, lock = false } = {}) {
    $("#refreshBtn").hidden = !refresh;
    $("#settingsBtn").hidden = !settings;
    $("#lockBtn").hidden = !lock;
  }

  // ---- Kilit ekranı ------------------------------------------------------------------

  const LOCK_TEXT = {
    unlock: ["İş Takip kilitli", "Devam etmek için şifrenizi girin.", "Kilidi aç", "Şifre"],
    setup: ["İş Takip'i bağlayın",
      "Google E-Tablonuzun web uygulaması adresini ve bağlantı anahtarını girin, bu cihaz için bir şifre belirleyin. Bilgiler bu tarayıcıda şifrenizle kilitlenmiş olarak saklanır.",
      "Bağlan ve kilitle", "Yeni şifre"],
    server: ["İş Takip kilitli", "Devam etmek için şifrenizi girin.", "Giriş yap", "Şifre"],
    migrate: ["Bir şifre belirleyin",
      "Bağlantı bilgileriniz artık şifreyle korunuyor. Bu cihazda kullanacağınız bir şifre belirleyin; sayfa her açılışta bu şifreyi soracak.",
      "Şifreyi kaydet", "Yeni şifre"]
  };

  function lockError(msg) {
    const el = $("#lockError");
    el.textContent = msg || "";
    el.hidden = !msg;
  }

  function showLock(mode, message) {
    state.lockMode = mode;
    state.store = null;
    state.tasks = [];
    state.subs = [];
    state.units = [];
    state.unitInfo = [];
    document.querySelectorAll("dialog[open]").forEach((d) => d.close());
    // Kilitliyken önceki verilerin hiçbiri sayfada kalmasın
    for (const id of ["#taskBody", "#boardView", "#upcoming", "#unitProgress", "#stats", "#statusChart",
      "#heroSummary", "#heroRing", "#unitsBody", "#subsList"]) $(id).innerHTML = "";
    const [title, text, submit, pwLabel] = LOCK_TEXT[mode];
    $("#lockTitle").textContent = title;
    $("#lockText").textContent = text;
    $("#lockSubmit").textContent = submit;
    $("#lockPwLabel").textContent = pwLabel;
    $("#lockPw").autocomplete = mode === "unlock" ? "current-password" : "new-password";
    document.querySelectorAll("#lockView [data-mode]").forEach((el) => {
      el.hidden = !el.dataset.mode.split(" ").includes(mode);
    });
    for (const id of ["#lockUrl", "#lockToken", "#lockPw", "#lockPw2"]) $(id).value = "";
    const forgot = $("#forgotBtn");
    forgot.classList.remove("confirm");
    forgot.textContent = "Şifremi unuttum";
    lockError(message || "");
    setBadge(mode === "unlock" || mode === "server" ? "Kilitli" : "Bağlı değil", "demo");
    setChrome();
    $("#userName").textContent = "";
    show("#lockView");
    (mode === "setup" ? $("#lockUrl") : $("#lockPw")).focus();
  }

  async function submitLock(ev) {
    ev.preventDefault();
    lockError("");
    const mode = state.lockMode;
    const pw = $("#lockPw").value;
    const btn = $("#lockSubmit");
    const label = btn.textContent;
    let secret;

    if (mode === "unlock" || mode === "server") {
      if (!pw) { lockError("Şifrenizi girin."); return; }
    } else {
      const err = checkNewPassword(pw, $("#lockPw2").value);
      if (err) { lockError(err); return; }
    }
    if (mode === "setup") {
      secret = { scriptUrl: $("#lockUrl").value.trim(), token: $("#lockToken").value.trim() };
      if (!validUrl(secret.scriptUrl)) { lockError(URL_HELP); return; }
      if (!secret.token) { lockError("Bağlantı anahtarını girin. E-Tabloda İş Takip > Bağlantı anahtarını göster menüsünden alabilirsiniz."); return; }
    }

    btn.disabled = true;
    btn.textContent = mode === "unlock" || mode === "server" ? "Açılıyor…" : "Kaydediliyor…";
    try {
      if (mode === "server") {
        // Şifre Google tarafında doğrulanır
        secret = { scriptUrl: cfg.sheetsUrl, token: pw };
        try {
          await new SheetsStore(secret.scriptUrl, secret.token).load();
        } catch (e) {
          const msg = e.message || String(e);
          throw new Error(/hatalı\.$/.test(msg) && !/deneme/.test(msg)
            ? "Şifre hatalı. Web şifresi henüz belirlenmediyse E-Tabloda İş Takip > 3. Web şifresi belirle menüsünü kullanın."
            : msg);
        }
      } else if (mode === "unlock") {
        secret = await openVault(readVault(), pw);
      } else {
        if (mode === "migrate") {
          const plain = readSettings();
          secret = { scriptUrl: plain.scriptUrl, token: plain.token };
        } else {
          await new SheetsStore(secret.scriptUrl, secret.token).load(); // bağlantıyı dene
        }
        if (!writeJson(localStorage, VAULT_KEY, await sealVault(secret, pw))) {
          throw new Error("Tarayıcı bu sayfanın veri saklamasına izin vermiyor (gizli pencere olabilir).");
        }
        const plain = readSettings();
        delete plain.scriptUrl;
        delete plain.token;
        writeSettings(plain);
      }
    } catch (e) {
      lockError(e.message || String(e));
      $("#lockPw").select();
      return;
    } finally {
      btn.disabled = false;
      btn.textContent = label;
    }
    writeSession(secret);
    setDemo(false);
    await connectSheets(secret);
    if (mode === "setup" || mode === "migrate") toast("Şifre kaydedildi. Sayfa her açılışta bu şifreyi soracak.");
  }

  function forgotPassword() {
    const btn = $("#forgotBtn");
    if (!btn.classList.contains("confirm")) {
      btn.classList.add("confirm");
      btn.textContent = "Bu cihazdaki bağlantı silinsin mi? Onaylamak için tekrar tıklayın";
      return;
    }
    removeKey(localStorage, VAULT_KEY);
    removeKey(sessionStorage, SESSION_KEY);
    showLock("setup", "");
    toast("Bağlantı bu cihazdan silindi. E-Tablodaki verileriniz duruyor; adres ve anahtarla yeniden bağlanın.");
  }

  function lockNow(message) {
    removeKey(sessionStorage, SESSION_KEY);
    showLock(cfg.sheetsUrl ? "server" : readVault() ? "unlock" : "setup", message);
  }

  function startIdleWatch() {
    for (const ev of ["pointerdown", "keydown", "scroll"]) {
      document.addEventListener(ev, touchSession, { passive: true, capture: true });
    }
    setInterval(() => {
      if (state.store instanceof SheetsStore && !readSession()) {
        lockNow("30 dakika işlem yapılmadığı için sayfa kilitlendi.");
      }
    }, 60000);
  }

  async function connectSheets(secret) {
    state.store = new SheetsStore(secret.scriptUrl, secret.token);
    setBadge("Google E-Tablolar'a bağlı", "live");
    // Merkezi şifre kullanılıyorsa cihazda değiştirilecek bağlantı ayarı yoktur
    setChrome({ refresh: true, settings: !cfg.sheetsUrl, lock: true });
    $("#demoBanner").hidden = true;
    await load();
  }

  // ---- Ayarlar penceresi -------------------------------------------------------------

  function settingsError(msg) {
    const el = $("#settingsError");
    el.textContent = msg || "";
    el.hidden = !msg;
  }

  function openSettings() {
    const session = readSession();
    // Google bağlı değilse (demo vb.) kurulum ekranına git
    if (!session && !cfg.clientId) {
      setDemo(false);
      showLock(readVault() ? "unlock" : "setup");
      return;
    }
    $("#googleFields").hidden = !session;
    $("#scriptUrlInput").value = session ? session.scriptUrl : "";
    $("#tokenInput").value = session ? session.token : "";
    $("#currentPwInput").value = "";
    $("#newPwInput").value = "";
    $("#shareUrlInput").value = cfg.shareUrl || "";
    $("#shareUrlRow").hidden = !cfg.clientId || !!session;
    $("#disconnectBtn").hidden = !session;
    settingsError("");
    $("#settingsDialog").showModal();
    (session ? $("#currentPwInput") : $("#shareUrlInput")).focus();
  }

  async function submitSettings(ev) {
    ev.preventDefault();
    settingsError("");
    const btn = $("#settingsForm [type=submit]");

    if (!$("#googleFields").hidden) {
      const secret = { scriptUrl: $("#scriptUrlInput").value.trim(), token: $("#tokenInput").value.trim() };
      const currentPw = $("#currentPwInput").value;
      const newPw = $("#newPwInput").value;
      if (!validUrl(secret.scriptUrl)) { settingsError(URL_HELP); return; }
      if (!secret.token) { settingsError("Bağlantı anahtarını girin."); return; }
      if (!currentPw) { settingsError("Değişikliği kaydetmek için mevcut şifrenizi girin."); return; }
      if (newPw) { const err = checkNewPassword(newPw); if (err) { settingsError(err); return; } }
      btn.disabled = true;
      btn.textContent = "Kaydediliyor…";
      try {
        await openVault(readVault(), currentPw); // mevcut şifreyi doğrula
        await new SheetsStore(secret.scriptUrl, secret.token).load();
        writeJson(localStorage, VAULT_KEY, await sealVault(secret, newPw || currentPw));
      } catch (e) {
        settingsError(e.message || String(e));
        return;
      } finally {
        btn.disabled = false;
        btn.textContent = "Kaydet ve bağlan";
      }
      writeSession(secret);
      $("#settingsDialog").close();
      toast(newPw ? "Bağlantı ve şifre güncellendi" : "Bağlantı güncellendi");
      await connectSheets(secret);
      return;
    }

    // Office 365 (Excel) bağlantı linki
    const shareUrl = $("#shareUrlInput").value.trim();
    if (!/^https:\/\/[^/]+\.(sharepoint\.com|1drv\.ms|onedrive\.live\.com)\//i.test(shareUrl)) {
      settingsError("Bu bir OneDrive / SharePoint linki gibi görünmüyor. Excel'de Paylaş > Bağlantıyı kopyala ile alınan linki yapıştırın.");
      return;
    }
    const settings = readSettings();
    settings.shareUrl = shareUrl;
    writeSettings(settings);
    $("#settingsDialog").close();
    await start();
  }

  function disconnect() {
    removeKey(localStorage, VAULT_KEY);
    removeKey(sessionStorage, SESSION_KEY);
    $("#settingsDialog").close();
    toast("Bağlantı bu cihazdan kaldırıldı. E-Tablodaki verileriniz duruyor.");
    showLock("setup");
  }

  async function connectExcel() {
    if (!state.auth || !state.auth.account) return;
    if (!cfg.shareUrl && !cfg.filePath) { show("#noFile"); return; }
    state.store = new ExcelStore(cfg, state.auth);
    await load();
  }

  // ---- Başlangıç --------------------------------------------------------------

  async function start() {
    $("#demoBanner").hidden = true;
    setChrome();

    // 0) Web uygulaması adresi yapılandırılmışsa her cihazda yalnızca şifre sorulur
    if (cfg.sheetsUrl) {
      const s = readSession();
      if (s && s.scriptUrl === cfg.sheetsUrl) { await connectSheets(s); return; }
      removeKey(sessionStorage, SESSION_KEY);
      showLock("server");
      return;
    }

    // 1) Kilidi açık bir oturum varsa doğrudan Google E-Tablolar'a bağlan
    const session = readSession();
    if (session) { await connectSheets(session); return; }

    // 2) Şifreli bağlantı kayıtlıysa şifre sor
    if (readVault()) { showLock("unlock"); return; }

    // 3) Eski sürümden kalan şifresiz bağlantı varsa şifre belirlet
    const saved = readSettings();
    if (saved.scriptUrl && saved.token) { showLock("migrate"); return; }

    // 4) Office 365 yapılandırılmışsa Microsoft girişi
    if (cfg.clientId) {
      if (saved.shareUrl) cfg.shareUrl = saved.shareUrl;
      setBadge("Excel'e bağlı", "live");
      setChrome({ refresh: true, settings: true });
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

    // 5) Bu sekmede demo istendiyse örnek verilerle aç
    if (isDemo()) {
      state.store = new DemoStore(cfg);
      setBadge("Demo modu", "demo", "Örnek veriler; kendi E-Tablonuzu bağlamak için Ayarlar");
      setChrome({ refresh: true, settings: true });
      $("#demoBanner").hidden = false;
      await load();
      return;
    }

    // 6) Hiçbiri yoksa kurulum ekranı
    showLock("setup");
  }

  function init() {
    bindEvents();
    $("#lockForm").addEventListener("submit", submitLock);
    $("#forgotBtn").addEventListener("click", forgotPassword);
    $("#demoBtn").addEventListener("click", () => { setDemo(true); start(); });
    $("#lockBtn").addEventListener("click", () => lockNow("Sayfa kilitlendi."));
    startIdleWatch();
    startAutoRefresh();
    try {
      state.actor = localStorage.getItem(NAME_KEY) || "";
      const z = localStorage.getItem("istakip-gantt");
      if (GANTT_ZOOM[z]) state.ganttZoom = z;
      setView(localStorage.getItem("istakip-view") || "list");
    } catch (_) {}
    // Bağlantıyla gelen filtreler kayıtlı görünümün önüne geçer
    state.urlFilters = readUrlFilters();
    const fromUrl = { pano: "board", zaman: "gantt" }[state.urlFilters.view];
    if (fromUrl) setView(fromUrl);
    start();
  }

  init();
})();
