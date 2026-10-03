// Veri katmanı: ExcelStore (Microsoft Graph) ve DemoStore (tarayıcı belleği).
// İkisi de aynı arayüzü sunar: load(), addTask(task), updateTask(task).
(function () {
  "use strict";

  // Excel tablosundaki başlık adları. Sütun sırası önemli değil, eşleştirme ada göre yapılır.
  const TASK_COLUMNS = {
    id: "GörevNo",
    assigned: "AtamaTarihi",
    unit: "Birim",
    title: "GörevTanımı",
    priority: "Öncelik",
    due: "TerminTarihi",
    status: "Durum",
    done: "TamamlandıMı",
    doneDate: "TamamlanmaTarihi",
    note: "Not"
  };
  const DATE_FIELDS = ["assigned", "due", "doneDate"];
  const UNIT_NAME_COLUMN = "BirimAdı";

  const GRAPH = "https://graph.microsoft.com/v1.0";
  const SCOPES = ["User.Read", "Files.ReadWrite.All"];

  // ---- Yardımcılar ----------------------------------------------------------

  function normalizeHeader(s) {
    return String(s || "").toLocaleLowerCase("tr-TR").replace(/[\s_.-]/g, "");
  }

  function pad(n) { return String(n).padStart(2, "0"); }

  function todayIso() {
    const d = new Date();
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  // Excel tarih seri numarası (1900 sistemi) <-> "YYYY-MM-DD"
  function fromExcelDate(v) {
    if (v === null || v === undefined || v === "") return "";
    if (typeof v === "number") {
      return new Date(Math.round((v - 25569) * 86400000)).toISOString().slice(0, 10);
    }
    const s = String(v).trim();
    let m = s.match(/^(\d{4})-(\d{1,2})-(\d{1,2})/);
    if (m) return `${m[1]}-${pad(m[2])}-${pad(m[3])}`;
    m = s.match(/^(\d{1,2})[./](\d{1,2})[./](\d{4})$/);
    if (m) return `${m[3]}-${pad(m[2])}-${pad(m[1])}`;
    return "";
  }

  function toExcelDate(iso) {
    if (!iso) return "";
    const [y, m, d] = iso.split("-").map(Number);
    return Date.UTC(y, m - 1, d) / 86400000 + 25569;
  }

  function toBool(v) {
    if (typeof v === "boolean") return v;
    return ["true", "doğru", "dogru", "1", "evet", "x", "✓", "✔"].includes(
      String(v || "").trim().toLocaleLowerCase("tr-TR")
    );
  }

  function nextId(tasks, prefix) {
    let max = 0;
    for (const t of tasks) {
      const n = parseInt(String(t.id).replace(/\D/g, ""), 10);
      if (!isNaN(n) && n > max) max = n;
    }
    return prefix + String(max + 1).padStart(4, "0");
  }

  // ---- Kimlik doğrulama (MSAL) ------------------------------------------------

  class Auth {
    constructor(cfg) { this.cfg = cfg; this.msal = null; }

    async init() {
      this.msal = new msal.PublicClientApplication({
        auth: {
          clientId: this.cfg.clientId,
          authority: `https://login.microsoftonline.com/${this.cfg.tenantId || "organizations"}`,
          redirectUri: this.cfg.redirectUri || location.origin + location.pathname
        },
        cache: { cacheLocation: "localStorage" }
      });
      await this.msal.initialize();
      const result = await this.msal.handleRedirectPromise();
      if (result && result.account) {
        this.msal.setActiveAccount(result.account);
      } else if (!this.msal.getActiveAccount()) {
        const [first] = this.msal.getAllAccounts();
        if (first) this.msal.setActiveAccount(first);
      }
    }

    get account() { return this.msal && this.msal.getActiveAccount(); }

    login() { return this.msal.loginRedirect({ scopes: SCOPES }); }

    logout() { return this.msal.logoutRedirect({ account: this.account }); }

    async token() {
      try {
        const r = await this.msal.acquireTokenSilent({ scopes: SCOPES, account: this.account });
        return r.accessToken;
      } catch (e) {
        if (e instanceof msal.InteractionRequiredAuthError) {
          await this.msal.acquireTokenRedirect({ scopes: SCOPES });
        }
        throw e;
      }
    }
  }

  // ---- Excel (Microsoft Graph) deposu ----------------------------------------

  class ExcelStore {
    constructor(cfg, auth) {
      this.cfg = cfg;
      this.auth = auth;
      this.base = null;       // .../workbook
      this.headers = [];      // Görevler tablosunun başlıkları (Excel'deki sırasıyla)
      this.colIndex = {};     // alan -> sütun indeksi
    }

    get label() { return "Excel (Office 365)"; }

    get features() { return { units: false, subtasks: false }; }

    async graph(method, path, body) {
      const token = await this.auth.token();
      const res = await fetch(path.startsWith("http") ? path : GRAPH + path, {
        method,
        headers: {
          Authorization: `Bearer ${token}`,
          "Content-Type": "application/json"
        },
        body: body ? JSON.stringify(body) : undefined
      });
      if (!res.ok) {
        let msg = `${res.status} ${res.statusText}`;
        try { const j = await res.json(); if (j.error && j.error.message) msg = j.error.message; } catch (_) {}
        throw new Error(`Graph hatası: ${msg}`);
      }
      return res.status === 204 ? null : res.json();
    }

    async resolveWorkbook() {
      if (this.base) return this.base;
      if (this.cfg.shareUrl) {
        // Paylaşım linkini Graph "shares" kimliğine çevir: "u!" + base64url(url)
        const b64 = btoa(unescape(encodeURIComponent(this.cfg.shareUrl)))
          .replace(/=+$/, "").replace(/\//g, "_").replace(/\+/g, "-");
        const item = await this.graph("GET", `/shares/u!${b64}/driveItem?$select=id,parentReference`);
        this.base = `/drives/${item.parentReference.driveId}/items/${item.id}/workbook`;
      } else if (this.cfg.filePath) {
        const p = this.cfg.filePath.split("/").map(encodeURIComponent).join("/");
        this.base = `/me/drive/root:/${p}:/workbook`;
      } else {
        throw new Error("Excel bağlantısı girilmedi. Ayarlar düğmesinden Excel linkini girin.");
      }
      return this.base;
    }

    async readTable(name) {
      const base = await this.resolveWorkbook();
      const r = await this.graph("GET", `${base}/tables/${encodeURIComponent(name)}/range?$select=values`);
      const [headers, ...rows] = r.values;
      return { headers, rows };
    }

    mapHeaders(headers) {
      this.headers = headers;
      this.colIndex = {};
      const norm = headers.map(normalizeHeader);
      for (const [field, title] of Object.entries(TASK_COLUMNS)) {
        const i = norm.indexOf(normalizeHeader(title));
        if (i >= 0) this.colIndex[field] = i;
      }
      const missing = ["id", "unit", "title", "done"].filter((f) => !(f in this.colIndex));
      if (missing.length) {
        throw new Error("Görevler tablosunda eksik sütun(lar): " +
          missing.map((f) => TASK_COLUMNS[f]).join(", "));
      }
    }

    rowToTask(row) {
      const t = {};
      for (const field of Object.keys(TASK_COLUMNS)) {
        const i = this.colIndex[field];
        const v = i === undefined ? "" : row[i];
        if (DATE_FIELDS.includes(field)) t[field] = fromExcelDate(v);
        else if (field === "done") t[field] = toBool(v);
        else t[field] = v === null || v === undefined ? "" : String(v);
      }
      return t;
    }

    // Uygulamanın yönetmediği sütunlar (ör. formül sütunları) için null yazılır;
    // Graph null değerleri atlar, böylece o hücreler korunur.
    taskToRow(t) {
      const row = this.headers.map(() => null);
      for (const [field, i] of Object.entries(this.colIndex)) {
        let v = t[field];
        if (DATE_FIELDS.includes(field)) v = toExcelDate(v);
        else if (field === "done") v = !!v;
        else v = v || "";
        row[i] = v;
      }
      return row;
    }

    async load() {
      const tasksTable = await this.readTable(this.cfg.tasksTable);
      this.mapHeaders(tasksTable.headers);
      const tasks = tasksTable.rows
        .map((r) => this.rowToTask(r))
        .filter((t) => t.id || t.title);

      let units = [];
      try {
        const u = await this.readTable(this.cfg.unitsTable);
        const i = u.headers.map(normalizeHeader).indexOf(normalizeHeader(UNIT_NAME_COLUMN));
        units = u.rows.map((r) => String(r[i >= 0 ? i : 0] || "").trim()).filter(Boolean);
      } catch (_) {
        // Birimler tablosu yoksa görevlerdeki birimler kullanılır.
      }
      if (!units.length) units = [...new Set(tasks.map((t) => t.unit).filter(Boolean))];
      return { tasks, units, unitInfo: units.map((name) => ({ name, owner: "", email: "" })), subs: [] };
    }

    async addTask(task, existing) {
      const base = await this.resolveWorkbook();
      task.id = nextId(existing, this.cfg.idPrefix);
      await this.graph("POST", `${base}/tables/${encodeURIComponent(this.cfg.tasksTable)}/rows`, {
        values: [this.taskToRow(task)]
      });
      return task;
    }

    async updateTask(task) {
      const base = await this.resolveWorkbook();
      // Excel'de satırlar sıralanmış / eklenmiş olabilir; satırı her seferinde GörevNo ile bul.
      const { headers, rows } = await this.readTable(this.cfg.tasksTable);
      this.mapHeaders(headers);
      const index = rows.findIndex((r) => String(r[this.colIndex.id]) === String(task.id));
      if (index < 0) throw new Error(`${task.id} numaralı görev Excel'de bulunamadı.`);
      await this.graph(
        "PATCH",
        `${base}/tables/${encodeURIComponent(this.cfg.tasksTable)}/rows/itemAt(index=${index})`,
        { values: [this.taskToRow(task)] }
      );
      return task;
    }
  }

  // ---- Google E-Tablolar deposu (Apps Script web uygulaması) -------------------

  class SheetsStore {
    constructor(url, token) { this.url = url; this.token = token; this.version = 0; }

    get label() { return "Google E-Tablolar"; }

    // Birim yönetimi ve alt paketler Kod.gs'in 2. sürümüyle gelir.
    get features() { return { units: this.version >= 2, subtasks: this.version >= 2, needsUpdate: this.version < 2 }; }

    async request(init, query) {
      let res;
      try {
        res = await fetch(this.url + (query || ""), { redirect: "follow", ...init });
      } catch (_) {
        throw new Error("Google'a ulaşılamadı. Web uygulaması adresini ve dağıtımdaki \"Erişimi olanlar: Herkes\" ayarını kontrol edin.");
      }
      let data;
      try {
        data = await res.json();
      } catch (_) {
        throw new Error("Google'dan beklenmeyen yanıt geldi. Dağıtımda \"Erişimi olanlar: Herkes\" seçili olmalı ve adres /exec ile bitmeli.");
      }
      if (!data.ok) throw new Error(data.error || "Google E-Tablolar isteği başarısız oldu.");
      return data;
    }

    async load() {
      const data = await this.request({ method: "GET" }, "?action=load&token=" + encodeURIComponent(this.token));
      this.version = data.version || 1;
      const tasks = data.tasks || [];
      let units = data.units || [];
      if (!units.length) units = [...new Set(tasks.map((t) => t.unit).filter(Boolean))];
      const unitInfo = data.unitInfo || units.map((name) => ({ name, owner: "", email: "" }));
      return { tasks, units, unitInfo, subs: data.subs || [] };
    }

    // text/plain gövde, tarayıcının ön kontrol (CORS preflight) isteği atmasını engeller;
    // Apps Script bu isteği kabul etmez.
    post(body) {
      return this.request({
        method: "POST",
        headers: { "Content-Type": "text/plain;charset=utf-8" },
        body: JSON.stringify({ token: this.token, ...body })
      });
    }

    async addTask(task) {
      const data = await this.post({ action: "add", task });
      return data.task;
    }

    async updateTask(task) {
      await this.post({ action: "update", task });
      return task;
    }

    async saveUnit(oldName, unit) {
      const data = await this.post({ action: "unitSave", oldName: oldName || "", unit });
      return { unit: data.unit, renamed: data.renamed || 0 };
    }

    async deleteUnit(name) { await this.post({ action: "unitDelete", name }); }

    async addSub(sub) { return (await this.post({ action: "subAdd", sub })).sub; }

    async updateSub(sub) { await this.post({ action: "subUpdate", sub }); return sub; }

    async deleteSub(id) { await this.post({ action: "subDelete", id }); }
  }

  // ---- Demo deposu (localStorage) -------------------------------------------

  const DEMO_KEY = "istakip-demo-v2";

  function daysFromToday(n) {
    const d = new Date();
    d.setDate(d.getDate() + n);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
  }

  function demoSeed() {
    const units = ["Satın Alma", "İnsan Kaynakları", "Bilgi İşlem", "Muhasebe", "İdari İşler"];
    const rows = [
      ["Satın Alma", "Yıllık kırtasiye ihalesi dosyasının hazırlanması", "Yüksek", -20, 5, "Devam Ediyor"],
      ["Satın Alma", "Tedarikçi performans raporu", "Orta", -30, -3, "Devam Ediyor"],
      ["Satın Alma", "Temizlik malzemesi sipariş listesi", "Düşük", -14, -7, "Tamamlandı"],
      ["İnsan Kaynakları", "Eylül ayı eğitim planının yayınlanması", "Orta", -25, -10, "Tamamlandı"],
      ["İnsan Kaynakları", "Yeni personel oryantasyon dokümanı", "Düşük", -5, 14, "Atandı"],
      ["İnsan Kaynakları", "Yıllık izin planlamasının toplanması", "Orta", -9, 2, "Devam Ediyor"],
      ["Bilgi İşlem", "E-posta sunucusu lisans yenilemesi", "Yüksek", -15, -2, "Devam Ediyor"],
      ["Bilgi İşlem", "Yedekleme testlerinin raporlanması", "Orta", -12, -1, "Tamamlandı"],
      ["Bilgi İşlem", "Toplantı salonu ekran kurulumu", "Düşük", -3, 10, "Atandı"],
      ["Bilgi İşlem", "Kullanıcı parola politikası güncellemesi", "Yüksek", -6, 0, "Devam Ediyor"],
      ["Muhasebe", "3. çeyrek bütçe gerçekleşme tablosu", "Yüksek", -10, 3, "Devam Ediyor"],
      ["Muhasebe", "Avans kapama listesinin kontrolü", "Orta", -18, -6, "Tamamlandı"],
      ["Muhasebe", "Demirbaş sayım tutanaklarının mutabakatı", "Orta", -4, 1, "Atandı"],
      ["İdari İşler", "Araç muayene takvimi", "Orta", -8, -4, "Atandı"],
      ["İdari İşler", "Bina yangın tatbikatı organizasyonu", "Yüksek", -2, 21, "Atandı"],
      ["İdari İşler", "Kış dönemi ısınma bakımı", "Orta", -21, -9, "Tamamlandı"]
    ];
    const tasks = rows.map(([unit, title, priority, a, d, status], i) => ({
      id: "G-" + String(i + 1).padStart(4, "0"),
      assigned: daysFromToday(a),
      unit, title, priority,
      due: daysFromToday(d),
      status,
      done: status === "Tamamlandı",
      doneDate: status === "Tamamlandı" ? daysFromToday(d - 1) : "",
      note: ""
    }));
    const subs = [
      ["G-0001", "Teknik şartnamenin hazırlanması", true],
      ["G-0001", "Piyasa fiyat araştırması", true],
      ["G-0001", "İhale onay yazısı", false],
      ["G-0007", "Lisans tekliflerinin toplanması", true],
      ["G-0007", "Satın alma talebinin açılması", false],
      ["G-0011", "Gider kalemlerinin çekilmesi", false]
    ].map(([taskId, title, done], i, arr) => ({
      id: `${taskId}.${arr.slice(0, i).filter((x) => x[0] === taskId).length + 1}`,
      taskId, title, due: "", done, doneDate: done ? daysFromToday(-2) : ""
    }));
    return { tasks, units, unitInfo: units.map((name) => ({ name, owner: "", email: "" })), subs };
  }

  class DemoStore {
    constructor(cfg) { this.cfg = cfg; this.data = null; }

    get label() { return "Demo (tarayıcı belleği)"; }

    get features() { return { units: true, subtasks: true }; }

    persist() {
      try { localStorage.setItem(DEMO_KEY, JSON.stringify(this.data)); } catch (_) {}
    }

    async load() {
      try {
        const raw = localStorage.getItem(DEMO_KEY);
        if (raw) this.data = JSON.parse(raw);
      } catch (_) {}
      if (!this.data) { this.data = demoSeed(); this.persist(); }
      if (!this.data.unitInfo) this.data.unitInfo = this.data.units.map((name) => ({ name, owner: "", email: "" }));
      if (!this.data.subs) this.data.subs = demoSeed().subs;
      return JSON.parse(JSON.stringify(this.data));
    }

    async addTask(task) {
      task.id = nextId(this.data.tasks, this.cfg.idPrefix);
      this.data.tasks.push({ ...task });
      this.persist();
      return task;
    }

    async updateTask(task) {
      const i = this.data.tasks.findIndex((t) => t.id === task.id);
      if (i < 0) throw new Error(`${task.id} bulunamadı.`);
      this.data.tasks[i] = { ...task };
      this.persist();
      return task;
    }

    async saveUnit(oldName, unit) {
      const name = String(unit.name || "").trim();
      if (!name) throw new Error("Birim adı boş olamaz.");
      const key = (x) => String(x).toLocaleLowerCase("tr-TR");
      const clash = this.data.unitInfo.find((u) => key(u.name) === key(name) && u.name !== oldName);
      if (clash) throw new Error(`"${name}" adında bir birim zaten var.`);
      const saved = { name, owner: unit.owner || "", email: unit.email || "" };
      let renamed = 0;
      if (oldName) {
        const i = this.data.unitInfo.findIndex((u) => u.name === oldName);
        if (i < 0) throw new Error(`"${oldName}" birimi bulunamadı.`);
        this.data.unitInfo[i] = saved;
        this.data.tasks.forEach((t) => { if (t.unit === oldName) { t.unit = name; renamed++; } });
      } else {
        this.data.unitInfo.push(saved);
      }
      this.data.units = this.data.unitInfo.map((u) => u.name);
      this.persist();
      return { unit: saved, renamed };
    }

    async deleteUnit(name) {
      const n = this.data.tasks.filter((t) => t.unit === name).length;
      if (n) throw new Error(`"${name}" birimine bağlı ${n} iş var. Önce bu işleri başka bir birime taşıyın.`);
      this.data.unitInfo = this.data.unitInfo.filter((u) => u.name !== name);
      this.data.units = this.data.unitInfo.map((u) => u.name);
      this.persist();
    }

    async addSub(sub) {
      const title = String(sub.title || "").trim();
      if (!title) throw new Error("Alt paket tanımı boş olamaz.");
      const max = this.data.subs.filter((x) => x.taskId === sub.taskId)
        .reduce((m, x) => Math.max(m, parseInt(String(x.id).split(".").pop(), 10) || 0), 0);
      const saved = { ...sub, title, id: `${sub.taskId}.${max + 1}` };
      this.data.subs.push(saved);
      this.persist();
      return { ...saved };
    }

    async updateSub(sub) {
      const i = this.data.subs.findIndex((x) => x.id === sub.id);
      if (i < 0) throw new Error(`${sub.id} bulunamadı.`);
      this.data.subs[i] = { ...sub };
      this.persist();
      return sub;
    }

    async deleteSub(id) {
      this.data.subs = this.data.subs.filter((x) => x.id !== id);
      this.persist();
    }

    reset() {
      try { localStorage.removeItem(DEMO_KEY); } catch (_) {}
      this.data = null;
    }
  }

  window.IsTakip = { Auth, ExcelStore, SheetsStore, DemoStore, todayIso, TASK_COLUMNS };
})();
