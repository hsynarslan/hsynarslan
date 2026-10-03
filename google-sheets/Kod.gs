/**
 * İş Takip — Google E-Tablolar köprüsü
 *
 * Bu kod Google E-Tablonuzu İş Takip web sayfasına bağlar.
 * Kurulum: E-Tablo > Uzantılar > Apps Script > bu kodun tamamını yapıştırın > Kaydet.
 * Ardından E-Tabloyu yenileyin ve "İş Takip" menüsünden "1. Kurulumu yap"ı çalıştırın.
 */

const SHEET_TASKS = "Görevler";
const SHEET_UNITS = "Birimler";
const SHEET_SUBS = "AltGörevler";

// Web sayfası bu numaraya bakarak hangi özelliklerin desteklendiğini anlar.
const API_VERSION = 2;

// Alan adı -> E-Tablodaki başlık. Sütun sırası önemli değil, başlık adına göre eşleştirilir.
const COLUMNS = {
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
const UNIT_COLUMNS = { code: "BirimKodu", name: "BirimAdı", owner: "Sorumlu", email: "E-posta" };
const SUB_COLUMNS = {
  id: "AltNo",
  taskId: "GörevNo",
  title: "Tanım",
  due: "TerminTarihi",
  done: "TamamlandıMı",
  doneDate: "TamamlanmaTarihi"
};
const DATE_FIELDS = ["assigned", "due", "doneDate"];
const STATUSES = ["Atandı", "Devam Ediyor", "Tamamlandı", "İptal"];
const PRIORITIES = ["Yüksek", "Orta", "Düşük"];
const ID_PREFIX = "G-";
const MAX_ROWS = 1000;

// ---- Menü ve kurulum --------------------------------------------------------

function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu("İş Takip")
    .addItem("1. Kurulumu yap", "kurulum")
    .addItem("2. Bağlantı anahtarını göster", "anahtariGoster")
    .addItem("Bağlantı anahtarını yenile", "anahtariYenile")
    .addToUi();
}

function kurulum() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const headers = Object.values(COLUMNS);

  // Birimler
  const unitsExisted = !!ss.getSheetByName(SHEET_UNITS);
  const units = ensureSheet_(SHEET_UNITS, Object.values(UNIT_COLUMNS));
  if (!unitsExisted || units.getLastRow() < 2) {
    units.getRange(2, 1, 3, 2).setValues([["B01", "Örnek Birim 1"], ["B02", "Örnek Birim 2"], ["B03", "Örnek Birim 3"]]);
  }
  units.setColumnWidths(1, 4, 160);

  // Alt paketler
  const subs = subsSheet_();
  const sm = subMap_(subs);
  subs.getRange(2, sm.done + 1, MAX_ROWS - 1, 1).insertCheckboxes();
  [sm.due, sm.doneDate].forEach((c) => subs.getRange(2, c + 1, MAX_ROWS - 1, 1).setNumberFormat("dd.mm.yyyy"));
  subs.setColumnWidth(sm.title + 1, 360);

  // Görevler
  let tasks = ss.getSheetByName(SHEET_TASKS);
  if (!tasks) tasks = ss.insertSheet(SHEET_TASKS, 0);
  if (!tasks.getRange(1, 1).getValue()) {
    tasks.getRange(1, 1, 1, headers.length).setValues([headers]);
  }
  const map = headerMap_(tasks);
  tasks.getRange(1, 1, 1, tasks.getLastColumn()).setFontWeight("bold").setBackground("#0e2a3f").setFontColor("#ffffff");
  tasks.setFrozenRows(1);

  const col = (field) => map[field] + 1;
  const rows = MAX_ROWS - 1;
  DATE_FIELDS.forEach((f) => tasks.getRange(2, col(f), rows, 1).setNumberFormat("dd.mm.yyyy"));
  tasks.getRange(2, col("done"), rows, 1).insertCheckboxes();
  tasks.getRange(2, col("status"), rows, 1).setDataValidation(
    SpreadsheetApp.newDataValidation().requireValueInList(STATUSES, true).setAllowInvalid(false).build());
  tasks.getRange(2, col("priority"), rows, 1).setDataValidation(
    SpreadsheetApp.newDataValidation().requireValueInList(PRIORITIES, true).setAllowInvalid(false).build());
  tasks.getRange(2, col("unit"), rows, 1).setDataValidation(
    SpreadsheetApp.newDataValidation().requireValueInRange(units.getRange("B2:B200"), true).setAllowInvalid(true).build());
  tasks.setColumnWidth(col("title"), 360);
  tasks.setColumnWidth(col("note"), 240);

  // Renklendirme: tamamlananlar yeşil, gecikenler kırmızı
  const letter = (field) => columnLetter_(col(field));
  const all = tasks.getRange(2, 1, rows, tasks.getLastColumn());
  tasks.setConditionalFormatRules([
    SpreadsheetApp.newConditionalFormatRule()
      .whenFormulaSatisfied(`=$${letter("done")}2=TRUE`)
      .setBackground("#e5f5e5").setFontColor("#5d6b7e").setRanges([all]).build(),
    SpreadsheetApp.newConditionalFormatRule()
      .whenFormulaSatisfied(`=AND($${letter("done")}2<>TRUE,$${letter("due")}2<>"",$${letter("due")}2<TODAY(),$${letter("status")}2<>"İptal")`)
      .setBackground("#fcebeb").setFontColor("#b42323").setRanges([all]).build()
  ]);

  // Boş varsayılan sayfayı kaldır
  ["Sayfa1", "Sheet1"].forEach((name) => {
    const s = ss.getSheetByName(name);
    if (s && s.getLastRow() === 0 && ss.getSheets().length > 2) ss.deleteSheet(s);
  });

  const token = getToken_(true);
  SpreadsheetApp.getUi().alert(
    "Kurulum tamamlandı",
    "Görevler, Birimler ve AltGörevler sayfaları hazır.\n\n" +
    "Bağlantı anahtarınız:\n" + token + "\n\n" +
    "Sonraki adım: Dağıt > Yeni dağıtım > Web uygulaması.\n" +
    "Anahtarı daha sonra İş Takip > Bağlantı anahtarını göster menüsünden de görebilirsiniz.",
    SpreadsheetApp.getUi().ButtonSet.OK);
}

