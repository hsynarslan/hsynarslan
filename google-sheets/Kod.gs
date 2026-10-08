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
const SHEET_LOG = "Geçmiş";

// Web sayfası bu numaraya bakarak hangi özelliklerin desteklendiğini anlar.
const API_VERSION = 4;

// Günlük e-posta: her sabah bu saatte (Apps Script ±15 dakika esneklikle çalıştırır)
const EMAIL_HOUR = 8;
const EMAIL_MINUTE = 45;
const EMAIL_TZ = "Europe/Istanbul";

// Web şifresi: art arda hatalı denemelerde geçici kilit
const PW_MIN = 8;
const PW_MAX_FAILS = 10;
const PW_LOCK_SECONDS = 15 * 60;

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
  note: "Not",
  repeat: "Tekrar",
  link: "Bağlantı",
  updatedAt: "SonGüncelleme",
  updatedBy: "Güncelleyen"
};
// Sonradan eklenen sütunlar: eski tablolarda yoksa kurulum sona ekler, yoksa da sayfa çalışır.
const OPTIONAL_COLUMNS = ["repeat", "link", "updatedAt", "updatedBy"];
const LOG_COLUMNS = ["Tarih", "Kişi", "GörevNo", "İşlem", "Ayrıntı"];
const FIELD_LABELS = {
  assigned: "Atama tarihi", unit: "Birim", title: "Tanım", priority: "Öncelik", due: "Termin",
  status: "Durum", doneDate: "Tamamlanma", note: "Not", repeat: "Tekrar", link: "Bağlantı"
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
const REPEATS = ["Haftalık", "Aylık", "Yıllık"];
const ID_PREFIX = "G-";
const MAX_ROWS = 1000;

// ---- Menü ve kurulum --------------------------------------------------------

function onOpen() {
  SpreadsheetApp.getUi()
    .createMenu("İş Takip")
    .addItem("1. Kurulumu yap", "kurulum")
    .addItem("2. Bağlantı anahtarını göster", "anahtariGoster")
    .addItem("3. Web şifresi belirle", "webSifresiBelirle")
    .addItem("Bağlantı anahtarını yenile", "anahtariYenile")
    .addSeparator()
    .addItem("Günlük e-postayı aç (her sabah " + EMAIL_HOUR + ":" + EMAIL_MINUTE + ")", "gunlukEpostaAc")
    .addItem("Günlük e-postayı kapat", "gunlukEpostaKapat")
    .addItem("Günlük e-postayı şimdi gönder (deneme)", "gunlukEpostaDene")
    .addItem("Birim sorumlularına da gönder: aç / kapat", "birimEpostaDegistir")
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
  ensureColumns_(tasks, OPTIONAL_COLUMNS.map((f) => COLUMNS[f]));
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
  tasks.getRange(2, col("repeat"), rows, 1).setDataValidation(
    SpreadsheetApp.newDataValidation().requireValueInList(REPEATS, true).setAllowInvalid(false).build());
  tasks.getRange(2, col("updatedAt"), rows, 1).setNumberFormat("@");

  // Geçmiş: her değişiklik bir satır
  const log = logSheet_();
  log.setColumnWidth(5, 420);

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
    "Görevler, Birimler, AltGörevler ve Geçmiş sayfaları hazır.\n\n" +
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

// ---- Web şifresi -------------------------------------------------------------------
// Web sayfası her cihazda yalnızca bu şifreyi sorar. Şifre düz metin olarak değil,
// rastgele tuz ile SHA-256 özeti olarak Script Properties'te saklanır.

function webSifresiBelirle() {
  const html = HtmlService.createHtmlOutput(
    '<style>body{font-family:Arial,sans-serif;font-size:14px;margin:0;padding:4px 2px}' +
    'label{display:block;margin:10px 0 4px;font-weight:bold}input{width:100%;box-sizing:border-box;padding:8px;font-size:14px}' +
    'button{margin-top:14px;padding:8px 16px;font-size:14px;background:#1a73e8;color:#fff;border:0;border-radius:4px;cursor:pointer}' +
    '#m{margin-top:10px;color:#c5221f}</style>' +
    '<div>İş Takip sayfasında her cihazda bu şifre sorulacak (en az ' + PW_MIN + ' karakter).</div>' +
    '<label>Yeni şifre</label><input id="a" type="password" autofocus>' +
    '<label>Yeni şifre (tekrar)</label><input id="b" type="password">' +
    '<button id="k">Kaydet</button><div id="m"></div>' +
    '<script>' +
    'document.getElementById("k").onclick=function(){' +
    'var a=document.getElementById("a").value,b=document.getElementById("b").value,m=document.getElementById("m");' +
    'if(a.length<' + PW_MIN + '){m.textContent="Şifre en az ' + PW_MIN + ' karakter olmalı.";return;}' +
    'if(a!==b){m.textContent="Şifreler aynı değil.";return;}' +
    'this.disabled=true;m.style.color="#555";m.textContent="Kaydediliyor…";' +
    'google.script.run.withSuccessHandler(function(){m.style.color="#188038";m.textContent="Şifre kaydedildi. Bu pencereyi kapatabilirsiniz.";})' +
    '.withFailureHandler(function(e){m.style.color="#c5221f";m.textContent=e.message;document.getElementById("k").disabled=false;})' +
    '.sifreKaydet(a);};' +
    '</script>'
  ).setWidth(380).setHeight(290);
  SpreadsheetApp.getUi().showModalDialog(html, "Web şifresi belirle");
}

// HTML penceresinden çağrılır
function sifreKaydet(password) {
  password = String(password || "");
  if (password.length < PW_MIN) throw new Error("Şifre en az " + PW_MIN + " karakter olmalı.");
  getToken_(true); // kurulum yapılmamışsa anahtarı da oluştur
  const salt = Utilities.getUuid();
  const props = PropertiesService.getScriptProperties();
  props.setProperty("PW_SALT", salt);
  props.setProperty("PW_HASH", hashPassword_(salt, password));
  CacheService.getScriptCache().remove("pw_fails");
  return true;
}

function hashPassword_(salt, password) {
  const bytes = Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, salt + ":" + password, Utilities.Charset.UTF_8);
  return bytes.map((b) => ("0" + (b & 0xff).toString(16)).slice(-2)).join("");
}

// ---- Web uygulaması uç noktaları ---------------------------------------------

function doGet(e) {
  return respond_(() => {
    checkToken_(e.parameter.token);
    rememberPage_(e.parameter.page);
    return Object.assign({ ok: true }, readAll_());
  });
}

function doPost(e) {
  return respond_(() => {
    const body = JSON.parse(e.postData.contents);
    checkToken_(body.token);
    const actor = String(body.actor || "").trim().slice(0, 60) || "Bilinmeyen";
    const lock = LockService.getScriptLock();
    lock.waitLock(20000);
    try {
      switch (body.action) {
        case "add": return { ok: true, task: addTask_(body.task, actor) };
        case "update": return { ok: true, task: updateTask_(body.task, body.base, actor) };
        case "unitSave": return Object.assign({ ok: true }, saveUnit_(body.oldName, body.unit, actor));
        case "unitDelete": return { ok: true, deleted: deleteUnit_(body.name, actor) };
        case "subAdd": return { ok: true, sub: addSub_(body.sub, actor) };
        case "subUpdate": return { ok: true, sub: updateSub_(body.sub, actor) };
        case "subDelete": return { ok: true, deleted: deleteSub_(body.id, actor) };
        case "history": return { ok: true, entries: readLog_(body.id) };
        case "comment": return { ok: true, entry: addComment_(body.id, body.text, actor) };
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

// İstekler bağlantı anahtarıyla ya da web şifresiyle yetkilendirilir.
function checkToken_(token) {
  const real = getToken_(false);
  if (!real) throw new Error("Kurulum yapılmamış. E-Tabloda İş Takip > 1. Kurulumu yap menüsünü çalıştırın.");
  token = String(token || "");
  if (token === real) return;

  const props = PropertiesService.getScriptProperties();
  const hash = props.getProperty("PW_HASH");
  if (hash) {
    const cache = CacheService.getScriptCache();
    const fails = Number(cache.get("pw_fails") || 0);
    if (fails >= PW_MAX_FAILS) {
      throw new Error("Çok fazla hatalı deneme yapıldı. " + Math.round(PW_LOCK_SECONDS / 60) + " dakika sonra tekrar deneyin.");
    }
    if (hashPassword_(props.getProperty("PW_SALT"), token) === hash) return;
    cache.put("pw_fails", String(fails + 1), PW_LOCK_SECONDS);
  }
  throw new Error("Şifre ya da bağlantı anahtarı hatalı.");
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

// Başlık satırında olmayan sütunları sona ekler (eski tablolar için).
function ensureColumns_(sheet, headers) {
  const lastCol = Math.max(sheet.getLastColumn(), 1);
  const have = sheet.getRange(1, 1, 1, lastCol).getValues()[0].map(normalize_);
  const missing = headers.filter((h) => have.indexOf(normalize_(h)) < 0);
  if (missing.length) sheet.getRange(1, lastCol + 1, 1, missing.length).setValues([missing]);
}

function logSheet_() { return ensureSheet_(SHEET_LOG, LOG_COLUMNS); }

function log_(actor, id, action, detail) {
  const sheet = logSheet_();
  const entry = [new Date().toISOString(), actor || "", id || "", action, detail || ""];
  sheet.appendRow(entry);
  return { at: entry[0], by: entry[1], id: entry[2], action: entry[3], detail: entry[4] };
}

function readLog_(id) {
  const sheet = SpreadsheetApp.getActiveSpreadsheet().getSheetByName(SHEET_LOG);
  if (!sheet || sheet.getLastRow() < 2) return [];
  const key = String(id || "");
  return sheet.getRange(2, 1, sheet.getLastRow() - 1, 5).getValues()
    .filter((r) => String(r[2]) === key || String(r[2]).indexOf(key + ".") === 0)
    .map((r) => ({ at: r[0] instanceof Date ? r[0].toISOString() : String(r[0]), by: String(r[1]), id: String(r[2]), action: String(r[3]), detail: String(r[4]) }))
    .slice(-200);
}

function addComment_(id, text, actor) {
  text = String(text || "").trim().slice(0, 2000);
  if (!text) throw new Error("Yorum boş olamaz.");
  return log_(actor, id, "Yorum", text);
}

function rememberPage_(page) {
  page = String(page || "");
  if (!/^https:\/\/[^\s"<>]+$/.test(page)) return;
  const props = PropertiesService.getScriptProperties();
  if (props.getProperty("PAGE_URL") !== page) props.setProperty("PAGE_URL", page);
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
  if (field === "updatedAt" && v instanceof Date) return v.toISOString();
  return v === null || v === undefined ? "" : String(v);
}

function toCell_(field, v) {
  if (DATE_FIELDS.indexOf(field) >= 0) {
    if (!v) return "";
    const p = String(v).split("-").map(Number);
    return new Date(p[0], p[1] - 1, p[2]);
  }
  if (field === "done") return !!v;
  // Baştaki kesme işareti E-Tablonun zaman damgasını tarihe çevirmesini engeller
  if (field === "updatedAt") return v ? "'" + String(v) : "";
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

function saveUnit_(oldName, unit, actor) {
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
  log_(actor, "", oldName ? "Birim güncellendi" : "Birim eklendi", oldName && oldName !== name ? oldName + " → " + name : name);
  return { unit: saved, renamed: renamed };
}

function deleteUnit_(name, actor) {
  const n = countTasksOfUnit_(name);
  if (n > 0) throw new Error("\"" + name + "\" birimine bağlı " + n + " iş var. Önce bu işleri başka bir birime taşıyın.");
  const sheet = unitsSheet_();
  const row = findUnitRow_(sheet, unitMap_(sheet), name);
  if (row < 0) throw new Error("\"" + name + "\" birimi bulunamadı.");
  sheet.deleteRow(row);
  log_(actor, "", "Birim silindi", name);
  return name;
}

// ---- Alt paketler ------------------------------------------------------------

function findSubRow_(sheet, map, id) {
  const ids = sheet.getRange(1, map.id + 1, Math.max(sheet.getLastRow(), 1), 1).getValues();
  const i = ids.findIndex((r, idx) => idx > 0 && String(r[0]) === String(id));
  return i < 0 ? -1 : i + 1;
}

function addSub_(sub, actor) {
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
  log_(actor, saved.id, "Alt paket eklendi", title);
  return saved;
}

function updateSub_(sub, actor) {
  const sheet = subsSheet_();
  const map = subMap_(sheet);
  const row = findSubRow_(sheet, map, sub.id);
  if (row < 0) throw new Error(sub.id + " numaralı alt paket bulunamadı.");
  const wasDone = sheet.getRange(row, map.done + 1).getValue() === true;
  writeFields_(sheet, row, map, sub, ["id", "taskId"]);
  if (wasDone !== !!sub.done) log_(actor, sub.id, sub.done ? "Alt paket tamamlandı" : "Alt paket yeniden açıldı", sub.title);
  return sub;
}

function deleteSub_(id, actor) {
  const sheet = subsSheet_();
  const map = subMap_(sheet);
  const row = findSubRow_(sheet, map, id);
  if (row < 0) throw new Error(id + " numaralı alt paket bulunamadı.");
  const title = sheet.getRange(row, map.title + 1).getValue();
  sheet.deleteRow(row);
  log_(actor, id, "Alt paket silindi", title);
  return id;
}

function addTask_(task, actor) {
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

  const saved = Object.assign({}, task, {
    id: ID_PREFIX + ("000" + (max + 1)).slice(-4), updatedAt: new Date().toISOString(), updatedBy: actor
  });
  writeFields_(sheet, lastUsed + 1, map, saved);
  log_(actor, saved.id, "Oluşturuldu", saved.title);
  return saved;
}

// base: sayfanın görevi yüklediği andaki SonGüncelleme değeri. Arada başkası kaydettiyse
// değişiklik yazılmaz ve "CONFLICT|" ile başlayan bir hata döner.
function updateTask_(task, base, actor) {
  const sheet = tasksSheet_();
  const map = headerMap_(sheet);
  const tz = SpreadsheetApp.getActiveSpreadsheet().getSpreadsheetTimeZone();
  const ids = sheet.getRange(1, map.id + 1, sheet.getLastRow(), 1).getValues();
  const idx = ids.findIndex((r, i) => i > 0 && String(r[0]) === String(task.id));
  if (idx < 0) throw new Error(task.id + " numaralı görev tabloda bulunamadı.");
  const row = sheet.getRange(idx + 1, 1, 1, sheet.getLastColumn()).getValues()[0];
  const old = {};
  Object.keys(map).forEach((f) => { old[f] = fromCell_(f, row[map[f]], tz); });

  if ("updatedAt" in map && base !== undefined && String(base || "") !== String(old.updatedAt || "")) {
    throw new Error("CONFLICT|" + (old.updatedBy || "Başka biri") + "|" + (old.updatedAt || ""));
  }

  const saved = Object.assign({}, task, { updatedAt: new Date().toISOString(), updatedBy: actor });
  // Yalnızca uygulamanın yönettiği sütunlara yazılır; diğer sütunlar (ör. formüller) korunur.
  writeFields_(sheet, idx + 1, map, saved, ["id"]);

  const changes = Object.keys(FIELD_LABELS)
    .filter((f) => f in map && String(old[f] || "") !== String(saved[f] || ""))
    .map((f) => FIELD_LABELS[f] + ": " + (old[f] || "—") + " → " + (saved[f] || "—"));
  if (changes.length) log_(actor, task.id, old.done !== saved.done ? (saved.done ? "Tamamlandı" : "Yeniden açıldı") : "Güncellendi", changes.join("; "));
  return saved;
}

// ---- Günlük e-posta ------------------------------------------------------------
// Her sabah bugün yapılması gerekenleri (gecikenler, bugün termini dolanlar, bugünkü alt paketler)
// ve önümüzdeki 3 günü e-postayla gönderir. Alıcı: E-Tablonun sahibi; istenirse her birimin sorumlusu.

function gunlukEpostaAc() {
  removeDigestTriggers_();
  ScriptApp.newTrigger("gunlukEpostaGonder").timeBased()
    .atHour(EMAIL_HOUR).nearMinute(EMAIL_MINUTE).everyDays(1).inTimezone(EMAIL_TZ).create();
  const ui = SpreadsheetApp.getUi();
  ui.alert("Günlük e-posta açıldı",
    "Her sabah " + EMAIL_HOUR + ":" + EMAIL_MINUTE + " civarında (Google ±15 dakika esneklikle çalıştırır) " +
    Session.getEffectiveUser().getEmail() + " adresine bugünün işleri gönderilecek.", ui.ButtonSet.OK);
}

function gunlukEpostaKapat() {
  const n = removeDigestTriggers_();
  SpreadsheetApp.getUi().alert(n ? "Günlük e-posta kapatıldı." : "Günlük e-posta zaten kapalıydı.");
}

function gunlukEpostaDene() {
  const sent = gunlukEpostaGonder(true);
  SpreadsheetApp.getUi().alert("Gönderildi: " + sent.join(", "));
}

function birimEpostaDegistir() {
  const props = PropertiesService.getScriptProperties();
  const on = props.getProperty("UNIT_EMAILS") !== "1";
  props.setProperty("UNIT_EMAILS", on ? "1" : "0");
  SpreadsheetApp.getUi().alert(on
    ? "Birim sorumluları da her sabah kendi birimlerinin işlerini alacak (Birimler sayfasındaki E-posta sütunu)."
    : "Birim sorumlularına e-posta gönderimi kapatıldı. Yalnızca size gönderilecek.");
}

function removeDigestTriggers_() {
  let n = 0;
  ScriptApp.getProjectTriggers().forEach((t) => {
    if (t.getHandlerFunction() === "gunlukEpostaGonder") { ScriptApp.deleteTrigger(t); n++; }
  });
  return n;
}

// Tetikleyici çağırır. force: boş gün de olsa gönder (deneme için).
function gunlukEpostaGonder(force) {
  force = force === true;
  const data = readAll_();
  const today = Utilities.formatDate(new Date(), EMAIL_TZ, "yyyy-MM-dd");
  const page = PropertiesService.getScriptProperties().getProperty("PAGE_URL") || "";
  const owner = Session.getEffectiveUser().getEmail();
  const sent = [];

  const digest = (units) => {
    const inUnits = (t) => !units || units.indexOf(t.unit) >= 0;
    const open = data.tasks.filter((t) => inUnits(t) && !t.done && t.status !== "İptal");
    const subs = data.subs.filter((x) => !x.done && x.due && x.due <= today &&
      open.some((t) => t.id === x.taskId));
    return {
      late: open.filter((t) => t.due && t.due < today).sort((a, b) => (a.due < b.due ? -1 : 1)),
      todayList: open.filter((t) => t.due === today),
      subs: subs,
      soon: open.filter((t) => t.due > today && daysBetween_(today, t.due) <= 3).sort((a, b) => (a.due < b.due ? -1 : 1))
    };
  };

  const send = (to, d, who) => {
    const count = d.late.length + d.todayList.length + d.subs.length;
    if (!count && !d.soon.length && !force) return;
    const subject = "İş Takip · " + formatTr_(today) + " · " +
      (count ? count + " iş bugün sizi bekliyor" : "Bugün için acil iş yok");
    MailApp.sendEmail({ to: to, subject: subject, htmlBody: digestHtml_(d, today, page, who), name: "İş Takip" });
    sent.push(to);
  };

  if (owner) send(owner, digest(null), "Tüm birimler");

  if (PropertiesService.getScriptProperties().getProperty("UNIT_EMAILS") === "1") {
    const byEmail = {};
    data.unitInfo.forEach((u) => {
      const mail = String(u.email || "").trim().toLowerCase();
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(mail) || mail === String(owner).toLowerCase()) return;
      (byEmail[mail] = byEmail[mail] || []).push(u.name);
    });
    Object.keys(byEmail).forEach((mail) => send(mail, digest(byEmail[mail]), byEmail[mail].join(", ")));
  }
  return sent;
}

function daysBetween_(a, b) { return Math.round((Date.parse(b) - Date.parse(a)) / 86400000); }

function formatTr_(iso) {
  if (!iso) return "—";
  const p = String(iso).split("-");
  return p[2] + "." + p[1] + "." + p[0];
}

function escHtml_(s) {
  return String(s == null ? "" : s).replace(/[&<>"]/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]));
}

function digestHtml_(d, today, page, who) {
  const row = (t, extra, color) =>
    '<tr><td style="width:84px;padding:8px 10px;border-bottom:1px solid #dfe4eb;color:#5d6b7e;white-space:nowrap">' + escHtml_(t.id) + "</td>" +
    '<td style="padding:8px 10px;border-bottom:1px solid #dfe4eb"><b>' + escHtml_(t.title) + "</b><br>" +
    '<span style="color:#5d6b7e;font-size:12px">' + escHtml_([t.unit, t.priority].filter(Boolean).join(" · ")) + "</span></td>" +
    '<td style="width:120px;padding:8px 10px;border-bottom:1px solid #dfe4eb;white-space:nowrap;text-align:right;color:' + color + ';font-weight:bold">' + extra + "</td></tr>";
  const section = (title, list, render) => !list.length ? "" :
    '<h3 style="margin:22px 0 6px;font-size:15px;color:#142030">' + title + " (" + list.length + ")</h3>" +
    '<table style="width:100%;border-collapse:collapse;font-size:14px;table-layout:fixed">' + list.map(render).join("") + "</table>";

  let html = '<div style="font-family:Arial,sans-serif;max-width:640px;color:#142030">' +
    '<div style="background:#0e2a3f;color:#fff;padding:18px 20px;border-radius:12px">' +
    '<div style="font-size:12px;color:#a9c0d4;letter-spacing:.06em">' + formatTr_(today) + " · " + escHtml_(who) + "</div>" +
    '<div style="font-size:20px;font-weight:bold;margin-top:4px">Bugün yapılması gerekenler</div></div>';
  html += section("Gecikenler", d.late, (t) => row(t, daysBetween_(t.due, today) + " gün gecikti", "#b42323"));
  html += section("Bugün termini dolanlar", d.todayList, (t) => row(t, "Bugün", "#8a5a00"));
  html += section("Bugünkü ve geciken alt paketler", d.subs, (x) => row({ id: x.id, title: x.title, unit: "Alt paket", priority: "" },
    x.due < today ? daysBetween_(x.due, today) + " gün gecikti" : "Bugün", x.due < today ? "#b42323" : "#8a5a00"));
  html += section("Önümüzdeki 3 gün", d.soon, (t) => row(t, formatTr_(t.due), "#1c5cab"));
  if (!d.late.length && !d.todayList.length && !d.subs.length) {
    html += '<p style="margin:22px 0;color:#0a6e0a;font-weight:bold">Bugün için geciken ya da termini dolan iş yok.</p>';
  }
  if (page) html += '<p style="margin-top:24px"><a href="' + escHtml_(page) + '" style="background:#2a78d6;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;font-weight:bold">İş Takip\'i aç</a></p>';
  html += '<p style="color:#98a2b3;font-size:12px;margin-top:24px">Bu e-postayı kapatmak için E-Tabloda İş Takip → Günlük e-postayı kapat.</p></div>';
  return html;
}
