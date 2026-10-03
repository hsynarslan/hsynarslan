/**
 * İş Takip — Google E-Tablolar köprüsü
 *
 * Bu kod Google E-Tablonuzu İş Takip web sayfasına bağlar.
 * Kurulum: E-Tablo > Uzantılar > Apps Script > bu kodun tamamını yapıştırın > Kaydet.
 * Ardından E-Tabloyu yenileyin ve "İş Takip" menüsünden "1. Kurulumu yap"ı çalıştırın.
 */

const SHEET_TASKS = "Görevler";
const SHEET_UNITS = "Birimler";

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
  let units = ss.getSheetByName(SHEET_UNITS);
  if (!units) units = ss.insertSheet(SHEET_UNITS);
  if (!units.getRange(1, 1).getValue()) {
    units.getRange(1, 1, 1, 4).setValues([["BirimKodu", "BirimAdı", "Sorumlu", "E-posta"]]);
    units.getRange(2, 1, 3, 2).setValues([["B01", "Örnek Birim 1"], ["B02", "Örnek Birim 2"], ["B03", "Örnek Birim 3"]]);
  }
  units.getRange(1, 1, 1, 4).setFontWeight("bold").setBackground("#0e2a3f").setFontColor("#ffffff");
  units.setFrozenRows(1);
  units.setColumnWidths(1, 4, 160);

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
    "Görevler ve Birimler sayfaları hazır.\n\n" +
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
      if (body.action === "add") return { ok: true, task: addTask_(body.task) };
      if (body.action === "update") return { ok: true, task: updateTask_(body.task) };
      throw new Error("Bilinmeyen işlem: " + body.action);
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

function headerMap_(sheet) {
  const lastCol = Math.max(sheet.getLastColumn(), 1);
  const headers = sheet.getRange(1, 1, 1, lastCol).getValues()[0].map(normalize_);
  const map = {};
  Object.keys(COLUMNS).forEach((field) => {
    const i = headers.indexOf(normalize_(COLUMNS[field]));
    if (i >= 0) map[field] = i;
  });
  const missing = ["id", "unit", "title", "done"].filter((f) => !(f in map));
  if (missing.length) {
    throw new Error(SHEET_TASKS + " sayfasında eksik sütun: " + missing.map((f) => COLUMNS[f]).join(", "));
  }
  return map;
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

  let units = [];
  const us = ss.getSheetByName(SHEET_UNITS);
  if (us && us.getLastRow() > 1) {
    const uv = us.getDataRange().getValues();
    let i = uv[0].map(normalize_).indexOf(normalize_("BirimAdı"));
    if (i < 0) i = 0;
    units = uv.slice(1).map((r) => String(r[i] || "").trim()).filter(String);
  }
  return { tasks: tasks, units: units };
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
  const row = lastUsed + 1;
  Object.keys(map).forEach((f) => sheet.getRange(row, map[f] + 1).setValue(toCell_(f, saved[f])));
  return saved;
}

function updateTask_(task) {
  const sheet = tasksSheet_();
  const map = headerMap_(sheet);
  const ids = sheet.getRange(1, map.id + 1, sheet.getLastRow(), 1).getValues();
  const idx = ids.findIndex((r, i) => i > 0 && String(r[0]) === String(task.id));
  if (idx < 0) throw new Error(task.id + " numaralı görev tabloda bulunamadı.");
  // Yalnızca uygulamanın yönettiği sütunlara yazılır; diğer sütunlar (ör. formüller) korunur.
  Object.keys(map).forEach((f) => {
    if (f !== "id") sheet.getRange(idx + 1, map[f] + 1).setValue(toCell_(f, task[f]));
  });
  return task;
}