function anahtariGoster() {
  const ui = SpreadsheetApp.getUi();
  const token = getToken_(false);
  ui.alert("Bağlantı anahtarı", token || "Henüz anahtar yok. Önce \"1. Kurulumu yap\" menüsünü çalıştırın.", ui.ButtonSet.OK);
}

function anahtariYenile() {
  const ui = SpreadsheetApp.getUi();
  const ok = ui.alert("Anahtar yenilensin mi?",
    "Eski anahtar çalışmaz hale gelir; web sayfasındaki Ayarlar'a yeni anahtarı girmeniz gerekir.",
    ui.ButtonSet.YES_NO);
  if (ok !== ui.Button.YES) return;
  PropertiesService.getScriptProperties().deleteProperty("TOKEN");
  ui.alert("Yeni bağlantı anahtarı", getToken_(true), ui.ButtonSet.OK);
}

// ---- Web uygulaması uç noktaları ---------------------------------------------

function doGet(e) {
  return respond_(() => {
    checkToken_(e.parameter.token);
    return Object.assign({ ok: true }, readAll_());
  });
}

function doPost(e) {
  return respond_(() => {
    const body = JSON.parse(e.postData.contents);
    checkToken_(body.token);
    const lock = LockService.getScriptLock();
    lock.waitLock(20000);
    try {
      switch (body.action) {
        case "add": return { ok: true, task: addTask_(body.task) };
        case "update": return { ok: true, task: updateTask_(body.task) };
        case "unitSave": return Object.assign({ ok: true }, saveUnit_(body.oldName, body.unit));
        case "unitDelete": return { ok: true, deleted: deleteUnit_(body.name) };
        case "subAdd": return { ok: true, sub: addSub_(body.sub) };
        case "subUpdate": return { ok: true, sub: updateSub_(body.sub) };
        case "subDelete": return { ok: true, deleted: deleteSub_(body.id) };
        default: throw new Error("Bilinmeyen işlem: " + body.action);
      }
    } finally {
      lock.releaseLock();
    }
  });
}

// ---- Yardımcılar -----------------------------------------------------------

