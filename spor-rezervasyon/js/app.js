/* Spor Rezervasyon — tıklanabilir prototip.
 * Veriler tarayıcıda (localStorage) tutulur; gerçek sistemde SSO ve sunucu API'si ile değiştirilir.
 * Durum makinesi: PENDING → APPROVED → COMPLETED, ya da REJECTED / CANCELLED. */
(function () {
  "use strict";

  const STORE_KEY = "spor-rezervasyon-v1";
  const DAYS = ["Pazar", "Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi"];
  const DAY_ORDER = [1, 2, 3, 4, 5, 6, 0];
  const ROLES = { USER: "Kullanıcı", SKS: "SKS onaylayıcı", ADMIN: "Admin" };
  const STATUS = {
    PENDING:   { label: "Ön rezervasyon", cls: "b-warning", icon: "ti-hourglass" },
    APPROVED:  { label: "Onaylandı",      cls: "b-success", icon: "ti-check" },
    COMPLETED: { label: "Tamamlandı",     cls: "b-success", icon: "ti-checks" },
    REJECTED:  { label: "Reddedildi",     cls: "b-danger",  icon: "ti-x" },
    CANCELLED: { label: "İptal",          cls: "b-danger",  icon: "ti-ban" },
  };
  const ROOM_TYPES = ["Fitness", "Dans stüdyosu", "Spor salonu"];
  const MODES = { shared: "paylaşımlı", exclusive: "münhasır" };

  // ---------- Yardımcılar ----------
  const $ = (sel, root = document) => root.querySelector(sel);
  const esc = (s) => String(s ?? "").replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[c]));
  const icon = (name) => `<i class="ti ${name}" aria-hidden="true"></i>`;
  const pad = (n) => String(n).padStart(2, "0");
  const isoDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  const addDays = (n) => { const d = new Date(); d.setDate(d.getDate() + n); return isoDate(d); };
  const toMin = (t) => { const [h, m] = t.split(":").map(Number); return h * 60 + m; };
  const fmtDate = (iso) => new Date(iso + "T00:00").toLocaleDateString("tr-TR", { day: "numeric", month: "long", weekday: "short" });
  const uid = () => Math.random().toString(36).slice(2, 9);

  // ---------- Örnek veri ----------
  function seed() {
    const rooms = [
      { id: "r1", name: "Fitness salonu", type: "Fitness", mode: "shared", capacity: 30 },
      { id: "r2", name: "Dans stüdyosu", type: "Dans stüdyosu", mode: "exclusive", capacity: 20 },
      { id: "r3", name: "Kapalı spor salonu", type: "Spor salonu", mode: "exclusive", capacity: 40 },
    ];
    const hours = [];
    for (const r of rooms) {
      for (const d of [1, 2, 3, 4, 5]) hours.push({ id: uid(), roomId: r.id, day: d, open: "08:00", close: "22:00" });
      hours.push({ id: uid(), roomId: r.id, day: 6, open: "10:00", close: "18:00" });
    }
    const p = (n) => Array.from({ length: n }, (_, i) => String(20230100 + i * 7));
    const res = (o) => ({ id: uid(), team: "", participants: [], reason: "", attended: null, createdAt: Date.now(), ...o });
    return {
      users: {
        ogrenci: { username: "ogrenci", name: "Elif Yılmaz", number: "20231045", role: "USER", waiver: false },
        sks: { username: "sks", name: "Murat Kaya", number: "P-1102", role: "SKS", waiver: true },
        admin: { username: "admin", name: "Ayşe Demir", number: "P-0007", role: "ADMIN", waiver: true },
      },
      teams: ["Basketbol takımı", "Voleybol takımı", "Modern dans topluluğu", "Masa tenisi kulübü"],
      rooms, hours,
      closures: [{ id: uid(), start: addDays(20), end: addDays(22), reason: "Zemin bakımı" }],
      reservations: [
        res({ owner: "ogrenci", roomId: "r2", date: addDays(2), start: "18:00", end: "19:30", team: "Modern dans topluluğu", participants: p(7), waivers: 6, status: "PENDING" }),
        res({ owner: "ogrenci", roomId: "r1", date: addDays(1), start: "09:00", end: "10:00", waivers: 1, status: "APPROVED" }),
        res({ owner: "ogrenci", roomId: "r3", date: addDays(-3), start: "16:00", end: "18:00", team: "Basketbol takımı", participants: p(9), waivers: 10, status: "REJECTED", reason: "Aynı saatte üniversite maçı planlandı." }),
        res({ owner: "ogrenci", roomId: "r1", date: addDays(-6), start: "07:30", end: "08:30", waivers: 1, status: "COMPLETED", attended: 1 }),
        res({ owner: "u2", ownerName: "Can Öztürk", roomId: "r3", date: addDays(3), start: "20:00", end: "22:00", team: "Voleybol takımı", participants: p(11), waivers: 12, status: "PENDING" }),
        res({ owner: "u3", ownerName: "Zeynep Arslan", roomId: "r1", date: addDays(1), start: "12:00", end: "13:00", participants: p(2), waivers: 1, status: "PENDING" }),
        res({ owner: "u4", ownerName: "Deniz Şahin", roomId: "r2", date: addDays(-2), start: "17:00", end: "18:00", participants: p(4), waivers: 5, status: "COMPLETED", attended: 4 }),
        res({ owner: "u2", ownerName: "Can Öztürk", roomId: "r3", date: addDays(-4), start: "20:00", end: "22:00", team: "Voleybol takımı", participants: p(11), waivers: 12, status: "COMPLETED", attended: 10 }),
        res({ owner: "u5", ownerName: "Efe Koç", roomId: "r1", date: addDays(-1), start: "18:00", end: "19:00", participants: p(1), waivers: 2, status: "COMPLETED", attended: 1 }),
        res({ owner: "u3", ownerName: "Zeynep Arslan", roomId: "r1", date: addDays(-5), start: "10:00", end: "11:00", waivers: 1, status: "CANCELLED" }),
      ],
    };
  }

  let db = load();
  let session = safeGet("spor-rezervasyon-session");
  let ui = { adminTab: "rooms", editRoom: null, rejecting: null, flash: null };

  function safeGet(k) { try { return localStorage.getItem(k); } catch { return null; } }
  function safeSet(k, v) { try { v == null ? localStorage.removeItem(k) : localStorage.setItem(k, v); } catch { /* yok say */ } }
  function load() { try { const s = JSON.parse(localStorage.getItem(STORE_KEY)); if (s && s.rooms) return s; } catch { /* yok say */ } return seed(); }
  function save() { safeSet(STORE_KEY, JSON.stringify(db)); }

  const me = () => db.users[session];
  const room = (id) => db.rooms.find((r) => r.id === id);
  const people = (r) => 1 + r.participants.length;
  const ownerLabel = (r) => (db.users[r.owner]?.name || r.ownerName || r.owner);

  // ---------- Yönlendirme ----------
  const ROUTES = {
    home:      { title: "Ana sayfa",        roles: ["USER", "SKS", "ADMIN"], render: renderHome },
    waiver:    { title: "Feragatname",      roles: ["USER", "SKS", "ADMIN"], render: renderWaiver },
    new:       { title: "Yeni rezervasyon", roles: ["USER", "SKS", "ADMIN"], render: renderNew, nav: ["Yeni", "ti-calendar-plus"] },
    mine:      { title: "Rezervasyonlarım", roles: ["USER", "SKS", "ADMIN"], render: renderMine, nav: ["Rezervasyonlarım", "ti-list-details"] },
    approvals: { title: "Onaylar",          roles: ["SKS", "ADMIN"],         render: renderApprovals, nav: ["Onaylar", "ti-checks"] },
    stats:     { title: "İstatistik",       roles: ["SKS", "ADMIN"],         render: renderStats, nav: ["İstatistik", "ti-chart-bar"] },
    admin:     { title: "Yönetim",          roles: ["ADMIN"],                render: renderAdmin, nav: ["Yönetim", "ti-settings"] },
  };

  function go(route) { if (location.hash === "#" + route) render(); else location.hash = route; }
  window.addEventListener("hashchange", render);

  function render() {
    const app = $("#app");
    if (!me()) { app.innerHTML = loginView(); bindLogin(); document.title = "Giriş · Spor Rezervasyon"; return; }
    let key = location.hash.slice(1) || "home";
    if (!ROUTES[key] || !ROUTES[key].roles.includes(me().role)) key = "home";
    // Feragatname imzalanmadan hiçbir ekrana geçilemez
    if (!me().waiver && key !== "waiver") key = "waiver";
    const r = ROUTES[key];
    document.title = `${r.title} · Spor Rezervasyon`;
    app.innerHTML = navView(key) + `<main class="${key === "waiver" || key === "new" || key === "home" ? "narrow" : ""}">${r.render()}</main>`;
    bindNav();
    r.bind?.();
    window.scrollTo(0, 0);
  }

  function navView(active) {
    const u = me();
    const pending = db.reservations.filter((r) => r.status === "PENDING").length;
    const links = Object.entries(ROUTES)
      .filter(([, r]) => r.nav && r.roles.includes(u.role))
      .map(([k, r]) => `<a href="#${k}" class="${k === active ? "active" : ""}">${icon(r.nav[1])}${r.nav[0]}${k === "approvals" && pending ? `<span class="count">${pending}</span>` : ""}</a>`)
      .join("");
    return `
      <header class="topnav"><div class="topnav-inner">
        <a href="#home" class="brand" style="color:inherit;text-decoration:none">
          <span class="brand-mark">${icon("ti-barbell")}</span><span>Spor Rezervasyon</span>
        </a>
        <nav class="nav" aria-label="Ana menü">${u.waiver ? links : ""}</nav>
        <div class="userbox">
          <div class="who">${esc(u.name)}<small>${ROLES[u.role]}</small></div>
          <button class="btn btn-ghost" id="logout" title="Çıkış">${icon("ti-logout")}<span class="sr">Çıkış</span></button>
        </div>
      </div></header>`;
  }
  function bindNav() {
    $("#logout").onclick = () => { session = null; safeSet("spor-rezervasyon-session", null); location.hash = ""; render(); };
  }

  function toast(msg) {
    const t = document.createElement("div");
    t.className = "toast"; t.setAttribute("role", "status"); t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2600);
  }

  const alertBox = (kind, ic, html) => `<div class="alert alert-${kind}" role="${kind === "danger" ? "alert" : "status"}">${icon(ic)}<div>${html}</div></div>`;
  const statusBadge = (s) => `<span class="badge ${STATUS[s].cls}">${icon(STATUS[s].icon)}${STATUS[s].label}</span>`;

  // ---------- 1. Giriş ----------
  function loginView(error) {
    return `
      <div class="login-wrap"><div class="card login">
        <div class="login-head">
          <div class="brand-mark">${icon("ti-barbell")}</div>
          <h1>Spor Rezervasyon</h1>
          <p class="muted">Üniversite hesabınızla giriş yapın</p>
        </div>
        <div id="loginError">${error ? alertBox("danger", "ti-alert-circle", esc(error)) : ""}</div>
        <form id="loginForm" novalidate>
          <div class="field"><label for="lu">Kullanıcı adı</label>
            <input class="input" id="lu" autocomplete="username" autocapitalize="off" placeholder="ad.soyad"></div>
          <div class="field"><label for="lp">Şifre</label>
            <input class="input" id="lp" type="password" autocomplete="current-password"></div>
          <button class="btn btn-primary btn-block" type="submit">Giriş yap</button>
        </form>
        <div class="demo-hint">Prototip: <code>ogrenci</code>, <code>sks</code> veya <code>admin</code> ile, herhangi bir şifreyle girin.</div>
      </div></div>`;
  }
  function bindLogin() {
    $("#loginForm").onsubmit = (e) => {
      e.preventDefault();
      const u = $("#lu").value.trim().toLowerCase(), p = $("#lp").value;
      let err = "";
      if (!u || !p) err = "Kullanıcı adı ve şifre gerekli.";
      else if (!db.users[u]) err = "Kullanıcı adı veya şifre hatalı.";
      if (err) { $("#loginError").innerHTML = alertBox("danger", "ti-alert-circle", err); return; }
      session = u; safeSet("spor-rezervasyon-session", u);
      location.hash = me().waiver ? "home" : "waiver";
      render();
    };
  }

  // ---------- 2. Ana sayfa ----------
  function renderHome() {
    const u = me();
    const upcoming = db.reservations.filter((r) => r.owner === u.username && ["PENDING", "APPROVED"].includes(r.status)).length;
    return `
      <h1 class="page-head">Hoş geldiniz, ${esc(u.name.split(" ")[0])}</h1>
      <p class="page-sub">Spor alanları için rezervasyon talebi oluşturabilir, taleplerinizin durumunu izleyebilirsiniz.</p>
      <div class="tiles">
        <a class="tile" href="#new"><span class="tile-icon">${icon("ti-calendar-plus")}</span>
          <span><strong>Yeni rezervasyon</strong><span class="muted small">Alan, tarih ve saat seçin</span></span></a>
        <a class="tile" href="#mine"><span class="tile-icon">${icon("ti-list-details")}</span>
          <span><strong>Rezervasyonlarım</strong><span class="muted small">${upcoming ? `${upcoming} aktif talep` : "Aktif talebiniz yok"}</span></span></a>
      </div>`;
  }

  // ---------- 3. Feragatname ----------
  function renderWaiver() {
    ROUTES.waiver.bind = () => {
      const cb = $("#wAgree"), btn = $("#wSign");
      cb.onchange = () => { btn.disabled = !cb.checked; };
      btn.onclick = () => { me().waiver = true; save(); toast("Feragatname imzalandı."); go("home"); };
    };
    const done = me().waiver;
    return `
      <div class="card">
        <h1>Sağlık feragatnamesi</h1>
        <p class="muted" style="margin-top:4px">Spor tesislerini kullanmadan önce usul ve esasları okuyup onaylamanız gerekir.</p>
        <div class="waiver-text" tabindex="0" aria-label="Usul ve esaslar">
          <ol>
            <li>Tesisleri kullanan kişi, spor yapmasına engel bir sağlık sorunu bulunmadığını beyan eder.</li>
            <li>Kronik rahatsızlığı olan kullanıcılar, hekim onayı almadan yoğun egzersiz yapmamalıdır.</li>
            <li>Ekipmanlar amacına uygun ve görevli personelin yönlendirmelerine göre kullanılır.</li>
            <li>Tesise uygun spor kıyafeti ve temiz spor ayakkabısı ile girilir.</li>
            <li>Rezervasyon saatine uyulur; 15 dakika gecikmede rezervasyon iptal edilebilir.</li>
            <li>Kullanıcının kendi dikkatsizliği sonucu oluşan yaralanmalardan üniversite sorumlu tutulamaz.</li>
            <li>Takım rezervasyonlarında her katılımcının bu feragatnameyi ayrıca imzalaması gerekir.</li>
            <li>Kişisel eşyaların korunmasından kullanıcı sorumludur; dolaplar gün sonunda boşaltılır.</li>
            <li>Kurallara uymayan kullanıcıların rezervasyon hakkı SKS tarafından askıya alınabilir.</li>
            <li>Bu feragatname bir akademik yıl boyunca geçerlidir.</li>
          </ol>
        </div>
        ${done
          ? alertBox("success", "ti-circle-check", "Feragatnameyi bu akademik yıl için imzaladınız.")
          : `<label class="check form-row"><input type="checkbox" id="wAgree"><span>Okudum, kabul ediyorum</span></label>
             <button class="btn btn-primary btn-block" id="wSign" disabled>İmzala ve devam et</button>`}
      </div>`;
  }

  // ---------- 4. Yeni rezervasyon ----------
  let draftChips = [];
  function renderNew() {
    ROUTES.new.bind = bindNew;
    const flash = ui.flash; ui.flash = null;
    return `
      <h1 class="page-head">Yeni rezervasyon</h1>
      <p class="page-sub">Talebiniz SKS onayından sonra kesinleşir.</p>
      <form class="card" id="newForm" novalidate>
        <div id="newMsg">${flash || ""}</div>
        <div class="field"><label for="nRoom">Alan</label>
          <select class="input" id="nRoom">
            <option value="">Alan seçin</option>
            ${db.rooms.map((r) => `<option value="${r.id}">${esc(r.name)} (${MODES[r.mode]} · ${r.capacity} kişi)</option>`).join("")}
          </select>
        </div>
        <div class="grid-3 form-row">
          <div class="field"><label for="nDate">Tarih</label><input class="input" type="date" id="nDate" min="${addDays(0)}" value="${addDays(1)}"></div>
          <div class="field"><label for="nStart">Başlangıç</label><input class="input" type="time" id="nStart" step="900" value="18:00"></div>
          <div class="field"><label for="nEnd">Bitiş</label><input class="input" type="time" id="nEnd" step="900" value="19:00"></div>
        </div>
        <div class="field"><label for="nTeam">Takım <span class="muted">(opsiyonel)</span></label>
          <select class="input" id="nTeam">
            <option value="">— Bireysel —</option>
            ${db.teams.map((t) => `<option>${esc(t)}</option>`).join("")}
          </select>
        </div>
        <div class="field"><label for="nChipInput">Yanınızdaki öğrenci numaraları</label>
          <div class="chips" id="nChips"></div>
          <p class="hint">${icon("ti-mail")}Her katılımcıya feragatname daveti gönderilecek</p>
        </div>
        ${alertBox("warning", "ti-info-circle", "Talebiniz SKS onayına kadar ön rezervasyon olarak görünecek.")}
        <button class="btn btn-primary btn-block" type="submit">${icon("ti-send")}Rezervasyon talebi gönder</button>
      </form>`;
  }

  function drawChips() {
    const box = $("#nChips");
    const old = $("#nChipInput");
    if (old) old.onblur = null;
    box.innerHTML = draftChips.map((n, i) =>
      `<span class="chip">${esc(n)}<button type="button" data-i="${i}" aria-label="${esc(n)} numarasını kaldır">${icon("ti-x")}</button></span>`).join("") +
      `<input id="nChipInput" inputmode="numeric" placeholder="${draftChips.length ? "" : "Numara yazıp Enter'a basın"}">`;
    const input = $("#nChipInput");
    box.querySelectorAll("button[data-i]").forEach((b) => b.onclick = () => { draftChips.splice(+b.dataset.i, 1); drawChips(); $("#nChipInput").focus(); });
    const commit = () => {
      const parts = input.value.split(/[\s,;]+/).map((s) => s.trim()).filter(Boolean);
      input.value = "";
      let bad = false;
      for (const v of parts) {
        if (!/^\d{6,12}$/.test(v)) { bad = true; continue; }
        if (!draftChips.includes(v) && v !== me().number) draftChips.push(v);
      }
      if (parts.length) { drawChips(); $("#nChipInput").focus(); }
      if (bad) toast("Öğrenci numarası 6–12 haneli olmalı.");
    };
    input.onkeydown = (e) => {
      if (["Enter", ",", " ", "Tab"].includes(e.key) && input.value.trim()) { e.preventDefault(); commit(); }
      else if (e.key === "Backspace" && !input.value && draftChips.length) { draftChips.pop(); drawChips(); $("#nChipInput").focus(); }
    };
    input.onblur = () => { if (input.value.trim()) commit(); };
    box.onclick = (e) => { if (e.target === box) input.focus(); };
  }

  function bindNew() {
    drawChips();
    $("#newForm").onsubmit = (e) => {
      e.preventDefault();
      const f = { roomId: $("#nRoom").value, date: $("#nDate").value, start: $("#nStart").value, end: $("#nEnd").value, team: $("#nTeam").value };
      const extra = $("#nChipInput").value.trim();
      if (extra) { toast("Yazdığınız numarayı eklemek için Enter'a basın."); return; }
      const err = validate(f, draftChips);
      if (err) { $("#newMsg").innerHTML = alertBox("danger", "ti-alert-circle", err); $("#newMsg").scrollIntoView({ block: "center" }); return; }
      db.reservations.unshift({
        id: uid(), owner: me().username, ...f, participants: [...draftChips],
        waivers: 1, status: "PENDING", reason: "", attended: null, createdAt: Date.now(),
      });
      save();
      const r = room(f.roomId);
      ui.flash = alertBox("success", "ti-circle-check",
        `Talebiniz alındı: <b style="font-weight:500">${esc(r.name)}</b>, ${fmtDate(f.date)} ${f.start}–${f.end}. ` +
        `Durumunu <a href="#mine">Rezervasyonlarım</a> sayfasından izleyebilirsiniz.` +
        (draftChips.length ? ` ${draftChips.length} katılımcıya feragatname daveti gönderildi.` : ""));
      draftChips = [];
      render();
    };
  }

  function validate(f, chips) {
    if (!f.roomId) return "Lütfen bir alan seçin.";
    if (!f.date || !f.start || !f.end) return "Tarih, başlangıç ve bitiş saati gerekli.";
    if (f.date < addDays(0)) return "Geçmiş bir tarih için rezervasyon yapılamaz.";
    if (toMin(f.end) <= toMin(f.start)) return "Bitiş saati başlangıçtan sonra olmalı.";
    if (toMin(f.end) - toMin(f.start) > 180) return "Bir rezervasyon en fazla 3 saat olabilir.";
    const r = room(f.roomId);
    const closed = db.closures.find((c) => f.date >= c.start && f.date <= c.end);
    if (closed) return `Tesis bu tarihte kapalı: ${esc(closed.reason)}.`;
    const day = new Date(f.date + "T00:00").getDay();
    const slots = db.hours.filter((h) => h.roomId === r.id && h.day === day);
    if (!slots.length) return `${esc(r.name)} ${DAYS[day].toLocaleLowerCase("tr")} günleri kapalı.`;
    if (!slots.some((h) => toMin(f.start) >= toMin(h.open) && toMin(f.end) <= toMin(h.close)))
      return `${esc(r.name)} açık saatleri: ${slots.map((h) => `${h.open}–${h.close}`).join(", ")}.`;
    const count = 1 + chips.length;
    if (count > r.capacity) return `Kişi sayısı (${count}) alan kapasitesini (${r.capacity}) aşıyor.`;
    const overlap = db.reservations.filter((x) => x.roomId === r.id && x.date === f.date && ["PENDING", "APPROVED"].includes(x.status)
      && toMin(x.start) < toMin(f.end) && toMin(f.start) < toMin(x.end));
    if (r.mode === "exclusive" && overlap.length)
      return `Bu saat aralığında ${esc(r.name)} dolu (${overlap.map((x) => `${x.start}–${x.end}`).join(", ")}). Başka bir saat seçin.`;
    if (r.mode === "shared") {
      const used = overlap.reduce((s, x) => s + people(x), 0);
      if (used + count > r.capacity) return `Bu saatte yalnızca ${Math.max(0, r.capacity - used)} kişilik yer kaldı.`;
    }
    return "";
  }

  // ---------- 5. Rezervasyonlarım ----------
  function renderMine() {
    ROUTES.mine.bind = () => {
      document.querySelectorAll("[data-cancel]").forEach((b) => b.onclick = () => {
        const r = db.reservations.find((x) => x.id === b.dataset.cancel);
        if (!confirm("Bu rezervasyonu iptal etmek istiyor musunuz?")) return;
        r.status = "CANCELLED"; save(); toast("Rezervasyon iptal edildi."); render();
      });
    };
    const list = db.reservations.filter((r) => r.owner === me().username)
      .sort((a, b) => (b.date + b.start).localeCompare(a.date + a.start));
    return `
      <div class="page-head" style="justify-content:space-between">
        <h1>Rezervasyonlarım</h1>
        <a class="btn btn-primary" href="#new">${icon("ti-plus")}Yeni</a>
      </div>
      <p class="page-sub">Ön rezervasyonlar SKS onayından sonra kesinleşir.</p>
      <div class="stack">
        ${list.length ? list.map((r) => {
          const rm = room(r.roomId);
          const cancellable = ["PENDING", "APPROVED"].includes(r.status) && r.date >= addDays(0);
          return `
          <article class="row-card">
            <div>
              <div class="title">${esc(rm?.name || "Silinmiş alan")}${r.team ? ` <span class="muted">· ${esc(r.team)}</span>` : ""}</div>
              <div class="meta">
                <span>${icon("ti-calendar")}${fmtDate(r.date)}</span>
                <span>${icon("ti-clock")}${r.start}–${r.end}</span>
                <span>${icon("ti-users")}${people(r)} kişi</span>
              </div>
              ${r.status === "REJECTED" && r.reason ? `<div class="reason">${icon("ti-message-report")}<span>Sebep: ${esc(r.reason)}</span></div>` : ""}
            </div>
            <div class="side">
              ${statusBadge(r.status)}
              ${cancellable ? `<button class="btn btn-ghost small" data-cancel="${r.id}">İptal et</button>` : ""}
            </div>
          </article>`;
        }).join("") : `<div class="empty">${icon("ti-calendar-off")}Henüz rezervasyonunuz yok.<br><a href="#new">İlk rezervasyonunuzu oluşturun</a></div>`}
      </div>`;
  }

  // ---------- 6. SKS onay kuyruğu ----------
  function renderApprovals() {
    ROUTES.approvals.bind = () => {
      const find = (id) => db.reservations.find((x) => x.id === id);
      document.querySelectorAll("[data-approve]").forEach((b) => b.onclick = () => {
        find(b.dataset.approve).status = "APPROVED"; save(); toast("Rezervasyon onaylandı."); render();
      });
      document.querySelectorAll("[data-reject]").forEach((b) => b.onclick = () => { ui.rejecting = b.dataset.reject; render(); $("#rejReason")?.focus(); });
      document.querySelectorAll("[data-cancel-reject]").forEach((b) => b.onclick = () => { ui.rejecting = null; render(); });
      document.querySelectorAll("[data-confirm-reject]").forEach((b) => b.onclick = () => {
        const reason = $("#rejReason").value.trim();
        if (!reason) { $("#rejErr").hidden = false; $("#rejReason").focus(); return; }
        const r = find(b.dataset.confirmReject);
        r.status = "REJECTED"; r.reason = reason; ui.rejecting = null; save(); toast("Rezervasyon reddedildi."); render();
      });
    };
    const list = db.reservations.filter((r) => r.status === "PENDING").sort((a, b) => (a.date + a.start).localeCompare(b.date + b.start));
    return `
      <div class="page-head"><h1>Onay bekleyenler</h1><span class="badge b-warning">${list.length}</span></div>
      <p class="page-sub">Tarihe göre sıralı ön rezervasyonlar.</p>
      <div class="stack">
        ${list.length ? list.map((r) => {
          const rm = room(r.roomId), n = people(r), pct = Math.round((r.waivers / n) * 100);
          const rejecting = ui.rejecting === r.id;
          return `
          <article class="card approval">
            <div class="top">
              <div>
                <h2>${esc(rm?.name || "Silinmiş alan")}</h2>
                <div class="muted" style="margin-top:2px">${esc(ownerLabel(r))}${r.team ? ` · ${esc(r.team)}` : ""} · ${n} kişi</div>
              </div>
              <span class="badge ${r.team ? "b-accent" : "b-neutral"}">${icon(r.team ? "ti-users-group" : "ti-user")}${r.team ? "Takım" : "Bireysel"}</span>
            </div>
            <div class="row-card" style="border:0;padding:0;margin-top:10px;display:block">
              <div class="meta">
                <span>${icon("ti-calendar")}${fmtDate(r.date)}</span>
                <span>${icon("ti-clock")}${r.start}–${r.end}</span>
                <span class="waiver-meter">${icon("ti-file-certificate")}${r.waivers}/${n} feragatname
                  <span class="meter ${pct < 100 ? "partial" : ""}" aria-hidden="true"><i style="width:${pct}%"></i></span></span>
              </div>
            </div>
            <div class="actions">
              ${rejecting ? `
                <div class="reject-box">
                  <label class="label" for="rejReason">Red sebebi</label>
                  <textarea class="input" id="rejReason" placeholder="Kullanıcıya gösterilecek açıklama"></textarea>
                  <p class="small" id="rejErr" style="color:var(--danger)" hidden>Lütfen bir sebep yazın.</p>
                  <div class="btn-row">
                    <button class="btn btn-danger" data-confirm-reject="${r.id}">${icon("ti-x")}Reddet</button>
                    <button class="btn btn-outline" data-cancel-reject>Vazgeç</button>
                  </div>
                </div>` : `
                <div class="btn-row">
                  <button class="btn btn-success" data-approve="${r.id}">${icon("ti-check")}Onayla</button>
                  <button class="btn btn-danger-outline" data-reject="${r.id}">${icon("ti-x")}Reddet</button>
                </div>`}
            </div>
          </article>`;
        }).join("") : `<div class="empty">${icon("ti-circle-check")}Onay bekleyen talep yok.</div>`}
      </div>`;
  }

  // ---------- 7. İstatistik ----------
  function renderStats() {
    const counted = db.reservations.filter((r) => !["REJECTED", "CANCELLED"].includes(r.status));
    const totalPeople = counted.reduce((s, r) => s + people(r), 0);
    const rows = db.rooms.map((rm) => {
      const rs = counted.filter((r) => r.roomId === rm.id);
      const done = rs.filter((r) => r.status === "COMPLETED");
      const expected = done.reduce((s, r) => s + people(r), 0);
      const attended = done.reduce((s, r) => s + (r.attended ?? 0), 0);
      return { name: rm.name, count: rs.length, people: rs.reduce((s, r) => s + people(r), 0), rate: expected ? Math.round((attended / expected) * 100) : null };
    });
    return `
      <h1 class="page-head">İstatistik</h1>
      <p class="page-sub">Alan kullanımı. Reddedilen ve iptal edilen talepler sayılmaz.</p>
      <div class="metrics">
        <div class="card metric"><div class="label">${icon("ti-calendar-stats")}Toplam rezervasyon</div><div class="value">${counted.length}</div></div>
        <div class="card metric"><div class="label">${icon("ti-users")}Toplam kişi</div><div class="value">${totalPeople}</div></div>
      </div>
      <div class="card flush">
        <div class="card-head"><h2>Alan kullanımı</h2><span class="muted small">Katılım: tamamlanan rezervasyonlarda gelen / beklenen</span></div>
        <div class="table-wrap"><table>
          <thead><tr><th>Alan</th><th class="num">Rezervasyon</th><th class="num">Kişi</th><th class="num">Katılım oranı</th></tr></thead>
          <tbody>${rows.map((r) => `
            <tr><td>${esc(r.name)}</td><td class="num">${r.count}</td><td class="num">${r.people}</td>
              <td class="num">${r.rate == null ? `<span class="muted">—</span>` :
                `<span class="rate"><span class="meter ${r.rate < 100 ? "partial" : ""}" aria-hidden="true"><i style="width:${r.rate}%"></i></span>%${r.rate}</span>`}</td></tr>`).join("")}
          </tbody>
        </table></div>
      </div>`;
  }

  // ---------- 8. Yönetim ----------
  function renderAdmin() {
    ROUTES.admin.bind = bindAdmin;
    const tabs = [["rooms", "Odalar", "ti-building"], ["hours", "Açık saatler", "ti-clock"], ["closures", "Kapalı günler", "ti-calendar-off"]];
    const body = { rooms: adminRooms, hours: adminHours, closures: adminClosures }[ui.adminTab]();
    return `
      <h1 class="page-head">Yönetim</h1>
      <p class="page-sub">Alanları, açık saatleri ve kapalı günleri düzenleyin.</p>
      <div class="tabs" role="tablist">
        ${tabs.map(([k, l, i]) => `<button class="tab ${ui.adminTab === k ? "active" : ""}" role="tab" aria-selected="${ui.adminTab === k}" data-tab="${k}">${icon(i)}${l}</button>`).join("")}
      </div>
      ${body}`;
  }

  function adminRooms() {
    const e = db.rooms.find((r) => r.id === ui.editRoom);
    return `
      <div class="admin-grid">
        <form class="card" id="roomForm" novalidate>
          <h2 style="margin-bottom:14px">${e ? "Odayı düzenle" : "Oda ekle"}</h2>
          <div id="roomErr"></div>
          <div class="field"><label for="rName">Ad</label><input class="input" id="rName" value="${esc(e?.name || "")}"></div>
          <div class="field"><label for="rType">Tip</label><select class="input" id="rType">
            ${ROOM_TYPES.map((t) => `<option ${e?.type === t ? "selected" : ""}>${t}</option>`).join("")}</select></div>
          <div class="grid-2 form-row">
            <div class="field"><label for="rMode">Mod</label><select class="input" id="rMode">
              ${Object.entries(MODES).map(([k, v]) => `<option value="${k}" ${e?.mode === k ? "selected" : ""}>${v[0].toLocaleUpperCase("tr") + v.slice(1)}</option>`).join("")}</select></div>
            <div class="field"><label for="rCap">Kapasite</label><input class="input" id="rCap" type="number" min="1" value="${e?.capacity || ""}"></div>
          </div>
          <div class="form-actions">
            ${e ? `<button type="button" class="btn btn-outline" id="rCancel">Vazgeç</button>` : ""}
            <button class="btn btn-primary" type="submit">${e ? "Kaydet" : `${icon("ti-plus")}Ekle`}</button>
          </div>
        </form>
        <div class="card flush">
          <div class="card-head"><h2>Mevcut odalar</h2><span class="badge b-neutral">${db.rooms.length}</span></div>
          <div class="list">${db.rooms.map((r) => `
            <div class="list-item">
              <div><div>${esc(r.name)}</div><div class="meta">${esc(r.type)} · ${MODES[r.mode]} · ${r.capacity} kişi</div></div>
              <div class="btn-row">
                <button class="btn btn-ghost" data-edit-room="${r.id}" title="Düzenle">${icon("ti-pencil")}</button>
                <button class="btn btn-ghost" data-del-room="${r.id}" title="Sil">${icon("ti-trash")}</button>
              </div>
            </div>`).join("") || `<div class="list-item muted">Henüz oda yok.</div>`}</div>
        </div>
      </div>`;
  }

  function adminHours() {
    const roomOpts = db.rooms.map((r) => `<option value="${r.id}">${esc(r.name)}</option>`).join("");
    const byRoom = db.rooms.map((rm) => {
      const hs = db.hours.filter((h) => h.roomId === rm.id).sort((a, b) => DAY_ORDER.indexOf(a.day) - DAY_ORDER.indexOf(b.day) || a.open.localeCompare(b.open));
      return `<div class="card-head" style="background:var(--surface-2)"><span style="font-weight:500">${esc(rm.name)}</span></div>
        ${hs.map((h) => `<div class="list-item"><div>${DAYS[h.day]} <span class="muted">· ${h.open}–${h.close}</span></div>
          <button class="btn btn-ghost" data-del-hour="${h.id}" title="Sil">${icon("ti-trash")}</button></div>`).join("") || `<div class="list-item muted">Açık saat tanımlı değil.</div>`}`;
    }).join("");
    return `
      <div class="admin-grid">
        <form class="card" id="hourForm" novalidate>
          <h2 style="margin-bottom:14px">Saat ekle</h2>
          <div id="hourErr"></div>
          <div class="field"><label for="hRoom">Oda</label><select class="input" id="hRoom">${roomOpts}</select></div>
          <div class="field"><label for="hDay">Gün</label><select class="input" id="hDay">
            ${DAY_ORDER.map((d) => `<option value="${d}">${DAYS[d]}</option>`).join("")}</select></div>
          <div class="grid-2 form-row">
            <div class="field"><label for="hOpen">Açılış</label><input class="input" type="time" id="hOpen" value="08:00"></div>
            <div class="field"><label for="hClose">Kapanış</label><input class="input" type="time" id="hClose" value="22:00"></div>
          </div>
          <div class="form-actions"><button class="btn btn-primary" type="submit">${icon("ti-plus")}Ekle</button></div>
        </form>
        <div class="card flush"><div class="list">${byRoom}</div></div>
      </div>`;
  }

  function adminClosures() {
    const list = [...db.closures].sort((a, b) => a.start.localeCompare(b.start));
    return `
      <div class="admin-grid">
        <form class="card" id="closeForm" novalidate>
          <h2 style="margin-bottom:14px">Kapalı gün ekle</h2>
          <div id="closeErr"></div>
          <div class="grid-2 form-row">
            <div class="field"><label for="cStart">Başlangıç</label><input class="input" type="date" id="cStart"></div>
            <div class="field"><label for="cEnd">Bitiş</label><input class="input" type="date" id="cEnd"></div>
          </div>
          <div class="field"><label for="cReason">Sebep</label><input class="input" id="cReason" placeholder="ör. Resmi tatil"></div>
          <div class="form-actions"><button class="btn btn-primary" type="submit">${icon("ti-plus")}Ekle</button></div>
        </form>
        <div class="card flush">
          <div class="card-head"><h2>Kapalı günler</h2><span class="badge b-neutral">${list.length}</span></div>
          <div class="list">${list.map((c) => `
            <div class="list-item">
              <div><div>${fmtDate(c.start)}${c.end !== c.start ? ` – ${fmtDate(c.end)}` : ""}</div><div class="meta">${esc(c.reason)}</div></div>
              <button class="btn btn-ghost" data-del-close="${c.id}" title="Sil">${icon("ti-trash")}</button>
            </div>`).join("") || `<div class="list-item muted">Kapalı gün tanımlı değil.</div>`}</div>
        </div>
      </div>`;
  }

  function bindAdmin() {
    const err = (id, msg) => { $(id).innerHTML = alertBox("danger", "ti-alert-circle", msg); };
    document.querySelectorAll("[data-tab]").forEach((b) => b.onclick = () => { ui.adminTab = b.dataset.tab; ui.editRoom = null; render(); });

    if (ui.adminTab === "rooms") {
      $("#roomForm").onsubmit = (e) => {
        e.preventDefault();
        const r = { name: $("#rName").value.trim(), type: $("#rType").value, mode: $("#rMode").value, capacity: +$("#rCap").value };
        if (!r.name) return err("#roomErr", "Oda adı gerekli.");
        if (!(r.capacity >= 1)) return err("#roomErr", "Kapasite en az 1 olmalı.");
        if (ui.editRoom) Object.assign(room(ui.editRoom), r); else db.rooms.push({ id: uid(), ...r });
        toast(ui.editRoom ? "Oda güncellendi." : "Oda eklendi.");
        ui.editRoom = null; save(); render();
      };
      $("#rCancel") && ($("#rCancel").onclick = () => { ui.editRoom = null; render(); });
      document.querySelectorAll("[data-edit-room]").forEach((b) => b.onclick = () => { ui.editRoom = b.dataset.editRoom; render(); $("#rName").focus(); });
      document.querySelectorAll("[data-del-room]").forEach((b) => b.onclick = () => {
        const id = b.dataset.delRoom;
        if (db.reservations.some((r) => r.roomId === id && ["PENDING", "APPROVED"].includes(r.status)))
          return toast("Aktif rezervasyonu olan oda silinemez.");
        if (!confirm(`"${room(id).name}" silinsin mi?`)) return;
        db.rooms = db.rooms.filter((r) => r.id !== id); db.hours = db.hours.filter((h) => h.roomId !== id);
        save(); render();
      });
    }
    if (ui.adminTab === "hours") {
      $("#hourForm").onsubmit = (e) => {
        e.preventDefault();
        const h = { roomId: $("#hRoom").value, day: +$("#hDay").value, open: $("#hOpen").value, close: $("#hClose").value };
        if (!h.roomId) return err("#hourErr", "Önce bir oda ekleyin.");
        if (!h.open || !h.close || toMin(h.close) <= toMin(h.open)) return err("#hourErr", "Kapanış saati açılıştan sonra olmalı.");
        if (db.hours.some((x) => x.roomId === h.roomId && x.day === h.day && toMin(x.open) < toMin(h.close) && toMin(h.open) < toMin(x.close)))
          return err("#hourErr", "Bu gün için çakışan bir saat aralığı var.");
        db.hours.push({ id: uid(), ...h }); save(); toast("Saat eklendi."); render();
      };
      document.querySelectorAll("[data-del-hour]").forEach((b) => b.onclick = () => { db.hours = db.hours.filter((h) => h.id !== b.dataset.delHour); save(); render(); });
    }
    if (ui.adminTab === "closures") {
      $("#closeForm").onsubmit = (e) => {
        e.preventDefault();
        const c = { start: $("#cStart").value, end: $("#cEnd").value || $("#cStart").value, reason: $("#cReason").value.trim() };
        if (!c.start) return err("#closeErr", "Başlangıç tarihi gerekli.");
        if (c.end < c.start) return err("#closeErr", "Bitiş tarihi başlangıçtan önce olamaz.");
        if (!c.reason) return err("#closeErr", "Sebep gerekli.");
        db.closures.push({ id: uid(), ...c }); save(); toast("Kapalı gün eklendi."); render();
      };
      document.querySelectorAll("[data-del-close]").forEach((b) => b.onclick = () => { db.closures = db.closures.filter((c) => c.id !== b.dataset.delClose); save(); render(); });
    }
  }

  render();
})();