function respond_(fn) {
  let out;
  try {
    out = fn();
  } catch (err) {
    out = { ok: false, error: String((err && err.message) || err) };
  }
  return ContentService.createTextOutput(JSON.stringify(out)).setMimeType(ContentService.MimeType.JSON);
}

function getToken_(create) {
  const props = PropertiesService.getScriptProperties();
  let token = props.getProperty("TOKEN");
  if (!token && create) {
    token = Utilities.getUuid().replace(/-/g, "");
    props.setProperty("TOKEN", token);
  }
  return token;
}

function checkToken_(token) {
  const real = getToken_(false);
  if (!real) throw new Error("Kurulum yapılmamış. E-Tabloda İş Takip > 1. Kurulumu yap menüsünü çalıştırın.");
  if (String(token || "") !== real) throw new Error("Bağlantı anahtarı hatalı.");
}

function normalize_(s) {
  return String(s || "").toLocaleLowerCase("tr-TR").replace(/[\s_.-]/g, "");
}

function columnsMap_(sheet, columns, required) {
  const lastCol = Math.max(sheet.getLastColumn(), 1);
  const headers = sheet.getRange(1, 1, 1, lastCol).getValues()[0].map(normalize_);
  const map = {};
  Object.keys(columns).forEach((field) => {
    const i = headers.indexOf(normalize_(columns[field]));
    if (i >= 0) map[field] = i;
  });
  const missing = required.filter((f) => !(f in map));
  if (missing.length) {
    throw new Error(sheet.getName() + " sayfasında eksik sütun: " + missing.map((f) => columns[f]).join(", "));
  }
  return map;
}

function headerMap_(sheet) { return columnsMap_(sheet, COLUMNS, ["id", "unit", "title", "done"]); }
function unitMap_(sheet) { return columnsMap_(sheet, UNIT_COLUMNS, ["name"]); }
function subMap_(sheet) { return columnsMap_(sheet, SUB_COLUMNS, ["id", "taskId", "title", "done"]); }

// Sayfa yoksa oluşturur, başlık satırı boşsa başlıkları yazar.
function ensureSheet_(name, headers) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  let sheet = ss.getSheetByName(name);
  if (!sheet) sheet = ss.insertSheet(name);
  if (!sheet.getRange(1, 1).getValue()) {
    sheet.getRange(1, 1, 1, headers.length).setValues([headers]);
    sheet.getRange(1, 1, 1, headers.length).setFontWeight("bold").setBackground("#0e2a3f").setFontColor("#ffffff");
    sheet.setFrozenRows(1);
  }
  return sheet;
}

function unitsSheet_() { return ensureSheet_(SHEET_UNITS, Object.values(UNIT_COLUMNS)); }
function subsSheet_() { return ensureSheet_(SHEET_SUBS, Object.values(SUB_COLUMNS)); }

// Dolu son satırın numarası (onay kutusu gibi boş görünen satırlar sayılmaz).
function lastUsedRow_(values, isEmpty) {
  let last = 1;
  values.forEach((r, i) => { if (i > 0 && !isEmpty(r)) last = i + 1; });
  return last;
}

function writeFields_(sheet, row, map, obj, skip) {
  Object.keys(map).forEach((f) => {
    if (skip && skip.indexOf(f) >= 0) return;
    sheet.getRange(row, map[f] + 1).setValue(toCell_(f, obj[f]));
  });
}

function tasksSheet_() {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(SHEET_TASKS);
  if (!sheet) throw new Error("\"" + SHEET_TASKS + "\" sayfası bulunamadı. İş Takip > 1. Kurulumu yap menüsünü çalıştırın.");
  return sheet;
}

function columnLetter_(n) {
  let s = "";
  while (n > 0) { const m = (n - 1) % 26; s = String.fromCharCode(65 + m) + s; n = Math.floor((n - 1) / 26); }
  return s;
}

function fromCell_(field, v, tz) {
  if (DATE_FIELDS.indexOf(field) >= 0) {
    if (v instanceof Date) return Utilities.formatDate(v, tz, "yyyy-MM-dd");
    return "";
  }
  if (field === "done") return v === true || String(v).toUpperCase() === "TRUE";
  return v === null || v === undefined ? "" : String(v);
}

function toCell_(field, v) {
  if (DATE_FIELDS.indexOf(field) >= 0) {
    if (!v) return "";
    const p = String(v).split("-").map(Number);
    return new Date(p[0], p[1] - 1, p[2]);
  }
  if (field === "done") return !!v;
  return v === null || v === undefined ? "" : String(v);
}

function isEmptyRow_(row, map) {
  return !String(row[map.id] || "").trim() && !String(row[map.title] || "").trim();
}

function readAll_() {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const tz = ss.getSpreadsheetTimeZone();
  const sheet = tasksSheet_();
  const map = headerMap_(sheet);
  const values = sheet.getDataRange().getValues().slice(1);
  const tasks = values.filter((r) => !isEmptyRow_(r, map)).map((r) => {
    const t = {};
    Object.keys(COLUMNS).forEach((f) => { t[f] = f in map ? fromCell_(f, r[map[f]], tz) : ""; });
    return t;
  });

  const unitInfo = readUnits_();
  const units = unitInfo.map((u) => u.name);

  let subs = [];
  const ssh = ss.getSheetByName(SHEET_SUBS);
  if (ssh && ssh.getLastRow() > 1) {
    const sm = subMap_(ssh);
    subs = ssh.getDataRange().getValues().slice(1)
      .filter((r) => String(r[sm.id] || "").trim() && String(r[sm.taskId] || "").trim())
      .map((r) => {
        const o = {};
        Object.keys(SUB_COLUMNS).forEach((f) => { o[f] = f in sm ? fromCell_(f, r[sm[f]], tz) : ""; });
        return o;
      });
  }
  return { version: API_VERSION, tasks: tasks, units: units, unitInfo: unitInfo, subs: subs };
}

// ---- Birimler ----------------------------------------------------------------

function readUnits_() {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(SHEET_UNITS);
  if (!sheet || sheet.getLastRow() < 2) return [];
  const map = unitMap_(sheet);
  return sheet.getDataRange().getValues().slice(1)
    .map((r) => ({
      code: "code" in map ? String(r[map.code] || "") : "",
      name: String(r[map.name] || "").trim(),
      owner: "owner" in map ? String(r[map.owner] || "") : "",
      email: "email" in map ? String(r[map.email] || "") : ""
    }))
    .filter((u) => u.name);
}

function findUnitRow_(sheet, map, name) {
  const values = sheet.getDataRange().getValues();
  const key = normalize_(name);
  const i = values.findIndex((r, idx) => idx > 0 && normalize_(r[map.name]) === key);
  return i < 0 ? -1 : i + 1;
}

function countTasksOfUnit_(name) {
  const sheet = tasksSheet_();
  const map = headerMap_(sheet);
  return sheet.getDataRange().getValues().slice(1)
    .filter((r) => !isEmptyRow_(r, map) && String(r[map.unit]) === name).length;
}

function saveUnit_(oldName, unit) {
  const name = String((unit && unit.name) || "").trim();
  if (!name) throw new Error("Birim adı boş olamaz.");
  const sheet = unitsSheet_();
  const map = unitMap_(sheet);

  const clash = findUnitRow_(sheet, map, name);
  const own = oldName ? findUnitRow_(sheet, map, oldName) : -1;
  if (clash > 0 && clash !== own) throw new Error("\"" + name + "\" adında bir birim zaten var.");

  const saved = { code: "", name: name, owner: String(unit.owner || ""), email: String(unit.email || "") };
  let renamed = 0;

  if (oldName) {
    if (own < 0) throw new Error("\"" + oldName + "\" birimi bulunamadı.");
    if ("code" in map) saved.code = String(sheet.getRange(own, map.code + 1).getValue() || "");
    writeFields_(sheet, own, map, saved, ["code"]);
    if (oldName !== name) {
      // Bu birime bağlı işlerin birim adını da güncelle
      const ts = tasksSheet_();
      const tm = headerMap_(ts);
      const tv = ts.getDataRange().getValues();
      tv.forEach((r, i) => {
        if (i > 0 && String(r[tm.unit]) === oldName) { ts.getRange(i + 1, tm.unit + 1).setValue(name); renamed++; }
      });
    }
  } else {
    const values = sheet.getDataRange().getValues();
    let max = 0;
    if ("code" in map) values.slice(1).forEach((r) => {
      const n = parseInt(String(r[map.code]).replace(/\D/g, ""), 10);
      if (!isNaN(n) && n > max) max = n;
    });
    saved.code = "B" + ("0" + (max + 1)).slice(-2);
    const row = lastUsedRow_(values, (r) => !String(r[map.name] || "").trim()) + 1;
    writeFields_(sheet, row, map, saved);
  }
  return { unit: saved, renamed: renamed };
}

function deleteUnit_(name) {
  const n = countTasksOfUnit_(name);
  if (n > 0) throw new Error("\"" + name + "\" birimine bağlı " + n + " iş var. Önce bu işleri başka bir birime taşıyın.");
  const sheet = unitsSheet_();
  const row = findUnitRow_(sheet, unitMap_(sheet), name);
  if (row < 0) throw new Error("\"" + name + "\" birimi bulunamadı.");
  sheet.deleteRow(row);
  return name;
}

// ---- Alt paketler ------------------------------------------------------------

function findSubRow_(sheet, map, id) {
  const ids = sheet.getRange(1, map.id + 1, Math.max(sheet.getLastRow(), 1), 1).getValues();
  const i = ids.findIndex((r, idx) => idx > 0 && String(r[0]) === String(id));
  return i < 0 ? -1 : i + 1;
}

function addSub_(sub) {
  const title = String((sub && sub.title) || "").trim();
  if (!title) throw new Error("Alt paket tanımı boş olamaz.");
  const ts = tasksSheet_();
  const tm = headerMap_(ts);
  const taskIds = ts.getRange(1, tm.id + 1, Math.max(ts.getLastRow(), 1), 1).getValues().map((r) => String(r[0]));
  if (taskIds.indexOf(String(sub.taskId)) < 1) throw new Error(sub.taskId + " numaralı görev bulunamadı.");

  const sheet = subsSheet_();
  const map = subMap_(sheet);
  const values = sheet.getDataRange().getValues();
  let max = 0;
  values.slice(1).forEach((r) => {
    if (String(r[map.taskId]) !== String(sub.taskId)) return;
    const n = parseInt(String(r[map.id]).split(".").pop(), 10);
    if (!isNaN(n) && n > max) max = n;
  });
  const saved = Object.assign({}, sub, { title: title, id: sub.taskId + "." + (max + 1) });
  const row = lastUsedRow_(values, (r) => !String(r[map.id] || "").trim()) + 1;
  writeFields_(sheet, row, map, saved);
  return saved;
}

function updateSub_(sub) {
  const sheet = subsSheet_();
  const map = subMap_(sheet);
  const row = findSubRow_(sheet, map, sub.id);
  if (row < 0) throw new Error(sub.id + " numaralı alt paket bulunamadı.");
  writeFields_(sheet, row, map, sub, ["id", "taskId"]);
  return sub;
}

function deleteSub_(id) {
  const sheet = subsSheet_();
  const row = findSubRow_(sheet, subMap_(sheet), id);
  if (row < 0) throw new Error(id + " numaralı alt paket bulunamadı.");
  sheet.deleteRow(row);
  return id;
}

function addTask_(task) {
  const sheet = tasksSheet_();
  const map = headerMap_(sheet);
  const values = sheet.getDataRange().getValues();

  let max = 0, lastUsed = 1;
  values.forEach((r, i) => {
    if (i === 0) return;
    if (!isEmptyRow_(r, map)) lastUsed = i + 1;
    const n = parseInt(String(r[map.id]).replace(/\D/g, ""), 10);
    if (!isNaN(n) && n > max) max = n;
  });

  const saved = Object.assign({}, task, { id: ID_PREFIX + ("000" + (max + 1)).slice(-4) });
  writeFields_(sheet, lastUsed + 1, map, saved);
  return saved;
}

function updateTask_(task) {
  const sheet = tasksSheet_();
  const map = headerMap_(sheet);
  const ids = sheet.getRange(1, map.id + 1, sheet.getLastRow(), 1).getValues();
  const idx = ids.findIndex((r, i) => i > 0 && String(r[0]) === String(task.id));
  if (idx < 0) throw new Error(task.id + " numaralı görev tabloda bulunamadı.");
  // Yalnızca uygulamanın yönettiği sütunlara yazılır; diğer sütunlar (ör. formüller) korunur.
  writeFields_(sheet, idx + 1, map, task, ["id"]);
  return task;
}
